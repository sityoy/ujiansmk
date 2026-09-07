# Operasional VPS 2 GB

Panduan ini ditujukan untuk server `ujiansmk.smkpb1.my.id` dengan 2 vCPU, RAM 2 GB, dan disk 20 GB. Jangan menjalankan deployment ketika peserta sedang ujian.

## Deployment tanpa Node.js di VPS

Workflow **Build production assets** menjalankan `npm ci` dan `npm run build` di GitHub Actions lalu menyimpan `public/build` ke branch `laravel-v2`. Karena itu VPS tidak memerlukan Node.js untuk deployment rutin.

Pertama kali setelah fitur ini tersedia:

```bash
cd /var/www/ujiansmk
git pull --ff-only origin laravel-v2
chmod +x scripts/deploy-vps.sh
./scripts/deploy-vps.sh
```

Deployment berikutnya cukup:

```bash
cd /var/www/ujiansmk
./scripts/deploy-vps.sh
```

Skrip akan berhenti bila ada perubahan Git lokal, aset frontend belum selesai dibangun, atau masih ada ujian berstatus `in_progress`. Saat aman, skrip mengaktifkan maintenance mode, memperbarui kode secara fast-forward, memasang Composer tanpa paket pengembangan, menjalankan migrasi dan cache, lalu membuka aplikasi kembali. Jika salah satu langkah gagal, aplikasi tetap dicoba dibuka kembali.

## Konfigurasi `.env`

Pertahankan rahasia dan konfigurasi database yang sudah ada. Samakan pengaturan production dengan `deploy/env.production.example`, terutama:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
SESSION_DRIVER=database
SESSION_LIFETIME=180
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

Database cocok dipakai untuk session dan cache pada satu VPS tanpa menambah konsumsi RAM Redis. Aplikasi saat ini tidak memakai pekerjaan antrean asinkron, jadi `QUEUE_CONNECTION=sync` menghindari kebutuhan worker tambahan.

## PHP-FPM 8.4

Untuk server yang juga menjalankan situs lain, gunakan batas awal yang konservatif di `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests = 300
request_terminate_timeout = 120s
```

Tambahkan konfigurasi aplikasi di `/etc/php/8.4/fpm/conf.d/99-ujiansmk.ini`:

```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
```

Periksa lalu muat ulang hanya setelah perubahan valid:

```bash
sudo php-fpm8.4 -t
sudo systemctl reload php8.4-fpm
```

Nilai `pm.max_children = 8` adalah titik awal, bukan jaminan kapasitas. Karena VPS dipakai bersama aplikasi lain, pantau penggunaan memori ketika simulasi dan turunkan nilainya bila swap terus aktif atau proses dihentikan oleh OOM.

## Nginx dan unggahan selfie

Virtual host aplikasi perlu memuat batas berikut:

```nginx
client_max_body_size 10M;
client_body_timeout 60s;
fastcgi_read_timeout 120s;
```

Setelah mengubah konfigurasi:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Scheduler wajib

Pastikan cron berikut ada tepat satu kali untuk akun pengguna aplikasi:

```cron
* * * * * cd /var/www/ujiansmk && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Scheduler mengumpulkan ujian yang waktunya habis meskipun siswa menutup browser.

## Pemeriksaan sebelum ujian

Jalankan:

```bash
cd /var/www/ujiansmk
php artisan exams:readiness
curl -fsS https://ujiansmk.smkpb1.my.id/health
free -h
df -h /var/www/ujiansmk
```

Semua pemeriksaan kritis pada `exams:readiness` harus berstatus **OK**. Pastikan sisa disk sekurangnya 1 GB, tidak ada proses OOM, dan lakukan simulasi dengan jumlah perangkat serta jaringan yang mendekati ujian sebenarnya. Kapasitas peserta tidak dapat ditentukan hanya dari jumlah CPU/RAM karena juga dipengaruhi MySQL, aplikasi lain, ukuran selfie, dan pola autosave.

Periksa apakah swap tersedia dengan `swapon --show`. Swap membantu mencegah proses langsung mati ketika ada lonjakan memori, tetapi bukan pengganti RAM dan bukan solusi untuk beban ujian yang terlalu besar.

## Aturan operasional

- Hindari deployment, backup besar, impor Excel massal, atau pembaruan sistem selama ujian.
- Gunakan koneksi kabel untuk server dan lakukan simulasi perangkat sebelum hari pelaksanaan.
- Pantau `storage/logs`, `storage/app/private/checkins`, ruang disk, RAM, dan log Nginx/PHP-FPM.
- Simpan backup database sebelum migrasi, tetapi jalankan backup di luar jam ujian.
- Jangan menjalankan `npm ci`, `npm install`, atau `npm run build` di VPS.
