@extends('layouts.app')

@section('title', 'Soal Uji Coba')
@section('eyebrow', 'Bank Soal Uji Coba')
@section('heading', 'Kelola Soal Uji Coba')

@section('content')
    @if ($errors->any())<div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>@endif

    <section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.045] p-6">
        <a href="{{ route('trial-question-banks.index') }}" class="text-xs text-cyan-300">← Kembali ke bank soal uji coba</a>
        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">{{ $bank->subject->name }} · Kelas {{ $bank->grade_level }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $bank->name }}</h2>
        <p class="mt-2 text-sm text-slate-400">{{ $questionCount }} soal · Tidak tercampur dengan bank Ujian Inti</p>
        <p class="mt-2 text-xs text-cyan-200">Setiap jawaban PG benar bernilai tetap 2 poin. Nilai PG maksimal 100.</p>
        <div class="mt-4"><a href="{{ route('trial-question-banks.questions.export', $bank) }}" class="inline-flex rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-2.5 text-sm font-semibold text-emerald-200">Ekspor PG ke Excel</a></div>
        @if ($isLocked)<p class="mt-3 text-sm text-amber-300">Bank terkunci karena sudah ada peserta yang memulai uji coba.</p>@endif
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <h2 class="text-xl font-semibold text-white">Tambah soal PG</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500">Pilihan A sampai E. File soal dan setiap pilihan bersifat opsional; soal Bahasa Arab disarankan menggunakan gambar.</p>
            <fieldset @disabled($isLocked)>
                <form method="POST" action="{{ route('trial-question-banks.questions.store', $bank) }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                    @csrf
                    <textarea name="question_text" rows="5" required placeholder="Tuliskan pertanyaan..." class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">{{ old('question_text') }}</textarea>
                    <label class="block text-xs text-slate-400">File soal (opsional: JPG/PNG/WebP/PDF, maks. 5 MB)
                        <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </label>
                    @foreach (['A','B','C','D','E'] as $option)
                        <div class="grid grid-cols-[36px_1fr] items-start gap-2">
                            <span class="mt-1 grid size-9 place-items-center rounded-lg bg-slate-900 text-xs font-semibold text-cyan-300">{{ $option }}</span>
                            <div class="space-y-2">
                                <input name="option_{{ strtolower($option) }}" value="{{ old('option_'.strtolower($option)) }}" required placeholder="Pilihan {{ $option }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                                <input type="file" name="option_file_{{ strtolower($option) }}" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full rounded-lg border border-dashed border-white/10 bg-slate-950/50 px-3 py-2 text-xs text-slate-500">
                            </div>
                        </div>
                    @endforeach
                    <select name="correct_answer" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm"><option value="">Jawaban benar</option>@foreach (['A','B','C','D','E'] as $option)<option value="{{ $option }}" @selected(old('correct_answer') === $option)>Pilihan {{ $option }}</option>@endforeach</select>
                    <div class="rounded-xl border border-cyan-400/15 bg-cyan-400/[0.04] p-3 text-sm text-cyan-100">Bobot PG otomatis: <strong>2 poin</strong></div>
                    <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Simpan soal</button>
                </form>
            </fieldset>
        </section>

        <section class="space-y-3">
            @forelse ($questions as $question)
                <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold text-cyan-300">Soal {{ $question->position }} · 2 poin</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-white">{{ $question->question_text }}</p>
                            @if($question->question_image_path)
                                @php($questionFileIsImage = in_array(strtolower(pathinfo($question->question_image_path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp'], true))
                                @if($questionFileIsImage)<img src="{{ Storage::url($question->question_image_path) }}" alt="File soal" class="mt-4 max-h-80 rounded-xl border border-white/10 bg-white p-2">@else<a href="{{ Storage::url($question->question_image_path) }}" target="_blank" class="mt-4 inline-flex text-xs font-semibold text-cyan-300">Buka file soal</a>@endif
                            @endif
                        </div>
                        @unless ($isLocked)<div class="flex shrink-0 gap-3"><button type="button" data-edit-toggle="trial-edit-{{ $question->id }}" class="text-xs text-cyan-300">Edit</button><form method="POST" action="{{ route('trial-question-banks.questions.destroy', [$bank, $question]) }}" onsubmit="return confirm('Hapus soal ini?')">@csrf @method('DELETE')<button class="text-xs text-rose-300">Hapus</button></form></div>@endunless
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($question->options as $key => $option)
                            <div class="rounded-xl border px-3 py-2 text-xs {{ $key === $question->correct_answer ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-white/10 text-slate-400' }}"><strong>{{ $key }}.</strong> {{ $option }}
                                @if($question->option_file_paths[$key] ?? null)<a href="{{ Storage::url($question->option_file_paths[$key]) }}" target="_blank" class="mt-2 block font-semibold text-cyan-300">Buka lampiran {{ $key }}</a>@endif
                            </div>
                        @endforeach
                    </div>
                    @unless ($isLocked)
                        <form id="trial-edit-{{ $question->id }}" method="POST" action="{{ route('trial-question-banks.questions.update', [$bank, $question]) }}" enctype="multipart/form-data" class="mt-5 hidden space-y-3 border-t border-white/10 pt-5">
                            @csrf @method('PUT')
                            <textarea name="question_text" rows="4" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">{{ $question->question_text }}</textarea>
                            <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                            @foreach (['A','B','C','D','E'] as $option)
                                <div class="space-y-2 rounded-xl border border-white/10 p-3">
                                    <input name="option_{{ strtolower($option) }}" value="{{ $question->options[$option] ?? '' }}" required placeholder="Pilihan {{ $option }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                                    <input type="file" name="option_file_{{ strtolower($option) }}" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full text-xs text-slate-500">
                                    @if($question->option_file_paths[$option] ?? null)<a href="{{ Storage::url($question->option_file_paths[$option]) }}" target="_blank" class="text-xs text-cyan-300">Lihat lampiran {{ $option }}</a>@endif
                                </div>
                            @endforeach
                            <select name="correct_answer" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">@foreach (['A','B','C','D','E'] as $option)<option value="{{ $option }}" @selected($question->correct_answer === $option)>Jawaban {{ $option }}</option>@endforeach</select>
                            <div class="flex gap-2"><button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan perubahan</button><button type="button" data-edit-toggle="trial-edit-{{ $question->id }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm">Batal</button></div>
                        </form>
                    @endunless
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm text-slate-500">Belum ada soal dalam bank uji coba ini.</div>
            @endforelse
        </section>
    </div>
    @if ($questions->hasPages())<div class="mt-6 rounded-2xl border border-white/10 p-4"><p class="mb-3 text-xs text-slate-500">20 soal per halaman.</p>{{ $questions->links() }}</div>@endif
    <script>document.querySelectorAll('[data-edit-toggle]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.editToggle)?.classList.toggle('hidden')));</script>
@endsection
