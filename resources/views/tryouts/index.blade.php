@extends('layouts.app')

@section('title', 'Ujian Uji Coba')
@section('eyebrow', 'Simulasi berbasis kode')
@section('heading', 'Ujian Uji Coba')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
        <form method="POST" action="{{ route('tryouts.store') }}" class="space-y-4 rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            @csrf
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Buat tautan baru</p>
                <h2 class="mt-2 text-xl font-semibold">Ujian tanpa akun siswa</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Peserta membuka tautan khusus, mengisi nama dan kode bersama, lalu mengerjakan soal PG.</p>
            </div>
            <label class="block text-sm text-slate-300">Bank soal/mapel
                <select name="assessment_subject_id" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <option value="">Pilih bank soal</option>
                    @foreach ($components as $component)
                        <option value="{{ $component->id }}" @selected(old('assessment_subject_id') == $component->id)>
                            {{ $component->subject->name }} · {{ $component->schoolClass->name }} · {{ $component->assessmentPeriod->name }} ({{ $component->pg_questions_count }} PG)
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm text-slate-300">Judul
                <input name="title" value="{{ old('title') }}" required placeholder="Uji Coba Sejarah" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            </label>
            <label class="block text-sm text-slate-300">Kode bersama
                <input name="access_code" value="{{ old('access_code') }}" required placeholder="SJR-SMKBHG" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase">
                <span class="mt-1 block text-xs text-slate-500">Huruf, angka, dan tanda hubung.</span>
            </label>
            <label class="block text-sm text-slate-300">Durasi (menit)
                <input type="number" name="duration_minutes" min="10" max="300" value="{{ old('duration_minutes', 60) }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm text-slate-300">Mulai (opsional)<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3 text-xs"></label>
                <label class="block text-sm text-slate-300">Selesai (opsional)<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3 text-xs"></label>
            </div>
            <label class="flex items-center gap-3 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" @checked(old('is_active')) class="size-4 rounded border-white/20 bg-slate-950">Langsung aktifkan</label>
            <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Buat ujian uji coba</button>
        </form>

        <section class="space-y-4">
            @forelse ($trialExams as $trial)
                <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-white">{{ $trial->title }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $trial->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-400/10 text-slate-400' }}">{{ $trial->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-400">{{ $trial->assessmentSubject->subject->name }} · {{ $trial->assessmentSubject->schoolClass->name }} · {{ $trial->assessmentSubject->assessmentPeriod->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $trial->duration_minutes }} menit · {{ $trial->attempts_count }} peserta · Rata-rata {{ is_null($trial->attempts_avg_score) ? '—' : number_format($trial->attempts_avg_score, 2, ',', '.') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('tryouts.toggle', $trial) }}">@csrf @method('PATCH')<button class="rounded-xl border border-white/10 px-3 py-2 text-xs text-cyan-200">{{ $trial->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                            <form method="POST" action="{{ route('tryouts.destroy', $trial) }}" onsubmit="return confirm('Hapus ujian uji coba ini?')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-400/20 px-3 py-2 text-xs text-rose-300">Hapus</button></form>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto]">
                        <div class="rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-slate-300">
                            <span class="text-slate-500">Tautan:</span> <a target="_blank" href="{{ route('tryout.entry', $trial) }}" class="break-all text-cyan-300">{{ route('tryout.entry', $trial) }}</a>
                        </div>
                        <div class="rounded-xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-sm"><span class="text-amber-200">Kode:</span> <strong class="ml-2 tracking-wider text-white">{{ $trial->access_code }}</strong></div>
                    </div>
                    @if($trial->starts_at || $trial->ends_at)<p class="mt-3 text-xs text-slate-500">Jadwal: {{ $trial->starts_at?->format('d/m/Y H:i') ?? 'sekarang' }} – {{ $trial->ends_at?->format('d/m/Y H:i') ?? 'tanpa batas' }}</p>@endif
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-500">Belum ada ujian uji coba.</div>
            @endforelse
            {{ $trialExams->links() }}
        </section>
    </div>
@endsection
