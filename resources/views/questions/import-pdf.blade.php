@extends('layouts.app')

@section('title', 'Impor PDF')
@section('eyebrow', $trial ? 'Bank Soal Uji Coba' : 'Bank Soal Ujian Inti')
@section('heading', 'Impor soal dari PDF')

@section('content')
    <section class="mx-auto max-w-4xl space-y-5 rounded-2xl border border-white/10 p-5 sm:p-6">
        <a href="{{ $backUrl }}" class="text-sm font-semibold text-cyan-300">← Kembali ke bank soal</a>
        <h2 class="text-xl font-semibold text-slate-200">{{ $title }}</h2>
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-rose-400/30 p-4 text-sm text-rose-300">{{ $errors->first() }}</div>
        @endif
        @isset($questions)
            <p class="text-sm text-slate-300">Periksa {{ count($questions) }} soal di bawah. Sistem akan menambahkannya di akhir bank soal.</p>
            <ol class="space-y-3">
                @foreach ($questions as $number => $question)
                    <li class="space-y-2 rounded-xl border border-white/10 p-4 text-sm text-slate-200">
                        <p class="font-semibold text-cyan-300">Soal {{ $number }} · {{ \App\Enums\ExamQuestionType::from($question['question_type'])->label() }}</p>
                        @if ($question['statement'])
                            <p class="whitespace-pre-wrap break-words text-slate-400">{{ $question['statement'] }}</p>
                        @endif
                        <p class="whitespace-pre-wrap break-words">{{ $question['question_text'] }}</p>
                        @foreach ($question['options'] ?? [] as $letter => $choice)
                            <p class="whitespace-pre-wrap break-words">{{ $letter }}. {{ $choice }}</p>
                        @endforeach
                        <p class="text-xs text-slate-400">
                            @if ($question['correct_answer']) Kunci {{ $question['correct_answer'] }} · @endif
                            Bobot {{ $question['points'] }} poin
                        </p>
                    </li>
                @endforeach
            </ol>
            <form method="post" action="{{ $action }}" class="space-y-4">
                @csrf
                <input type="hidden" name="import_token" value="{{ $token }}">
                <label class="flex items-start gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="confirm_import" value="1" required class="mt-1">
                    <span>Saya sudah memeriksa isi dan kunci. Gambar perlu ditambahkan melalui Edit Soal setelah impor.</span>
                </label>
                <div class="flex flex-wrap items-center gap-4">
                    <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan {{ count($questions) }} soal</button>
                    <a href="{{ $action }}" class="text-sm font-semibold text-cyan-300">Unggah ulang</a>
                </div>
            </form>
        @else
            @php($allowsChoices = in_array(\App\Enums\ExamQuestionType::MultipleChoice, $types, true))
            <form method="post" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4 rounded-xl border border-white/10 p-4">
                @csrf
                <p class="text-sm text-slate-300">Gunakan PDF satu kolom dengan teks yang dapat disalin. Maksimal 5 MB per file, 80 halaman, dan 100 soal per impor. Gambar ditambahkan melalui Edit Soal setelah impor.</p>
                <label class="block text-sm text-slate-200">
                    PDF naskah soal
                    <input type="file" name="pdf" accept=".pdf,application/pdf" required class="mt-1 block w-full">
                </label>
                @if ($allowsChoices)
                    <label class="block text-sm text-slate-200">
                        PDF kunci jawaban (opsional)
                        <input type="file" name="key_pdf" accept=".pdf,application/pdf" class="mt-1 block w-full">
                    </label>
                    <p class="text-xs text-slate-400">Jika kunci terpisah, tulis satu kunci per baris, misalnya 1. C, dengan nomor yang sama seperti di naskah.</p>
                @endif
                <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tinjau soal</button>
            </form>
            <section class="space-y-3" aria-labelledby="format-impor">
                <h3 id="format-impor" class="font-semibold text-slate-200">Ketentuan &amp; Format Impor PDF</h3>
                <p class="text-sm text-slate-300">Salin contoh ke Word atau Google Docs, ganti isinya, lalu simpan sebagai PDF dengan nomor soal yang unik. Gunakan judul dan pilihan seperti contoh pada baris tersendiri.</p>
                @if ($allowsChoices)
                    <pre class="overflow-auto rounded-xl border border-white/10 p-4 text-sm text-slate-200">Soal 1 · Pilihan Ganda
Pernyataan: Perhatikan hasil penjumlahan berikut.
Pertanyaan: Berapakah 2 + 3?
A. 2
B. 3
C. 5
D. 6
E. 7
Kunci: C</pre>
                @endif
                <pre class="overflow-auto rounded-xl border border-white/10 p-4 text-sm text-slate-200">Soal {{ $allowsChoices ? '2' : '1' }} · Isian Singkat
Pertanyaan: Berapakah 20% dari 150?
Bobot: 3

Soal {{ $allowsChoices ? '3' : '2' }} · Esai
Pertanyaan: Jelaskan cara menghitung 20% dari 150.
Bobot: 7</pre>
            </section>
        @endisset
    </section>
@endsection
