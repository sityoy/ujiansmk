# Arsitektur GALAK CBT

## Prinsip data

1. `AssessmentPeriod` mewakili satu periode seperti ATS Ganjil atau UUB 2.
2. `AssessmentSubject` mengikat periode, mata pelajaran, dan kelas.
3. `ExamSession` menentukan pelaksanaan reguler atau susulan.
4. `ExamAssignment` mengikat satu siswa ke satu komponen asesmen dan hanya boleh ada satu record.
5. `ExamAttempt` menyimpan proses dan hasil ujian untuk penugasan tersebut.
6. `DailyCheckin` menjadi bukti kehadiran harian yang dapat dipakai beberapa sesi ujian.

## Alur ujian reguler

1. Panitia menerbitkan periode dan jadwal.
2. Siswa dipetakan ke komponen asesmen.
3. Siswa melakukan check-in kartu, selfie, dan lokasi.
4. Sistem memulai satu attempt dari assignment yang sah.
5. Jawaban disimpan berkala dan dikirim final ketika waktu habis atau siswa menekan selesai.

## Alur ujian susulan

1. Pengawas menandai assignment sebagai tidak hadir.
2. Panitia membuat sesi dengan jenis `makeup` dan menunjuk sesi reguler sebagai sumber.
3. Assignment lama dipindahkan ke sesi susulan dalam transaksi database.
4. ID assignment tetap sama sehingga nilai reguler dan susulan tidak terduplikasi.

## Absensi dan lokasi

Koordinat kampus, radius, dan ambang akurasi disimpan sebagai konfigurasi database. Jarak dihitung di server menggunakan rumus Haversine. Geolokasi tidak menjadi satu-satunya bukti; kartu, selfie, sesi perangkat, dan keputusan pengawas tetap digunakan.

Foto selfie harus disimpan pada disk privat. Antarmuka admin hanya boleh mengaksesnya melalui URL sementara dan sesuai kewenangan.

## Batas tahap pertama

Branch ini belum memuat halaman admin, bank soal, lembar ujian, pengenalan wajah otomatis, atau importer database lama. Fitur tersebut ditambahkan per modul setelah migrasi fondasi lulus pengujian.
