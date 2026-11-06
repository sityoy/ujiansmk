# Kontrak Integrasi Identitas Sekolah dengan SIS.COM

## Tujuan

SIS.COM menjadi **control plane** untuk identitas dan status layanan sekolah. Sistem Ujian Sekolah menjadi **execution plane** untuk operasional ujian.

Identitas sekolah diatur satu kali pada SIS.COM lalu disinkronkan ke instalasi Sistem Ujian Sekolah. Salinan terakhir tetap disimpan lokal agar ujian dan tampilan identitas tidak berhenti ketika SIS.COM atau internet sedang tidak tersedia.

## Batas data

### Dikelola SIS.COM

- ID klien/sekolah
- nama sekolah
- NPSN
- alamat, kota, telepon, dan email
- nama kepala sekolah
- domain/URL instalasi
- paket dan modul yang aktif
- status langganan: `active`, `grace`, atau `expired`
- tanggal berlaku layanan
- waktu perubahan terakhir

### Tetap lokal di Sistem Ujian Sekolah

- tahun ajaran, kelas, siswa, guru, dan mata pelajaran
- bank soal dan jawaban
- jadwal ATS, AAS/AAT, UUB, reguler, dan susulan
- nilai, peringkat, rapor ATS, dan riwayat pengerjaan
- selfie, kartu pelajar, lokasi, absensi, serta insiden keamanan

Data siswa, jawaban, selfie, dan nilai tidak dikirim ke SIS.COM.

## Endpoint yang disiapkan pada SIS.COM

`GET /api/v1/cbt/tenant`

Header:

```http
Authorization: Bearer <installation-token>
Accept: application/json
```

Contoh respons:

```json
{
  "data": {
    "client_id": 123,
    "school": {
      "name": "SMKS Islam Bahagia",
      "npsn": "12345678",
      "address": "Alamat sekolah",
      "city": "Kabupaten/Kota",
      "phone": "021000000",
      "email": "sekolah@example.sch.id",
      "principal_name": "Nama Kepala Sekolah",
      "domain": "ujian.example.sch.id"
    },
    "subscription": {
      "status": "active",
      "valid_until": "2027-06-30",
      "modules": ["exam", "attendance", "midterm_report"]
    },
    "updated_at": "2026-08-24T08:00:00+07:00"
  }
}
```

## Aturan sinkronisasi

1. Sinkronisasi otomatis dijalankan terjadwal dan dapat dijalankan manual oleh Super Admin.
2. Respons valid disimpan sebagai cache lokal beserta `client_id` dan `synced_at`.
3. Kegagalan koneksi tidak menghapus identitas lokal yang terakhir valid.
4. Perubahan lokal pada kolom yang dikelola SIS.COM akan dikunci setelah integrasi diaktifkan.
5. Status `grace` tetap mengizinkan ujian yang telah dijadwalkan.
6. Status `expired` tidak boleh memutus ujian yang sedang berlangsung; pembatasan diterapkan pada pembuatan jadwal baru.
7. Setiap sinkronisasi dicatat pada audit log tanpa menyimpan token.

## Keamanan

- Setiap instalasi memiliki token berbeda dan token disimpan terenkripsi.
- SIS.COM hanya mengembalikan data milik klien pemilik token.
- Token dapat dicabut dan diputar ulang.
- Seluruh komunikasi produksi wajib menggunakan HTTPS.
- Endpoint diberi rate limit dan audit log.
- Repo dan konfigurasi produksi tidak boleh memuat token asli.

## Tahap implementasi

1. Aktifkan Data Akademik dan Manajemen Akun pada Sistem Ujian Sekolah.
2. Tambahkan endpoint tenant dan pengelolaan token instalasi pada SIS.COM.
3. Tambahkan tabel status sinkronisasi dan perintah sinkronisasi pada Sistem Ujian Sekolah.
4. Uji mode offline, status langganan, rotasi token, dan audit log.
5. Setelah stabil, jadikan identitas SIS.COM sebagai sumber utama.
