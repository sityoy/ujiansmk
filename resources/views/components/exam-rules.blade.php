@props(['core' => false, 'locationRequired' => false])

@php($headingId = $core ? 'core-exam-rules-title' : 'trial-exam-rules-title')
@php($attendanceHeadingId = $core ? 'core-exam-location-rules' : 'trial-exam-location-rules')

<section aria-labelledby="{{ $headingId }}" {{ $attributes->class('rounded-2xl border border-amber-400/25 bg-amber-400/[0.06] p-4 sm:p-6') }}>
    <h3 id="{{ $headingId }}" class="scroll-mt-24 text-lg font-semibold text-amber-200">Peringatan sebelum memulai ujian</h3>
    <p class="mt-2 text-sm leading-6 text-slate-300">Baca semua aturan sebelum mulai. Waktu dihitung setelah sistem menerima permintaan <strong>Mulai ujian</strong>. Waktu tetap berjalan saat panel pengawasan terbuka.</p>

    @if ($core || $locationRequired)
        <div class="mt-5 rounded-xl border border-amber-400/25 p-4 text-sm leading-6 text-slate-300">
            <h4 id="{{ $attendanceHeadingId }}" class="scroll-mt-24 font-semibold text-amber-200">Absensi lokasi sebelum ujian</h4>
            <p class="mt-2">Selesaikan absensi lokasi hingga terverifikasi sebelum mulai. Waktu ujian belum berjalan saat Anda absen.</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Pastikan Anda berada di lokasi yang ditetapkan panitia. Aktifkan layanan lokasi perangkat dan gunakan lokasi presisi jika tersedia.</li>
                @unless ($core)
                    <li>Isi NIS atau NISN jika diminta, lalu masukkan kode ujian dari panitia.</li>
                @endunless
                <li>Tekan <strong>{{ $core ? 'Ambil lokasi & kirim absensi' : 'Ambil Lokasi & Absen Simulasi' }}</strong>. Saat browser meminta izin lokasi, pilih <strong>Izinkan</strong> dan tunggu hasil verifikasi.</li>
                <li>Setelah absensi terverifikasi, mulai ujian dari halaman dan perangkat yang sama saat jadwal tersedia.</li>
            </ol>
            <h5 class="mt-3 font-semibold text-white">Jika absensi gagal</h5>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li><strong>Izin ditolak.</strong> Buka pengaturan izin situs di browser, izinkan lokasi, lalu tekan tombol absensi lagi.</li>
                <li><strong>Posisi belum terbaca atau kurang akurat.</strong> Pastikan lokasi presisi aktif, atau coba dari dekat jendela atas arahan pengawas.</li>
                <li><strong>Di luar radius.</strong> Pastikan Anda berada di lokasi ujian yang benar. Jika sudah di lokasi dan tetap gagal, panggil pengawas sebelum mulai.</li>
            </ul>
        </div>
    @endif

    <div class="mt-5 grid gap-5 text-sm leading-6 text-slate-300 md:grid-cols-2">
        <div>
            <h4 class="font-semibold text-white">Siapkan perangkat</h4>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Periksa kelas dan mata pelajaran. Gunakan satu perangkat dengan baterai cukup dan internet stabil.</li>
                @unless ($core)
                    <li>Siapkan NIS atau NISN milik sendiri dan kode ujian dari panitia.</li>
                @endunless
                <li>Aktifkan <strong>Jangan Ganggu</strong> jika tersedia. Tutup aplikasi lain, gelembung chat, dan jendela mengambang. Atur ukuran tampilan dan posisi layar sebelum mulai.</li>
            </ul>
            <details class="mt-2">
                <summary class="cursor-pointer font-medium text-cyan-200">Cara mengaktifkan Jangan Ganggu di HP</summary>
                <p class="mt-1"><strong>Android</strong> — cari Jangan Ganggu di Setelan atau panel cepat sebelum ujian dimulai.</p>
                <p class="mt-1"><strong>iPhone</strong> — buka Pengaturan → Fokus → Jangan Ganggu.</p>
            </details>
        </div>

        <div>
            <h4 class="font-semibold text-white">Tetap di halaman ujian</h4>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Izinkan dan pertahankan <strong>layar penuh</strong> jika didukung. Jangan berpindah tab atau aplikasi, memakai layar terbagi, mengunci layar, atau menekan tombol Kembali dan Home.</li>
                <li>Jangan tarik bilah notifikasi atau panel pengaturan cepat. Gulir soal dengan satu jari dari area isi halaman. Jangan mencubit layar atau memakai zoom browser.</li>
                <li>Jangan muat ulang halaman, ambil tangkapan layar, rekam layar, cetak, salin, atau tempel isi soal dan jawaban. Gunakan alat bantu hanya jika diizinkan pengawas.</li>
                <li>Ketuk gambar soal, lalu gunakan <strong>Perbesar</strong> atau <strong>Perkecil</strong> di penampil gambar. Geser gambar dengan satu jari untuk melihat bagian lain, lalu pilih <strong>Tutup</strong> untuk kembali ke soal.</li>
            </ul>
        </div>

        <div>
            <h4 class="font-semibold text-white">Jika ada peringatan atau gangguan</h4>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><strong>Pelanggaran pertama</strong> memunculkan peringatan. Baca petunjuknya, lalu gunakan tombol yang tersedia untuk kembali ke ujian.</li>
                <li><strong>Pelanggaran kedua</strong> mengunci ujian. Segera panggil pengawas. Waktu tetap berjalan.</li>
                <li>Jika jawaban gagal disimpan, pilih <strong>Coba simpan lagi</strong> saat tombolnya muncul. Hubungi pengawas jika masalah berlanjut. Biarkan halaman ujian tetap terbuka.</li>
            </ul>
        </div>

        <div>
            <h4 class="font-semibold text-white">Kumpulkan jawaban</h4>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Periksa jawaban dan status penyimpanan. Tekan <strong>{{ $core ? 'Selesai dan kumpulkan jawaban' : 'Kumpulkan jawaban' }}</strong>, lalu konfirmasi.</li>
                <li>Tunggu pesan bahwa ujian selesai sebelum menutup halaman. Jika waktu habis, sistem mengumpulkan jawaban yang sudah tersimpan.</li>
            </ul>
        </div>
    </div>
</section>
