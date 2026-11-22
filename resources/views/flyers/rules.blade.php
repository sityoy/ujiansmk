<section class="rules" aria-labelledby="rules-title">
    <h2 id="rules-title">Tata tertib peserta</h2>
    <div class="rule-grid">
        <div class="rule">
            <h3>Sebelum mulai</h3>
            <p>{{ $trial ? 'Siapkan NIS atau NISN milik sendiri dan kode uji coba.' : 'Siapkan kartu peserta dan masuk dengan akun siswa.' }} Periksa jadwal, mata pelajaran, dan baterai. Aktifkan <strong>Jangan Ganggu</strong> dan tutup aplikasi mengambang.</p>
            <p><strong>Cek koneksi internet dan sinyal di HP Anda sebelum mulai.</strong> Pastikan Wi-Fi atau data seluler stabil.</p>
            @if ($locationRequired)
                <p>Aktifkan lokasi dan izinkan akses GPS di {{ $trial ? 'lokasi simulasi' : 'lokasi ujian' }}. Pastikan absensi terverifikasi sebelum mulai.</p>
            @endif
        </div>
        <div class="rule">
            <h3>Saat mengerjakan</h3>
            <p>Pertahankan layar penuh jika didukung. Jangan berpindah tab atau aplikasi, menarik bilah notifikasi, memakai layar terbagi, mengunci layar, atau menekan Kembali.</p>
            <p>Jangan muat ulang, memperbesar halaman browser, menyalin soal, mengambil tangkapan layar, atau bekerja sama.</p>
        </div>
        <div class="rule">
            <h3>Gambar dan kendala perangkat</h3>
            <p>Ketuk gambar soal dan gunakan tombol <strong>Perbesar</strong> di penampil gambar. Tekan <strong>Tutup</strong> untuk kembali. Jika jawaban gagal tersimpan, pilih <strong>Coba simpan lagi</strong> atau hubungi pengawas.</p>
        </div>
        <div class="rule">
            <h3>Sebelum meninggalkan ujian</h3>
            <p>Jawab semua soal, periksa status simpan, lalu konfirmasi pengumpulan. Tunggu pesan ujian selesai. Jika waktu habis, sistem mengumpulkan jawaban yang sudah tersimpan.</p>
        </div>
    </div>
</section>
<section class="warning-note" aria-labelledby="warning-title">
    <h2 id="warning-title">Peringatan pelanggaran</h2>
    <p><strong>Pelanggaran pertama: peringatan. Kedua: ujian terkunci.</strong> Panggil pengawas jika ujian terkunci. Waktu ujian tetap berjalan saat terkunci.</p>
    <p>{{ $trial ? 'Reset pelanggaran hanya oleh petugas berwenang, maksimal '.$resetLimit.' kali per percobaan.' : 'Reset pelanggaran atau izin lanjut ditangani petugas berwenang, maksimal '.$resetLimit.' kali sesuai jatah sistem.' }}</p>
</section>
