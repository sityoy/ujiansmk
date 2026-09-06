# Sistem Ujian Sekolah

Aplikasi ini adalah pengembangan ulang sistem ujian sekolah menggunakan Laravel 13. Branch ini dibangun terpisah dari aplikasi PHP lama agar data pengujian dan proses migrasi dapat dikendalikan.

## Ruang lingkup tahap pertama

- Tahun ajaran, semester ganjil/genap, kelas, siswa, kampus, dan mata pelajaran.
- Periode asesmen ATS, AAS, AAT, UUB, dan jenis lain yang dapat dikembangkan.
- Jadwal ujian reguler dan susulan.
- Satu penugasan ujian untuk setiap siswa dan komponen asesmen.
- Satu percobaan aktif untuk setiap penugasan.
- Absensi satu kali per hari menggunakan kartu/wajah dan validasi radius kampus.
- Pencatatan insiden keamanan tanpa langsung menghukum siswa dari sinyal browser tunggal.
- Bank soal pilihan ganda, autosave jawaban, penghitung waktu, pengumpulan otomatis, dan perhitungan nilai.
- Impor/ekspor Excel untuk data siswa dan mata pelajaran.
- Rapor ATS per mata pelajaran beserta peringkat kelas.

## Akun siswa hasil impor

Saat siswa baru diimpor, sistem otomatis membuat akun. Username dan password awal memakai NISN jika tersedia; jika NISN kosong, keduanya memakai NIS. Siswa diwajibkan mengganti password awal setelah login pertama.

Password tidak pernah dimasukkan ke file ekspor. Ketika data siswa diimpor ulang dengan kolom Password kosong, password akun yang sudah ada tetap dipertahankan. Jika kolom Password diisi, nilai tersebut menjadi password awal baru dan siswa kembali diwajibkan menggantinya.

## Aturan anti-duplikasi

Database menolak penugasan ganda berdasarkan pasangan `student_id` dan `assessment_subject_id`. Siswa yang tidak hadir pada jadwal reguler dipindahkan ke sesi susulan melalui `ExamAssignmentService`; sistem tidak membuat hasil ujian baru.

Absensi harian dibatasi oleh pasangan `student_id` dan `attendance_date`. Selfie lengkap cukup satu kali pada hari ujian dan dapat digunakan oleh beberapa sesi mapel pada hari yang sama di kampus yang sama. File selfie disimpan privat dan hanya dapat dibuka oleh petugas berwenang.

Jadwal selesai dihitung otomatis dari jam mulai dan durasi. Sistem menolak tabrakan waktu pada kelas, ruangan, dan penugasan siswa. Jadwal reguler wajib berada dalam periode asesmen; jadwal susulan dapat ditempatkan sesudah periode selama merujuk sesi reguler yang sesuai.

Sesi yang sudah dibuat dapat diedit kembali dan waktu selesai akan dihitung ulang. Identitas komponen berupa periode, mapel, dan kelas hanya dapat diubah selama belum memiliki sesi, peserta, atau soal agar riwayat ujian tidak berpindah ke komponen yang salah.

Jadwal, lokasi, dan durasi sesi terkunci setelah percobaan ujian pertama. Bank soal komponen juga terkunci setelah siswa pertama mulai, termasuk untuk menjaga kesamaan penilaian susulan. Menutup sesi melalui Penjadwalan ataupun Pelaksanaan mengumpulkan semua percobaan yang masih berjalan. Sesi tertutup tidak dapat dibuka ulang; gunakan alur susulan untuk peserta yang belum mengerjakan.

## Pemantauan peserta per sesi

Menu **Pelaksanaan ujian → Lihat peserta & progres** menampilkan seluruh peserta yang saat ini ditempatkan pada sesi, 25 baris per halaman. Pengawas/panitia dapat mencari nama, NIS, atau NISN dan memfilter status pengerjaan. Absensi diambil berdasarkan tanggal serta lokasi sesi, progres berdasarkan jawaban yang sudah tersimpan, dan ringkasan tetap mencakup seluruh peserta sesi. Halaman dimuat ulang secara manual; waktu simpan terakhir bukan indikator online.

Halaman Pelaksanaan mendukung filter tahun ajaran, tanggal, dan status sesi. Ringkasan dan daftar aktivitas mengikuti filter yang sama; aktivitas tidak lagi dibatasi hanya 30 catatan terakhir tanpa halaman berikutnya. Insiden browser adalah sinyal pemeriksaan, bukan bukti kecurangan otomatis.

Saat sesi ditutup setelah waktu mulai, peserta berstatus terjadwal yang tidak memiliki percobaan ditandai tidak hadir ujian. Peserta yang sudah mulai dikumpulkan, sedangkan peserta dibatalkan tidak diubah. Penutupan sebelum waktu mulai tidak menandai ketidakhadiran. Susulan tetap memakai penugasan yang sama; peserta dibatalkan/yang sudah memiliki percobaan dan sesi susulan tertutup ditolak. Penandaan ini terjadi ketika panitia menutup sesi, bukan otomatis ketika waktu berakhir.

## Pengumpulan otomatis di server

Aktifkan Laravel Scheduler pada server agar ujian kedaluwarsa tetap dikumpulkan meskipun browser siswa ditutup. Jalankan dengan akun aplikasi dan PHP yang sama dengan aplikasi. Contoh cron (ganti `/path/to/ujiansmk` dengan lokasi proyek sebenarnya):

```cron
* * * * * cd /path/to/ujiansmk && php artisan schedule:run >> /dev/null 2>&1
```

Untuk pengujian lokal, jalankan `php artisan schedule:work` pada terminal terpisah. Untuk memproses ujian kedaluwarsa satu kali gunakan `php artisan exams:finalize-expired`. Perintah ini tidak mengubah percobaan yang sudah dikumpulkan atau dihentikan. Perubahan kode tidak otomatis memasang cron pada hosting.

## Deployment production ringan

Aset frontend branch `laravel-v2` dibangun otomatis oleh GitHub Actions dan disimpan di `public/build`. VPS production tidak perlu menjalankan `npm ci`, `npm install`, atau `npm run build`. Gunakan `scripts/deploy-vps.sh`; skrip menolak deployment jika masih ada peserta yang sedang mengerjakan dan menjalankan pemeriksaan kesiapan setelah pembaruan.

Konfigurasi PHP-FPM, Nginx, `.env`, scheduler, serta checklist untuk VPS 2 GB tersedia di [docs/VPS_2GB.md](docs/VPS_2GB.md).

Absensi dibuka pada hari ujian mulai 60 menit sebelum sesi sampai waktu selesai. Portal menampilkan alasan jika absensi tertutup, termasuk sesi draf, lokasi nonaktif, jadwal tidak valid, atau penugasan tidak aktif. Absensi yang ditolak/diperiksa pengawas tidak dapat diverifikasi ulang sendiri oleh siswa. Selfie dan GPS merupakan bukti pendukung, bukan pengenalan wajah biometrik atau jaminan bahwa lokasi tidak dipalsukan.

Autosave mengirim jawaban berurutan, menyediakan percobaan ulang, dan menunggu jawaban tertunda sebelum pengumpulan manual. Jawaban yang belum sampai ke server saat batas waktu habis tidak dihitung. Setelah pembaruan, uji alur lengkap dengan akun siswa dan panitia serta koneksi lambat sebelum dipakai pada ujian sesungguhnya.

## Persyaratan

- PHP 8.4.1 atau lebih baru.
- Composer 2.
- MySQL 8 atau MariaDB yang kompatibel.
- Node.js 20 atau lebih baru.
- HTTPS untuk kamera dan geolokasi pada perangkat siswa.

## Instalasi pengembangan

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan test
php artisan serve
```

Jangan menyalin `koneksi.php`, dump database, password, foto selfie, atau folder unggahan dari branch lama ke repository ini. Data lama harus dipindahkan melalui importer terkontrol setelah disanitasi.

## Status pengembangan

### Rapor ATS bergaya E-Rapor

Rapor ATS memiliki data terpisah dari percobaan CBT agar nilai asesmen kertas tetap dapat dimasukkan tanpa membuat riwayat ujian palsu. Nilai CBT yang sudah dikumpulkan menjadi nilai awal; guru mapel yang ditetapkan dapat mengisi atau menyesuaikan nilai, Tujuan Pembelajaran (TP), dan deskripsi capaian setiap peserta.

Super admin menetapkan wali kelas pada Data Akademik. Wali kelas mengisi rekap sakit, izin, tanpa keterangan, dan catatan wali kelas. Panitia/super admin menambahkan kegiatan ekstrakurikuler serta pesertanya, sedangkan guru pembina memberi predikat Sangat Baik, Baik, Cukup, atau Kurang. Keterangan ekstrakurikuler dibuat otomatis sesuai predikat jika kolom keterangan dikosongkan.

Cetak rapor memuat KOP sekolah, nilai dan deskripsi mapel, ekstrakurikuler, ketidakhadiran, catatan wali kelas, rata-rata, serta peringkat. KOP bawaan SMK Islam Bahagia dapat diganti melalui Identitas Sekolah.

### ATS isian/esai, koreksi manual, dan reset ujian

Bank soal ATS hanya menerima tipe **isian singkat** dan **esai**. Jawaban siswa tidak diberi nilai otomatis; setelah dikumpulkan, guru yang ditetapkan pada komponen mapel/kelas mengoreksi melalui menu **Koreksi jawaban** dan memberi poin per soal. Panitia dan super admin juga dapat melakukan koreksi. Nilai akhir baru diterbitkan ke rapor ATS setelah koreksi disimpan.

Daftar pada **Penjadwalan → 05 · Sesi Ujian** ditampilkan sebagai kelompok tertutup berdasarkan periode, mapel, dan kelas agar halaman tidak menumpuk. Buka judul yang ingin diatur untuk melihat guru, bank soal, sesi reguler, dan sesi susulan.

Pada **Pelaksanaan ujian → Lihat peserta & progres**, panitia dan super admin memiliki dua tindakan berbeda: reset pelanggaran hanya mengembalikan hitungan ke 0 tanpa menghapus jawaban, sedangkan reset seluruh ujian menghapus percobaan aktif agar siswa memulai dari awal. Reset seluruh ujian wajib disertai alasan dan ringkasan percobaan lama disimpan dalam audit. Sesi yang sudah ditutup/berakhir tidak dapat direset; gunakan sesi susulan.

### Pengawasan campuran — batas dua pelanggaran

Percobaan ujian baru memakai aturan: kejadian pertama diperingatkan, kejadian kedua dikunci di server. Siswa tidak dapat menyimpan jawaban baru selama terkunci, termasuk melalui permintaan langsung atau refresh. Jawaban tersimpan tidak dihapus dan waktu tidak berhenti. Scheduler tetap mengumpulkan percobaan terkunci saat kedaluwarsa. Percobaan yang sudah ada sebelum migrasi mempertahankan kebijakan lama agar pembaruan tidak mendadak menghentikan ujian berjalan.

Sinyal yang dihitung adalah halaman tersembunyi (`visibilitychange`) dan keluar fullscreen. Browser tidak bisa memastikan penyebabnya atau membuktikan kecurangan. `blur`, gerakan kursor, sentuhan bilah sistem, dan hilangnya koneksi tidak otomatis menambah pelanggaran. Satu perpindahan menonaktifkan sensor sampai siswa kembali mengonfirmasi; server juga menggabungkan sinyal dalam tiga detik dan menduplikasi ulang kiriman berdasarkan ID kejadian. Permintaan gagal disimpan sementara di browser untuk dikirim ulang dengan ID sama. Jika sinkronisasi gagal, halaman ditahan sampai koneksi pulih tanpa menambah pelanggaran koneksi.

Fullscreen diminta lewat tombol persetujuan. Browser yang tidak mendukung fullscreen mendapat pemberitahuan dan tetap memakai pengawasan visibilitas. Pembatasan salin/tempel/menu konteks hanyalah pencegahan ringan, bukan penguncian sistem operasi. Pengawasan JavaScript dapat dimanipulasi klien dan tidak mendeteksi aplikasi tertentu, screenshot, split-screen secara pasti, atau perangkat kedua. Tetap perlu pengawas.

Pengawas/panitia/super admin memeriksa lewat **Pelaksanaan ujian → Lihat peserta & progres → filter Terkunci**. Alasan wajib diisi untuk mengizinkan lanjut atau mengumpulkan jawaban. Izin lanjut tidak menghapus riwayat, jawaban, atau hitungan 2/2; kejadian berikutnya mengunci kembali. Formulir pemeriksaan lama ditolak jika versi kunci berubah. Ujian yang sudah dikumpulkan atau waktunya habis tidak bisa dibuka ulang.

Setelah memperbarui kode, jalankan `php artisan migrate --force`, `php artisan optimize:clear`, dan build aset. Jangan menjalankan `migrate:fresh` atau mereset database. Uji fullscreen dan gangguan koneksi pada HP/browser yang akan dipakai sekolah sebelum penerapan massal.

Fondasi domain, autentikasi berbasis peran, data akademik, penjadwalan, sesi susulan, absensi, bank soal pilihan ganda, pengerjaan ujian siswa, monitoring dasar, dan rapor ATS tersedia. Pengembangan lanjutan dapat mencakup paket soal acak, soal esai, pengenalan wajah biometrik dengan persetujuan dan kebijakan privasi, serta importer terkontrol untuk data aplikasi lama.

Endpoint pemeriksaan aplikasi tersedia di `GET /health`.
