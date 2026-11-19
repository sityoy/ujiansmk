# VPS CBT SMK Islam Bahagia

Profil awal untuk 215 peserta, 10 vCPU, RAM 8 GB, dan disk 30 GB. Gunakan hasil pengukuran di VPS untuk menetapkan kapasitas akhir, terutama bila database dan situs lain ada di mesin yang sama.

## Pemeriksaan sebelum deployment

- Jalankan `nproc`, `free -h`, `df -h /var/www/ujiansmk`, dan `swapon --show`. Pastikan perangkat yang diukur adalah VPS yang melayani domain CBT.
- Periksa versi serta pool PHP-FPM, ukuran memori proses, dan penggunaan database saat aplikasi aktif. Di Linux, gunakan PSS dari `/proc/*/smaps_rollup` bila tersedia. RSS dapat dipakai sebagai perkiraan konservatif.
- Pertahankan database, `APP_KEY`, session, domain, dan penyimpanan yang sudah dipakai. Jika pemindahan VPS diperlukan, pindahkan database dan `storage` sebelum mengalihkan domain. Jangan gunakan database produksi untuk uji beban.
- Jalankan `php artisan exams:readiness`. Pastikan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS aktif, dan scheduler pengumpulan Ujian Inti terpasang sekali. Periksa juga sisa disk untuk lampiran dan unggahan absensi.
- Simpan cadangan database dan file unggahan di luar jam ujian. Jalankan `scripts/deploy-vps.sh` saat Ujian Inti dan Uji Coba kosong. Periksa `/health` dan lakukan login sebagai petugas dan siswa sesudah rilis.

## Nilai awal untuk PHP-FPM

Gunakan pool yang sudah terhubung ke CBT dan cocokkan versi PHP dengan konfigurasi Nginx. Pada VPS khusus CBT, mulai dengan 24 pekerja hanya jika budget PHP sekurangnya 4 GB dan PSS/RSS per proses saat beban berat sekitar 160 MiB atau kurang. Sisakan memori untuk database, sistem operasi, dan aplikasi lain. Jika budget lebih kecil, pilih batas pekerja sesuai pengukuran.

```ini
pm = dynamic
pm.max_children = 24
pm.start_servers = 8
pm.min_spare_servers = 6
pm.max_spare_servers = 12
pm.max_requests = 500
```

Gunakan OPcache FPM dan periksa konfigurasi yang sudah aktif sebelum menambah berkas baru. Nilai awal untuk VPS ini adalah `opcache.memory_consumption=192` dan `opcache.validate_timestamps=1`. Cek berkas konfigurasi dengan `php-fpm<VERSI> -t` dan `nginx -t` sebelum me-reload layanan. Biarkan Nginx melayani berkas Vite dan lampiran langsung. Pertahankan akses lampiran yang dikonfigurasi aplikasi. Gunakan cache browser berdurasi panjang khusus berkas Vite yang namanya mengandung hash, tanpa cache proxy untuk halaman ujian dan API siswa.

## Simulasi kapasitas

1. Gunakan data sintetis pada salinan aplikasi dengan konfigurasi setara produksi. Jalankan login 215 akun berbeda dari IP jaringan sekolah yang sama, kemudian mulai ujian, simpan jawaban beberapa kali, buka lampiran, dan kumpulkan jawaban serentak. Uji Uji Coba dan Ujian Inti, termasuk absensi serta unggahan selfie jika diaktifkan.
2. Selama tes, rekam status HTTP dan p95 untuk login, simpan, dan pengumpulan. Sasaran awal adalah nol respons 429/5xx pada alur sah, p95 autosave di bawah 2 detik, dan semua jawaban tercatat. Periksa antrian FPM, penggunaan RAM/swap, CPU steal, waktu query, dan sisa disk. Ulangi pada jaringan sekolah.
3. Jika antrian FPM panjang dan masih ada RAM serta CPU, tambah pekerja bertahap dan ulangi tes. Bila yang penuh adalah CPU, I/O, atau database, periksa proses dan query yang paling mahal sebelum menambah pekerja.

Rujukan resmi [PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php), [OPcache](https://www.php.net/manual/en/opcache.configuration.php), dan [deployment Laravel](https://laravel.com/docs/deployment).
