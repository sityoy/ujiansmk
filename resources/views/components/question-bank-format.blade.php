@props(['types', 'trial' => false])

@php($allowsChoices = in_array(\App\Enums\ExamQuestionType::MultipleChoice, $types, true))
<details class="mt-4 rounded-xl border border-white/10 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-cyan-300">Ketentuan &amp; Format Soal</summary>
    <dl class="mt-3 space-y-3 text-sm leading-6 text-slate-400">
        <div>
            <dt class="font-semibold text-slate-200">Jenis soal yang tersedia</dt>
            <dd>{{ collect($types)->map(fn ($type) => $type->label())->join(', ', ' dan ') }}.</dd>
        </div>
        <div>
            <dt class="font-semibold text-slate-200">Urutan penulisan</dt>
            <dd>Isi pertanyaan untuk setiap soal. Untuk satu bacaan atau gambar yang dipakai beberapa nomor, buat pernyataan bersama pada soal pertama lalu pilih kelompok yang sama pada soal selanjutnya.</dd>
        </div>
        @if ($allowsChoices)
            <div>
                <dt class="font-semibold text-slate-200">Pilihan ganda</dt>
                <dd>Isi pilihan A sampai E dan centang semua kunci yang benar. Jawaban benar bernilai 2 poin, dengan nilai PG maksimal 100. Urutan pilihan di ujian daring dapat berbeda dari naskah PDF.</dd>
            </div>
        @endif
        <div>
            <dt class="font-semibold text-slate-200">Isian singkat dan esai</dt>
            <dd>Isi bobot maksimal setiap soal. Guru mengoreksi jawabannya secara manual.</dd>
        </div>
        <div>
            <dt class="font-semibold text-slate-200">Lampiran</dt>
            <dd>Gunakan JPG, JPEG, PNG, WEBP, atau PDF maksimal 5 MB per file. Gambar ikut tampil di naskah. Lampiran PDF dicantumkan sebagai tautan.</dd>
        </div>
        <div>
            <dt class="font-semibold text-slate-200">Format ekspor</dt>
            <dd>Naskah A4 memuat seluruh soal dalam bank sesuai urutannya. Gunakan versi Naskah Peserta untuk dibagikan dan versi dengan kunci untuk pegangan guru.</dd>
        </div>
    </dl>
</details>
