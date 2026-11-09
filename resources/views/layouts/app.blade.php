<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — {{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('ujiansmk-theme');
                document.documentElement.dataset.theme = savedTheme === 'light' ? 'light' : 'dark';
                if (localStorage.getItem('ujiansmk-sidebar-collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    @php($schoolName = $schoolProfile?->name ?? 'Nama Sekolah')
    @php($currentRole = auth()->user()->role->value)
    @php($masterOpen = request()->routeIs('settings.school.*', 'academic.*', 'users.*', 'scheduling.campuses.*'))
    @php($examOpen = request()->routeIs('scheduling.*', 'tryouts.*', 'question-bank.*', 'trial-question-banks.*', 'grading.*', 'operations.*', 'attendance.*') && !request()->routeIs('scheduling.campuses.*'))
    @php($reportOpen = request()->routeIs('reports.*'))

    <div class="app-shell min-h-screen">
        <div data-sidebar-overlay class="app-sidebar-overlay" aria-hidden="true"></div>

        <aside data-sidebar class="app-sidebar border-r border-white/10 bg-slate-950/95 px-5 py-5">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-cyan-400 text-lg font-bold text-slate-950 shadow-lg shadow-cyan-500/20">
                        {{ str($schoolName)->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-base font-semibold tracking-wide">{{ $schoolName }}</span>
                        <span class="block text-xs text-slate-400">Sistem Ujian Sekolah</span>
                    </span>
                </a>
                <button type="button" data-sidebar-close class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/10 text-slate-300 lg:hidden" aria-label="Tutup menu">×</button>
            </div>

            <span class="mt-6 inline-flex rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">
                Sistem aktif
            </span>

            <nav data-sidebar-nav class="mt-7 space-y-2 text-sm">
                <a href="{{ route('dashboard') }}" class="block rounded-xl px-4 py-3 font-semibold {{ request()->routeIs('dashboard') ? 'bg-cyan-400 text-slate-950' : 'text-slate-300 hover:bg-white/5' }}">
                    Ringkasan
                </a>

                @if ($currentRole === 'student')
                    <a href="{{ route('student.exams.index') }}" class="block rounded-xl px-4 py-3 {{ request()->routeIs('student.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-300 hover:bg-white/5' }}">
                        Ujian saya
                    </a>
                @else
                    @if (in_array($currentRole, ['super_admin', 'committee', 'principal'], true))
                        <details data-nav-group class="nav-group rounded-xl border border-white/10 bg-white/[0.025]" @if($masterOpen) open @endif>
                            <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 font-semibold {{ $masterOpen ? 'text-cyan-300' : 'text-slate-300' }}">
                                <span>Master Data</span>
                                <svg class="nav-chevron size-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </summary>
                            <div class="space-y-1 border-t border-white/10 p-2">
                                @if ($currentRole === 'super_admin')
                                    <a href="{{ route('settings.school.edit') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('settings.school.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Identitas sekolah</a>
                                    <a href="{{ route('academic.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('academic.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Data akademik</a>
                                    <a href="{{ route('users.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('users.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Manajemen akun</a>
                                @endif
                                @if (in_array($currentRole, ['super_admin', 'committee', 'principal'], true))
                                    <a href="{{ route('scheduling.campuses.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('scheduling.campuses.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Lokasi & radius</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if (in_array($currentRole, ['super_admin', 'committee', 'teacher', 'proctor', 'principal'], true))
                        <details data-nav-group class="nav-group rounded-xl border border-white/10 bg-white/[0.025]" @if($examOpen) open @endif>
                            <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 font-semibold {{ $examOpen ? 'text-cyan-300' : 'text-slate-300' }}">
                                <span>Ujian</span>
                                <svg class="nav-chevron size-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </summary>
                            <div class="space-y-1 border-t border-white/10 p-2">
                                @if (in_array($currentRole, ['super_admin', 'committee'], true))
                                    <a href="{{ route('scheduling.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('scheduling.*') && !request()->routeIs('scheduling.questions.*', 'scheduling.campuses.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Penjadwalan</a>
                                    <a href="{{ route('tryouts.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('tryouts.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Ujian uji coba</a>
                                @endif
                                @if (in_array($currentRole, ['super_admin', 'committee', 'teacher'], true))
                                    <a href="{{ route('question-bank.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('question-bank.*', 'scheduling.questions.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Bank soal inti</a>
                                    @if (in_array($currentRole, ['super_admin', 'committee'], true))
                                    <a href="{{ route('trial-question-banks.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('trial-question-banks.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Bank soal uji coba</a>
                                    @endif
                                    <a href="{{ route('grading.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('grading.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Koreksi jawaban</a>
                                @endif
                                <a href="{{ route('operations.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('operations.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Pemantauan peserta</a>
                                @if (in_array($currentRole, ['super_admin', 'committee', 'proctor'], true))
                                    <a href="{{ route('attendance.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('attendance.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Absensi & keamanan</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if (in_array($currentRole, ['super_admin', 'committee', 'principal', 'teacher'], true))
                        <details data-nav-group class="nav-group rounded-xl border border-white/10 bg-white/[0.025]" @if($reportOpen) open @endif>
                            <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 font-semibold {{ $reportOpen ? 'text-cyan-300' : 'text-slate-300' }}">
                                <span>Laporan</span>
                                <svg class="nav-chevron size-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </summary>
                            <div class="space-y-1 border-t border-white/10 p-2">
                                @if (in_array($currentRole, ['super_admin', 'committee', 'teacher'], true))
                                    <a href="{{ route('reports.exam-results.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('reports.exam-results.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Hasil ujian</a>
                                @endif
                                <a href="{{ route('reports.midterm.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('reports.midterm.*') ? 'bg-cyan-400 text-slate-950 font-semibold' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">Rapor ATS</a>
                            </div>
                        </details>
                    @endif
                @endif
            </nav>

            <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-4 text-xs leading-5 text-slate-400">
                Menu ditampilkan sesuai hak akses setiap pengguna.
            </div>
        </aside>

        <main class="app-main min-w-0">
            <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/90 px-4 py-3 backdrop-blur md:px-8">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-sidebar-toggle class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition hover:border-cyan-400/40 hover:text-cyan-300" aria-label="Buka atau tutup menu" aria-expanded="false">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">@yield('eyebrow', 'Sistem Ujian')</p>
                            <h1 class="mt-1 truncate text-lg font-semibold text-white md:text-xl">@yield('heading', 'Dashboard')</h1>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 md:gap-3">
                        <button type="button" data-theme-toggle>
                            <span data-theme-icon aria-hidden="true">☀</span>
                            <span data-theme-label class="hidden md:inline">Terang</span>
                        </button>
                        <div class="hidden text-right sm:block">
                            <p class="max-w-40 truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
                        </div>
                        <div class="hidden size-10 place-items-center rounded-full border border-white/10 bg-white/5 text-sm font-semibold text-cyan-300 sm:grid">
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

            <div class="px-4 py-6 md:px-8 md:py-9">
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
