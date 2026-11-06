<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ganti Password — {{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <main class="grid min-h-screen place-items-center px-5 py-10">
        <section class="w-full max-w-lg rounded-3xl border border-cyan-400/20 bg-white/[0.045] p-7 shadow-2xl shadow-cyan-950/30 sm:p-9">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-300">Keamanan akun</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">Buat password pribadi</h1>
            <p class="mt-3 text-sm leading-6 text-slate-400">
                Anda sedang memakai password awal dari sekolah. Ganti password terlebih dahulu agar akun tidak dapat digunakan oleh orang lain.
            </p>

            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-4">
                @csrf
                @method('PUT')
                <label class="block">
                    <span class="mb-2 block text-sm text-slate-300">Password awal/saat ini</span>
                    <input type="password" name="current_password" required autocomplete="current-password"
                        class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-400">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm text-slate-300">Password baru</span>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-400">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm text-slate-300">Ulangi password baru</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-400">
                </label>
                <p class="text-xs leading-5 text-slate-500">Minimal 8 karakter serta mengandung huruf dan angka.</p>
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Simpan password baru</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button class="text-sm text-slate-400 hover:text-white">Keluar dari akun</button>
            </form>
        </section>
    </main>
</body>
</html>
