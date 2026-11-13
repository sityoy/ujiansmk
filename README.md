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

### Manajemen akun

Super admin dapat membuat, mencari, memfilter, mengaktifkan/nonaktifkan, dan mengedit akun petugas sekolah. Daftar dibatasi 10 akun per halaman. Sistem membatasi satu akun Kepala Sekolah dan paling banyak empat akun Super Admin, termasuk perubahan melalui impor Excel.

Template dan ekspor akun memuat ID, nama, email, akses, Password Baru, dan status. Password lama tidak pernah dapat dibaca atau diekspor karena tersimpan sebagai hash. Password Baru wajib untuk akun baru; saat memperbarui akun yang sudah ada, kolom kosong mempertahankan password lama dan nilai yang diisi akan mereset password serta mewajibkan pengguna menggantinya saat login.

### Rapor ATS bergaya E-Rapor

Rapor ATS memiliki data terpisah dari percobaan CBT agar nilai asesmen kertas tetap dapat dimasukkan tanpa membuat riwayat ujian palsu. Nilai CBT yang sudah dikumpulkan menjadi nilai awal. Guru mapel yang ditetapkan menyiapkan Tujuan Pembelajaran (TP), mengisi atau menyesuaikan nilai, lalu menandai TP yang tercapai optimal dan yang perlu ditingkatkan. Deskripsi capaian kompetensi dibuat otomatis dari pilihan tersebut.

Super admin menetapkan wali kelas pada Data Akademik. Wali kelas mengisi rekap sakit, izin, tanpa keterangan, dan catatan wali kelas. Panitia/super admin menambahkan kegiatan ekstrakurikuler serta pesertanya, sedangkan guru pembina cukup memberi predikat Sangat Baik, Baik, Cukup, atau Kurang. Keterangan ekstrakurikuler selalu dibuat otomatis sesuai predikat.

Cetak rapor memuat KOP sekolah, nilai dan capaian kompetensi mapel, ekstrakurikuler, ketidakhadiran, catatan wali kelas, rata-rata, serta peringkat. Kelas X menggunakan Fase E dan kelas XI–XII menggunakan Fase F. Panel **Pengaturan Hasil Cetak** ditempatkan pada halaman cetak rapor seperti alur e-Rapor: ukuran kertas A4/F4, margin empat sisi, tempat, tanggal, skala isi, dan pilihan kelas. Posisi tanda tangan kepala sekolah tetap di bawah, nama wali kelas terisi otomatis, dan setiap rapor dimulai dari halaman 1. Cetak hanya dapat diakses super admin, panitia, kepala sekolah, dan guru yang ditugaskan sebagai wali kelas. KOP bawaan SMK Islam Bahagia dapat diganti melalui Identitas Sekolah.

### ATS isian/esai, koreksi manual, dan reset ujian

Bank soal ATS hanya menerima tipe **isian singkat** dan **esai**. Jawaban siswa tidak diberi nilai otomatis; setelah dikumpulkan, guru yang ditetapkan pada komponen mapel/kelas mengoreksi melalui menu **Koreksi jawaban** dan memberi poin per soal. Panitia dan super admin juga dapat melakukan koreksi. Nilai akhir baru diterbitkan ke rapor ATS setelah koreksi disimpan.

Guru mempunyai menu **Bank soal** yang hanya menampilkan periode, mapel, dan kelas yang ditugaskan kepadanya. Panitia/Super Admin menetapkan guru pada komponen penjadwalan, sedangkan guru menulis soal serta menentukan bobot. Panitia dan Super Admin tetap memiliki akses pengawasan. Nilai akhir dihitung dari `poin diperoleh / total bobot × 100`; total bobot disarankan 100 agar pemeriksaan mudah, misalnya 10 isian × 3 dan 10 esai × 7.

Daftar pada **Penjadwalan → 05 · Sesi Ujian** ditampilkan sebagai kartu ringkas berbentuk grid dan dibatasi 12 komponen per halaman. Daftar dapat difilter berdasarkan periode, kelas, nama, atau kode mapel. Kartu yang dibuka melebar penuh untuk menampilkan guru, bank soal, sesi reguler, dan sesi susulan tanpa membuat kartu lain memanjang.

Pada **Pelaksanaan ujian → Lihat peserta & progres**, panitia dan super admin memiliki dua tindakan berbeda: reset pelanggaran hanya mengembalikan hitungan ke 0 tanpa menghapus jawaban, sedangkan reset seluruh ujian menghapus percobaan aktif agar siswa memulai dari awal. Reset seluruh ujian wajib disertai alasan dan ringkasan percobaan lama disimpan dalam audit. Sesi yang sudah ditutup/berakhir tidak dapat direset; gunakan sesi susulan.

### Pengawasan campuran — batas dua pelanggaran

Percobaan ujian baru memakai aturan: kejadian pertama diperingatkan, kejadian kedua dikunci di server. Siswa tidak dapat menyimpan jawaban baru selama terkunci, termasuk melalui permintaan langsung atau refresh. Jawaban tersimpan tidak dihapus dan waktu tidak berhenti. Scheduler tetap mengumpulkan percobaan terkunci saat kedaluwarsa. Percobaan yang sudah ada sebelum migrasi mempertahankan kebijakan lama agar pembaruan tidak mendadak menghentikan ujian berjalan.

Mode Galak menghitung halaman tersembunyi, keluar fullscreen, kehilangan fokus akibat bubble/overlay, gestur bilah sistem dari tepi atas, perubahan viewport besar yang mengindikasikan split screen, tombol browser terlarang, dan percobaan navigasi kembali. Perubahan ukuran saat input/textarea aktif diabaikan agar keyboard layar tidak dianggap split screen; rotasi perangkat juga mempunyai masa toleransi. Satu perpindahan menonaktifkan sensor sampai siswa kembali mengonfirmasi; server menggabungkan sinyal dalam tiga detik dan mendeduplikasi kiriman berdasarkan ID kejadian. Permintaan gagal disimpan sementara di browser untuk dikirim ulang dengan ID sama. Jika sinkronisasi gagal, halaman ditahan sampai koneksi pulih tanpa menambah pelanggaran koneksi.

Fullscreen diminta lewat tombol persetujuan dan mendukung API standar maupun prefiks WebKit. Browser yang tidak mendukung fullscreen mendapat pemberitahuan dan tetap memakai pengawasan visibilitas/fokus. Pembatasan salin/tempel/menu konteks serta sensor browser tetap bukan penguncian sistem operasi; overlay tertentu, screenshot tombol fisik, dan perangkat kedua tidak dapat dipastikan oleh website. Tetap perlu pengawas.

Pengawas/panitia/super admin memeriksa lewat **Pelaksanaan ujian → Lihat peserta & progres → filter Terkunci**. Alasan wajib diisi untuk mengizinkan lanjut atau mengumpulkan jawaban. Izin lanjut tidak menghapus riwayat, jawaban, atau hitungan 2/2; kejadian berikutnya mengunci kembali. Formulir pemeriksaan lama ditolak jika versi kunci berubah. Ujian yang sudah dikumpulkan atau waktunya habis tidak bisa dibuka ulang.

Setelah memperbarui kode, jalankan `php artisan migrate --force`, `php artisan optimize:clear`, dan build aset. Jangan menjalankan `migrate:fresh` atau mereset database. Uji fullscreen dan gangguan koneksi pada HP/browser yang akan dipakai sekolah sebelum penerapan massal.

Fondasi domain, autentikasi berbasis peran, data akademik, penjadwalan, sesi susulan, absensi, bank soal pilihan ganda/isian/esai, koreksi manual, pengerjaan ujian siswa, monitoring keamanan, dan rapor ATS tersedia. Pengembangan lanjutan dapat mencakup paket soal acak, pengenalan wajah biometrik dengan persetujuan dan kebijakan privasi, serta importer terkontrol untuk data aplikasi lama.

Endpoint pemeriksaan aplikasi tersedia di `GET /health`.

## Pilihan Try Out per mapel dan kelas

Di **Bank Soal Uji Coba**, pilih kelas dan cari mapel, lalu klik satu pilihan, misalnya **Try Out Bahasa Indonesia 10**, **Try Out Bahasa Inggris 11**, atau **Try Out Matematika 12**. Satu klik hanya menambahkan pilihan tersebut: **20 soal PG A–E beserta kunci**, berurutan 6 mudah, 8 sedang, dan 6 sulit. Tidak ada impor massal atau penghapusan Sejarah XII otomatis melalui tombol pilihan ini.

Katalog siap pakai mencakup **27 mapel / 47 pilihan / 940 soal**, berdasarkan daftar sekolah dan 44 pasangan mapel–tingkat pada penjadwalan yang dibaca dari server baru pada 14 November 2026. Tiga mapel aktif yang belum memiliki penjadwalan (Akuntansi, Ilmu Pengetahuan Alam, dan Ilmu Pengetahuan Sosial) disediakan sebagai latihan dasar kelas X. Cakupan tersimpan di `database/data/try-out-subject-coverage.json`. Sejarah hanya tersedia untuk X–XI. Sebagian pertanyaan dasar dapat digunakan lintas tingkat; setiap bank hasil penambahan berdiri sendiri dan dapat disesuaikan guru sebelum digunakan.

Mapel meliputi Bahasa Indonesia, Bahasa Inggris, Bahasa Arab, Matematika, Sejarah, PAI, Pendidikan Pancasila, PJOK, Seni Rupa, Informatika, Koding dan Kecerdasan Artifisial, Proyek IPAS, IPA, IPS, Ekonomi Bisnis, Etika Profesi, Spreadsheet, Perbankan Dasar, Akuntansi, Akuntansi Dasar 1/2, Akuntansi Keuangan, Komputer Akuntansi, Administrasi Pajak, dua mapel Praktikum Akuntansi, serta Kreativitas, Inovasi dan Kewirausahaan. Tarif/skema hitungan pajak dan bunga yang bersifat simulasi dinyatakan sebagai asumsi pada soal; paket ini tidak menetapkan tarif transaksi nyata.

Mapel baru yang kelak ditambahkan di sekolah ikut ditampilkan sesuai penugasan; bila kontennya belum disiapkan, status **Soal belum tersedia** ditampilkan dan tombol tambah dinonaktifkan.

Konten asli, tingkat kesulitan, kunci, dan pembahasan ada di `database/data/trial-question-pack.json`. Pembahasan tidak dikirim ke pemilih paket atau halaman peserta. Guru/pengelola dapat menggunakan ekspor bank yang tersedia untuk memeriksa soal dan kunci sebelum ujian.

Klik ulang tidak menggandakan soal, termasuk setelah nama bank diubah. Bank yang sudah terisi atau dipakai tidak ditimpa. Bank paket lama dari impor sebelumnya tetap tersimpan; memilih Try Out baru membuat bank terpisah dengan 20 soal. Bank yang dihapus harus dipulihkan dari **Dihapus / pulihkan**, bukan dibuat ulang lewat tombol paket.

Sesudah deployment/migrasi, satu pilihan juga dapat ditambahkan melalui terminal:

```bash
php artisan trial-questions:sample --pack=bin-10
```

Impor massal hanya tersedia melalui perintah eksplisit `--all`, atau per tingkat melalui `--grade=10`, `--grade=11`, dan `--grade=12`. Opsi `--archive-history-12` pada mode massal/per tingkat masih tersedia untuk memindahkan bank Sejarah/Sejarah Indonesia XII ke Dihapus dan menonaktifkan tautan uji cobanya. Riwayat tetap disimpan dan penghapusan ditolak selama ada peserta berstatus sedang mengerjakan. `--pack` tidak dapat digabung dengan `--all` atau `--archive-history-12`.

Server sekolah saat ini: **https://ujiansmkbhg.sityoyitsolutions.com**. Atur `APP_URL` pada `.env` VPS sesuai domain tersebut agar tautan dan kartu mengarah ke server yang benar. Jangan memasukkan kata sandi atau konfigurasi rahasia ke repositori.

Acuan cakupan materi untuk tinjauan guru (soal ditulis ulang, bukan salinan): [Sejarah X: penelitian sejarah](https://repositori.kemendikdasmen.go.id/21720/), [Sejarah XI: Sumpah Pemuda](https://repositori.kemendikdasmen.go.id/21997/), [Matematika XII: statistika](https://repositori.kemendikdasmen.go.id/20973/), dan [Matematika XII: peluang](https://repositori.kemendikdasmen.go.id/21935/). Guru dapat menyesuaikan cakupan dengan materi yang sudah diajarkan sebelum ujian dimulai.

Acuan tambahan untuk pengecekan konsep: [DJP — fungsi pajak](https://www.pajak.go.id/id/fungsi-pajak), [JDIH Kemenkeu — SAP berbasis akrual](https://jdih.kemenkeu.go.id/kamus-hukum/sap-berbasis-akrual?id=218e89294fb322e94f14871eb983f2dc), serta [Microsoft — fungsi SUM](https://support.microsoft.com/en-us/excel/functions/sum-function) dan [fungsi statistik](https://support.microsoft.com/en-us/excel/statistical-functions-reference). Contoh transaksi, angka, dialog, dan kasus latihan disusun untuk paket ini.
