@extends('layouts.app')

@section('title', 'Bank Soal Ujian Inti')
@section('eyebrow', 'Guru & Asesmen')
@section('heading', 'Bank Soal Ujian Inti')

@section('content')
    @php
        $selectedPeriod = $periods->firstWhere('id', $periodId);
        $canManageDeadline = auth()->user()->role !== \App\Enums\UserRole::Teacher;
    @endphp
    <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Ruang Kerja Guru</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Bank soal per guru dan tingkat</h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-400">
            Guru hanya dapat membuka komponen yang ditugaskan kepadanya oleh Panitia/Super Admin.
            Rombel setingkat berbagi bank soal jika guru, mapel, dan periodenya sama. Rombel dengan guru berbeda punya bank masing-masing. ATS tetap khusus isian singkat dan esai.
        </p>
    </section>

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Tampilan bank soal inti">
        @foreach (['banks' => 'Daftar Bank Soal', 'summary' => 'Rekap Pembuatan Soal'] as $view => $label)
            <a href="{{ route('question-bank.index', array_merge(request()->except('page'), ['view' => $view])) }}"
                @if ($catalogView === $view) aria-current="page" @endif
                class="rounded-xl border border-white/10 px-4 py-3 text-sm {{ $catalogView === $view ? 'bg-violet-400 font-semibold text-slate-950' : 'text-slate-300' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach (['banks' => 'Total bank soal', 'filled' => 'Sudah ada soal', 'empty' => 'Belum ada soal', 'questions' => 'Jumlah soal'] as $key => $label)
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                <p class="text-xs text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-white">{{ $summary[$key] }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs leading-6 text-slate-400">Ringkasan mengikuti periode, tingkat, dan pencarian. Bank bersama antarrombel dihitung satu kali. Nama guru mengikuti penugasan di Penjadwalan.</p>

    <form method="GET" action="{{ route('question-bank.index') }}" class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-4 md:grid-cols-2 xl:grid-cols-3">
        <input type="hidden" name="view" value="{{ $catalogView }}">
        <select name="period_id" aria-label="Periode asesmen" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
            <option value="">Semua periode</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected($periodId === $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
            @endforeach
        </select>
        <select name="grade_level" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
            <option value="">Semua tingkat</option>
            @foreach ($gradeLevels as $level)
                <option value="{{ $level }}" @selected($gradeLevel === $level)>Kelas {{ $level }}</option>
            @endforeach
        </select>
        <input name="q" value="{{ $search }}" placeholder="Cari mapel, kode, atau guru"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm outline-none focus:border-violet-400">
        <select name="question_status" aria-label="Status pembuatan soal" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
            <option value="">Semua status soal</option>
            <option value="filled" @selected($questionStatus === 'filled')>Sudah ada soal</option>
            <option value="empty" @selected($questionStatus === 'empty')>Belum ada soal</option>
        </select>
        <button class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Tampilkan</button>
        <a href="{{ route('question-bank.index', ['view' => $catalogView]) }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-center text-sm text-slate-300">Reset</a>
    </form>

    @if ($selectedPeriod)
        <section id="batas-pembuatan-soal" aria-labelledby="question-deadline-title" class="mt-4 rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:p-5">
            <h2 id="question-deadline-title" class="font-semibold text-white">Batas Pembuatan Soal</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $selectedPeriod->name }} · {{ $selectedPeriod->academicYear->name }}</p>
            @if ($selectedPeriod->core_question_deadline_at)
                <x-core-question-deadline :period="$selectedPeriod" class="mt-2 text-sm" />
            @else
                <p class="mt-2 text-sm text-slate-400">Belum ada batas waktu pembuatan soal untuk periode ini.</p>
            @endif
            @if ($canManageDeadline)
                <form method="POST" action="{{ route('question-bank.deadline.update', $selectedPeriod) }}" class="mt-4">
                    @csrf @method('PATCH')
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="block text-sm text-slate-300" for="core-question-deadline">Tanggal dan jam penutupan (WIB)
                            <input id="core-question-deadline" type="datetime-local" name="core_question_deadline_at" step="60"
                                value="{{ old('core_question_deadline_at', $selectedPeriod->core_question_deadline_at?->timezone(\App\Models\AssessmentPeriod::CORE_QUESTION_TIMEZONE)->format('Y-m-d\TH:i')) }}"
                                aria-describedby="core-question-deadline-help"
                                class="mt-2 block w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm">
                        </label>
                        <button type="submit" class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan batas</button>
                    </div>
                    @error('core_question_deadline_at', 'questionDeadline')
                        <p role="alert" class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    <p id="core-question-deadline-help" class="mt-2 text-xs leading-6 text-slate-400">Tambah, edit, dan hapus soal ditutup pada jam ini. Kosongkan lalu simpan untuk menghapus batas waktu.</p>
                </form>
            @endif
        </section>
    @elseif ($canManageDeadline && $periods->isNotEmpty())
        <p class="mt-3 text-sm text-slate-400">Pilih satu periode lalu klik <strong>Tampilkan</strong> untuk mengatur batas pembuatan soal.</p>
    @endif

    @if ($catalogView === 'summary')
        <section class="mt-6 rounded-3xl border border-white/10 bg-white/[0.035] p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-white">Rekap Pembuatan Soal</h2>
            <p class="mt-2 text-xs leading-6 text-slate-400">Jumlah soal per mapel, tingkat, periode, dan guru penanggung jawab. Klik Lihat soal untuk membuka bank terkait.</p>
            <details class="mt-4 rounded-xl border border-white/10 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-violet-300">Teks untuk WhatsApp</summary>
                <p class="mt-2 text-xs leading-6 text-slate-400">Kedua teks mengikuti periode, tingkat, dan pencarian. Salin teks yang diperlukan, lalu tempel ke WhatsApp.</p>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    @foreach ($shareMessages as $status => $share)
                        <section data-question-share class="space-y-2">
                            <label for="question-share-{{ $status }}" class="block text-sm font-semibold text-white">{{ $share['label'] }}</label>
                            <textarea id="question-share-{{ $status }}" readonly rows="7" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-200">{{ $share['text'] }}</textarea>
                            <button type="button" data-copy-share class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-violet-300">Salin teks</button>
                            <p data-copy-result role="status" class="text-xs text-slate-400"></p>
                        </section>
                    @endforeach
                </div>
            </details>
            <script>
                document.querySelectorAll('[data-question-share]').forEach(group => {
                    const text = group.querySelector('textarea');
                    const result = group.querySelector('[data-copy-result]');
                    group.querySelector('[data-copy-share]').addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(text.value);
                            result.textContent = 'Teks disalin. Tempel ke WhatsApp.';
                        } catch {
                            text.focus();
                            text.select();
                            result.textContent = 'Teks dipilih. Gunakan menu Salin atau tekan Ctrl+C.';
                        }
                    });
                });
            </script>
            <form method="GET" action="{{ route('question-bank.recap') }}" target="_blank" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-white/10 p-4">
                <input type="hidden" name="period_id" value="{{ $periodId }}">
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="question_status" value="{{ $questionStatus }}">
                <label class="text-xs text-slate-400">Tingkat yang diunduh
                    <select name="grade_level" class="mt-2 block rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                        <option value="">Semua tingkat</option>
                        @foreach ($gradeLevels as $level)
                            <option value="{{ $level }}" @selected($gradeLevel === $level)>Kelas {{ $level }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Unduh rekap PDF / PNG</button>
                <p class="w-full text-xs leading-6 text-slate-400">Memuat seluruh hasil sesuai periode, pencarian, dan status di atas. Jumlah data per halaman tidak membatasi unduhan.</p>
            </form>
            <div class="mt-4 overflow-x-auto">
                <table class="data-grid w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-xs text-slate-400"><tr>
                        <th scope="col" class="px-3 py-3">Guru penanggung jawab</th><th scope="col" class="px-3 py-3">Mapel / Periode</th><th scope="col" class="px-3 py-3">Kelas / Rombel</th>
                        <th scope="col" class="px-3 py-3 text-center">PG</th><th scope="col" class="px-3 py-3 text-center">Esai</th><th scope="col" class="px-3 py-3 text-center">Isian singkat</th>
                        <th scope="col" class="px-3 py-3 text-center">Total soal</th><th scope="col" class="px-3 py-3">Status / Aksi</th>
                    </tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($components as $component)
                            <tr>
                                <td class="px-3 py-4 text-slate-200">{{ $component->teacher?->name ?? 'Belum ditetapkan' }}</td>
                                <td class="px-3 py-4"><p class="font-semibold text-white">{{ $component->subject->name }}</p><p class="mt-1 text-xs text-slate-400">{{ $component->assessmentPeriod->name }} · {{ $component->assessmentPeriod->academicYear->name }}</p>
                                    @unless ($selectedPeriod)
                                        @php($bankPeriod = $component->assessmentPeriod)
                                        <x-core-question-deadline :period="$bankPeriod" class="mt-1 text-xs" />
                                    @endunless
                                </td>
                                <td class="px-3 py-4 text-slate-300">Kelas {{ $component->grade_level }}<p class="mt-1 text-xs text-slate-400">{{ $component->rombel_names }}</p></td>
                                <td class="px-3 py-4 text-center">{{ $component->pg_count }}</td><td class="px-3 py-4 text-center">{{ $component->essay_count }}</td><td class="px-3 py-4 text-center">{{ $component->short_answer_count }}</td>
                                <td class="px-3 py-4 text-center font-semibold text-white">{{ $component->questions_count }}</td>
                                <td class="px-3 py-4"><p class="text-xs {{ $component->questions_count > 0 ? 'text-emerald-300' : 'text-amber-300' }}">{{ $component->questions_count > 0 ? 'Sudah ada soal' : 'Belum ada soal' }}</p><a href="{{ route('scheduling.questions.index', $component) }}" class="mt-2 inline-block whitespace-nowrap text-xs font-semibold text-violet-300">Lihat soal →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-10 text-center text-slate-400">Tidak ada bank soal sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($components as $component)
                <article class="flex flex-col rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-full bg-violet-400/10 px-3 py-1 text-xs font-medium text-violet-200">{{ $component->assessmentPeriod->type->label() }}</span>
                        <span class="text-xs text-slate-500">{{ $component->questions_count }} soal</span>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-white">{{ $component->subject->name }}</h3>
                    <p class="mt-1 text-sm text-slate-400">Kelas {{ $component->grade_level }} · {{ $component->assessmentPeriod->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $component->assessmentPeriod->academicYear->name }} · Total bobot {{ number_format((float) ($component->questions_sum_points ?? 0), 2, ',', '.') }}</p>
                    <p class="mt-2 text-xs text-slate-400">Rombel {{ $component->rombel_names }}</p>
                    @unless ($selectedPeriod)
                        @php($bankPeriod = $component->assessmentPeriod)
                        <x-core-question-deadline :period="$bankPeriod" class="mt-2 text-xs" />
                    @endunless
                    @if (auth()->user()->role->value !== 'teacher')
                        <p class="mt-3 text-xs text-slate-500">Guru: {{ $component->teacher?->name ?? 'belum ditetapkan' }}</p>
                    @endif
                    <a href="{{ route('scheduling.questions.index', $component) }}" class="mt-5 inline-flex justify-center rounded-xl bg-violet-400 px-4 py-2.5 text-sm font-semibold text-slate-950 xl:mt-auto">Kelola soal</a>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-12 text-center text-sm leading-6 text-slate-500 md:col-span-2 xl:col-span-3">
                    Belum ada mapel dan kelas yang ditugaskan untuk akun ini. Panitia atau Super Admin perlu memilih guru pengoreksi pada Penjadwalan terlebih dahulu.
                </div>
            @endforelse
        </div>

    @endif

        <div class="mt-5 rounded-2xl border border-white/10 bg-white/[0.025] p-4">{{ $components->links() }}</div>

@endsection

