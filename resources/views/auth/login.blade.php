<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — {{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    @php($schoolName = $schoolProfile?->name ?? 'Nama Sekolah')

    <main class="relative grid min-h-screen place-items-center overflow-hidden px-5 py-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.16),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(59,130,246,0.12),transparent_30%)]"></div>
        <div class="absolute inset-0 opacity-[0.04] [background-image:linear-gradient(rgba(255,255,255,.6)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.6)_1px,transparent_1px)] [background-size:32px_32px]"></div>

        <section class="relative w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/80 shadow-2xl shadow-black/40 backdrop-blur-xl lg:grid lg:grid-cols-[1.1fr_.9fr]">
            <div class="relative hidden min-h-[640px] overflow-hidden bg-gradient-to-br from-cyan-400 via-sky-500 to-blue-700 p-10 text-slate-950 lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-24 -top-24 size-80 rounded-full border-[48px] border-white/15"></div>
                <div class="absolute -bottom-40 -left-32 size-96 rounded-full border-[64px] border-slate-950/10"></div>

                <div class="relative flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-2xl bg-slate-950 text-xl font-bold text-cyan-300">
                        {{ str($schoolName)->substr(0, 1)->upper() }}
                    </span>
                    <div>
                        <p class="max-w-sm font-semibold tracking-wide">{{ $schoolName }}</p>
                        <p class="text-xs font-medium text-slate-800/70">Sistem Ujian Sekolah</p>
                    </div>
                </div>

                <div class="relative">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-900/60">Tertib. Aman. Terukur.</p>
                    <h1 class="mt-4 max-w-md text-4xl font-semibold leading-tight">Satu kendali untuk seluruh pelaksanaan asesmen.</h1>
                    <p class="mt-5 max-w-md text-sm leading-6 text-slate-900/75">
                        Penjadwalan reguler dan susulan, kontrol peserta, absensi berbasis lokasi, serta jejak keamanan dalam satu sistem.
                    </p>
                </div>

                <p class="relative text-xs font-medium text-slate-900/60">{{ $schoolName }} · Sistem Ujian Sekolah</p>
            </div>

            <div class="px-6 py-9 sm:px-10 lg:px-12 lg:py-14">
                <div class="mb-9 lg:hidden">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-cyan-400 font-bold text-slate-950">
                            {{ str($schoolName)->substr(0, 1)->upper() }}
                        </span>
                        <div>
                            <p class="font-semibold">{{ $schoolName }}</p>
                            <p class="text-xs text-slate-400">Sistem Ujian Sekolah</p>
                        </div>
                    </div>
                </div>

                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300">Akses Terverifikasi</p>
                <h2 class="mt-3 text-3xl font-semibold text-white">Masuk ke sistem</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Gunakan akun yang diberikan oleh administrator atau panitia ujian.</p>

                @if (session('status'))
                    <div class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Alamat email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username"
                            required autofocus placeholder="nama@sekolah.sch.id"
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-400/70 focus:ring-4 focus:ring-cyan-400/10">
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Kata sandi</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            placeholder="Masukkan kata sandi"
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-400/70 focus:ring-4 focus:ring-cyan-400/10">
                    </div>

                    <label class="flex items-center gap-3 text-sm text-slate-400">
                        <input name="remember" type="checkbox" value="1" class="size-4 rounded border-white/20 bg-slate-950 text-cyan-400 focus:ring-cyan-400/20">
                        Ingat sesi pada perangkat ini
                    </label>

                    <button type="submit" class="w-full rounded-2xl bg-cyan-400 px-5 py-3.5 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/15 transition hover:bg-cyan-300 focus:outline-none focus:ring-4 focus:ring-cyan-400/20">
                        Masuk ke Sistem Ujian
                    </button>
                </form>

                <div class="mt-8 border-t border-white/10 pt-6">
                    <p class="text-xs leading-5 text-slate-500">
                        Aktivitas masuk dicatat untuk keamanan ujian. Jangan membagikan akun atau kata sandi kepada pihak lain.
                    </p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
