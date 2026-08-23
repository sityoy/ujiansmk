@extends('layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', 'Pusat Kendali')
@section('heading', 'Dashboard GALAK CBT')

@section('content')
    <section class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-400/15 via-slate-900 to-blue-500/10 p-6 md:p-8">
        <div class="max-w-3xl">
            <span class="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-medium text-cyan-200">
                {{ $user->role->label() }}
            </span>
            <h2 class="mt-5 text-2xl font-semibold text-white md:text-3xl">Selamat datang, {{ $user->name }}.</h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                Fondasi sistem sudah siap. Data ujian, sesi susulan, absensi harian, dan kontrol keamanan akan dikelola dari pusat kendali ini.
            </p>
        </div>
    </section>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['label' => 'Siswa aktif', 'value' => $statistics['students'], 'accent' => 'text-cyan-300', 'note' => 'Peserta terdaftar'],
                ['label' => 'Periode asesmen', 'value' => $statistics['assessmentPeriods'], 'accent' => 'text-violet-300', 'note' => 'ATS, AAS/AAT, UUB'],
                ['label' => 'Sesi ujian', 'value' => $statistics['examSessions'], 'accent' => 'text-amber-300', 'note' => 'Reguler dan susulan'],
                ['label' => 'Hadir hari ini', 'value' => $statistics['checkinsToday'], 'accent' => 'text-emerald-300', 'note' => 'Terverifikasi sistem'],
            ];
        @endphp

        @foreach ($cards as $card)
            <article class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                <p class="text-sm text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold {{ $card['accent'] }}">{{ number_format($card['value'], 0, ',', '.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $card['note'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-7 grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <article class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Alur Operasional</p>
                    <h3 class="mt-2 text-lg font-semibold text-white">Kesiapan sistem ujian</h3>
                </div>
                <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-200">Tahap pengembangan</span>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2">
                @foreach ([
                    ['01', 'Data akademik', 'Tahun ajaran, kelas, siswa, dan mata pelajaran.'],
                    ['02', 'Periode asesmen', 'ATS, AAS/AAT, UUB ganjil maupun genap.'],
                    ['03', 'Sesi & susulan', 'Penempatan peserta tanpa membuat data ganda.'],
                    ['04', 'Absensi aman', 'Kartu, wajah, GPS, radius, dan akurasi lokasi.'],
                ] as [$number, $title, $description])
                    <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-400/10 text-xs font-semibold text-cyan-300">{{ $number }}</span>
                            <div>
                                <h4 class="text-sm font-semibold text-slate-100">{{ $title }}</h4>
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <aside class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Status Akses</p>
            <h3 class="mt-2 text-lg font-semibold text-white">Akun terverifikasi</h3>

            <dl class="mt-6 space-y-4 text-sm">
                <div class="border-b border-white/10 pb-4">
                    <dt class="text-xs text-slate-500">Nama pengguna</dt>
                    <dd class="mt-1 font-medium text-slate-200">{{ $user->name }}</dd>
                </div>
                <div class="border-b border-white/10 pb-4">
                    <dt class="text-xs text-slate-500">Email</dt>
                    <dd class="mt-1 break-all font-medium text-slate-200">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Hak akses</dt>
                    <dd class="mt-1 font-medium text-emerald-300">{{ $user->role->label() }}</dd>
                </div>
            </dl>
        </aside>
    </section>
@endsection
