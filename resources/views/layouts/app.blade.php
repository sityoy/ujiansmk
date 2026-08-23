<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'GALAK CBT') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="border-b border-white/10 bg-slate-950/95 px-5 py-5 lg:min-h-screen lg:border-b-0 lg:border-r">
            <div class="flex items-center justify-between lg:block">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-2xl bg-cyan-400 text-lg font-bold text-slate-950 shadow-lg shadow-cyan-500/20">G</span>
                    <span>
                        <span class="block text-base font-semibold tracking-wide">GALAK CBT</span>
                        <span class="block text-xs text-slate-400">Assessment Control Center</span>
                    </span>
                </a>
                <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300 lg:mt-6 lg:inline-flex">
                    Sistem aktif
                </span>
            </div>

            <nav class="mt-6 grid grid-cols-2 gap-2 text-sm lg:mt-8 lg:grid-cols-1">
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">
                    Ringkasan
                </a>
                <span class="rounded-xl px-4 py-3 text-slate-400">Data sekolah</span>
                <span class="rounded-xl px-4 py-3 text-slate-400">Penjadwalan</span>
                <span class="rounded-xl px-4 py-3 text-slate-400">Pelaksanaan CBT</span>
                <span class="rounded-xl px-4 py-3 text-slate-400">Absensi & keamanan</span>
            </nav>

            <div class="mt-8 hidden rounded-2xl border border-white/10 bg-white/5 p-4 text-xs leading-5 text-slate-400 lg:block">
                Tahap fondasi autentikasi sudah aktif. Modul pengelolaan data akan dibuka secara bertahap.
            </div>
        </aside>

        <main class="min-w-0">
            <header class="border-b border-white/10 bg-slate-950/75 px-5 py-4 backdrop-blur md:px-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">@yield('eyebrow', 'GALAK CBT')</p>
                        <h1 class="mt-1 text-xl font-semibold text-white">@yield('heading', 'Dashboard')</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-full border border-white/10 bg-white/5 text-sm font-semibold text-cyan-300">
                            {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-medium text-slate-300 transition hover:border-rose-400/40 hover:bg-rose-400/10 hover:text-rose-200">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="px-5 py-7 md:px-8 md:py-9">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
