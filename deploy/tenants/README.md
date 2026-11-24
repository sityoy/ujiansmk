# CBT per sekolah

CBT baru menggunakan satu rilis Laravel tersendiri di /var/www/cbt-sekolah. Database MySQL, pengguna SQL beserta kata sandinya, APP_KEY, cookie, sesi, cache, unggahan, dan direktori penyimpanan berbeda untuk setiap NPSN. Nginx memberikan NPSN dari nama host ke bootstrap khusus tenant. Instance CBT lama tetap menggunakan entry point dan db_ujiansis yang sudah ada.

## Persiapan VPS satu kali

1. Deploy migrasi portal dan perubahan SISCOM. Keduanya harus menggunakan instance MySQL yang sama dengan provisioner. Setujui paket melalui **Pendaftaran Sekolah**.
2. Siapkan checkout CBT yang telah ditinjau di **/var/www/cbt-sekolah**, terpisah dari checkout ujian lama. Dari checkout baru jalankan sebagai akun deploy:

   ~~~bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   npm ci
   npm run build
   ~~~

   Gunakan PHP CLI dan FPM 8.4.1+ dengan PDO MySQL. Web worker www-data memerlukan akses baca ke checkout. Bootstrap Laravel dan build Vite harus tersedia. FPM membaca konfigurasi tenant di luar web root. Checkout ini tidak membutuhkan salinan .env produksi CBT lama.
3. Arahkan nama-nama **cbt-<npsn>.sityoyitsolutions.com** ke VPS. DNS wildcard untuk *.sityoyitsolutions.com dapat melayani sekolah berikutnya secara otomatis. Siapkan sertifikat yang mencakup nama-nama tersebut. Sediakan /etc/sis-cbt/fullchain.pem dan /etc/sis-cbt/privkey.pem dengan pembaruan sertifikat yang terkelola.
4. Buat /etc/sis-cbt/provision.ini dengan mode 0600 milik root. Isi socket dan NPSN terverifikasi dari sekolah pengguna db_ujiansis. Gunakan NONE hanya jika layanan lama memang tidak akan dihubungkan. Jangan menebak NPSN. Contoh struktur:

   ~~~ini
   socket=/run/mysqld/mysqld.sock
   user=root
   legacy_npsn=ISI_NPSN_TERVERIFIKASI
   ~~~

   Pada Ubuntu dengan autentikasi socket, timer lokal dapat mengakses MySQL sebagai root. Jika memakai akun DBA dengan kata sandi, tambahkan password pada file ini. Kredensial provisioner hanya dibaca oleh proses CLI, di luar aplikasi web.
5. Aktifkan setelah konfigurasi dan DNS/TLS diverifikasi. Skrip menggunakan socket FPM /run/php/php8.4-fpm.sock dan akun www-data.

   ~~~bash
   sudo bash deploy/tenants/install.sh
   sudo systemctl start sis-cbt-provision.service
   sudo journalctl -u sis-cbt-provision.service -n 30
   ~~~

Timer memeriksa sekolah setiap menit. Pendaftaran publik menyimpan permintaan paket. Persetujuan dengan modul CBT, NPSN, dan masa aktif memulai penyiapan. Kegagalan migrasi dapat dicoba kembali menggunakan identitas dan database yang sama. Provisioner memberi akses web setelah migrasi berhasil. Untuk sekolah lama yang NPSN-nya cocok dengan pemetaan operator, portal membuka alamat CBT lama.

Kepala Sekolah membuka portal dan memilih **Siapkan admin pertama CBT**. Tautan berlaku sepuluh menit dan aktivasi hanya bisa diselesaikan sekali. Panitia dan siswa kemudian bisa login langsung pada alamat CBT sekolah. Untuk sekolah baru, penangguhan diproses saat sinkronisasi berikutnya dan tanggal akhir masa sewa juga diperiksa langsung saat mengakses Laravel. Ujian berjalan tanpa query ke portal pada setiap permintaan. Penangguhan tidak menghapus database atau berkas.

## Pemeliharaan

Cadangkan database setiap sekolah bersama /var/lib/sis-cbt (termasuk kunci enkripsi, identitas dan berkas) dan direktori portal. Hentikan timer saat melakukan pemulihan lintas database. Simpan cadangan terenkripsi. Layanan lama dikelola melalui prosedur CBT lama.

Untuk rilis yang menambah migrasi, jadwalkan pemeliharaan saat tidak ada ujian aktif. Perbarui kode dan build di checkout tenant, lalu jalankan skrip upgrade di bawah sebagai root untuk memigrasikan database sekolah yang telah disiapkan. Skrip menghentikan proses jika migrasi satu sekolah gagal dan bisa dijalankan kembali.

    sudo bash deploy/tenants/upgrade.sh
