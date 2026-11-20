# VPS CBT SMK Islam Bahagia

Profil awal untuk sekitar 215 peserta pada VPS 8 vCPU, RAM 10 GB, dan disk 30 GB. Ukur kapasitas pada VPS yang melayani domain CBT, terutama jika database dan aplikasi lain berjalan di mesin yang sama.

## Cek konfigurasi yang berjalan

Jalankan dari VPS sebelum mengubah layanan. Perintah ini menampilkan kapasitas, versi layanan, dan konfigurasi Laravel yang relevan.

```bash
cd /var/www/ujiansmk
nproc
free -h
swapon --show
df -h . /var/log
systemctl list-units --type=service 'php*-fpm.service' 'nginx.service' 'mariadb.service' 'mysql.service'
php artisan exams:readiness
php artisan schedule:list
php artisan tinker --execute='dump(config("database.default"), config("session.driver"), config("cache.default"));'
ps -eo rss=,args= | awk '$2 == "php-fpm:" && $3 == "pool" {print $4, $1 " KiB"}'
```

Periksa bahwa domain peserta memakai HTTPS agar browser dapat meminta izin lokasi. Pastikan scheduler berjalan sekali tiap menit pada server yang sama. Simpan cadangan database dan unggahan di luar disk VPS sebelum rilis. Pantau disk dan rotasi log, dengan sasaran ruang kosong awal minimal 5 GB sebelum simulasi.

## PHP-FPM dan OPcache

Temukan pool PHP-FPM yang dipakai vhost CBT. Terapkan profil awal ini pada pool tersebut jika ada anggaran memori PHP sekitar 4 GB dan penggunaan per pekerja saat sibuk sekitar 160 MiB atau kurang.

```ini
pm = dynamic
pm.max_children = 24
pm.start_servers = 8
pm.min_spare_servers = 6
pm.max_spare_servers = 12
pm.max_requests = 500
```

`pm.max_children` adalah batas request PHP serentak. Siswa mengisi jawaban secara bergantian dan satu pekerja melayani banyak request seiring waktu. Untuk menghitung batas dari memori, bagi anggaran PHP dengan penggunaan per pekerja saat beban tinggi. Misalnya, anggaran 4 GiB dengan 160 MiB per pekerja memberi batas sekitar 25 pekerja. Sisakan memori untuk database, OS, dan aplikasi lain. Gunakan PSS jika tersedia atau RSS sebagai perkiraan konservatif.

Periksa konfigurasi OPcache untuk FPM dan sesuaikan nilai awal berikut pada berkas INI yang sudah dipakai layanan.

```ini
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
```

Cadangkan konfigurasi yang akan diubah. Contoh validasi dan reload untuk PHP 8.4 berikut dijalankan di luar jam ujian. Sesuaikan nama binari dan layanan jika vhost memakai versi lain.

```bash
sudo php-fpm8.4 -t
sudo nginx -t
sudo systemctl reload php8.4-fpm
```

Biarkan Nginx melayani aset Vite dan lampiran publik secara langsung. Batasi cache berdurasi panjang ke aset Vite yang namanya mengandung hash. Halaman ujian, absensi, dan endpoint penyimpanan jawaban membutuhkan respons per peserta. Pertahankan database, session, dan cache yang sedang dipakai saat rilis. Skrip `./scripts/deploy-vps.sh` menjalankan cache konfigurasi, rute, dan view setelah pembaruan.

## Uji serentak sebelum hari ujian

1. Di lingkungan uji dengan konfigurasi setara produksi dan data sintetis, jalankan sekitar 215 siswa dari satu IP jaringan sekolah. Uji fase login atau buka tautan, absensi lokasi, mulai, simpan beberapa jawaban, buka gambar, dan kumpulkan. Sertakan Ujian Inti serta Ujian Uji Coba.
2. Catat status HTTP, p95 absensi, p95 penyimpanan, dan jumlah jawaban yang benar-benar tersimpan. Sasaran awal untuk alur sah adalah 0 respons 429/5xx, p95 autosave di bawah 2 detik, dan seluruh jawaban uji tercatat. Cek juga GPS di beberapa HP dari dalam gedung.
3. Selama pengujian jalankan `vmstat 1` dan observasi antrean FPM jika status internalnya sudah tersedia. Jika antrean meningkat dengan CPU dan RAM yang masih longgar, tambah pekerja bertahap lalu ulangi tes. Jika swap aktif terus atau database menunggu penulisan, periksa beban layanan dan query sebelum menambah pekerja. Pantau juga kualitas Wi-Fi sekolah karena semua perangkat menggunakannya pada saat yang sama.
4. Deploy di luar ujian. Setelah rilis, cek `/health`, login petugas dan peserta, verifikasi lokasi sebelum timer, simpan jawaban, dan buka **Laporan Absensi Simulasi** dari **Pemantauan Peserta Uji Coba**.

Rujukan resmi [PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php), [OPcache](https://www.php.net/manual/en/opcache.configuration.php), [deployment Laravel](https://laravel.com/docs/deployment), dan [izin Geolocation](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/getCurrentPosition).
