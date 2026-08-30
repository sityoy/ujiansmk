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

Fondasi domain, autentikasi berbasis peran, data akademik, penjadwalan, sesi susulan, absensi, bank soal pilihan ganda, pengerjaan ujian siswa, monitoring dasar, dan rapor ATS tersedia. Pengembangan lanjutan dapat mencakup paket soal acak, soal esai, pengenalan wajah biometrik dengan persetujuan dan kebijakan privasi, serta importer terkontrol untuk data aplikasi lama.

Endpoint pemeriksaan aplikasi tersedia di `GET /health`.
