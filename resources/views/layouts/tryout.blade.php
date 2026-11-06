<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ujian Uji Coba') — Sistem Ujian Sekolah</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('ujiansmk-theme');
                document.documentElement.dataset.theme = savedTheme === 'light' ? 'light' : 'dark';
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-white/10 bg-slate-950/90 px-5 py-4">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-cyan-400 font-bold text-slate-950">U</span>
                <div class="min-w-0"><p class="truncate font-semibold">Ujian Uji Coba</p><p class="text-xs text-slate-400">Sistem Ujian Sekolah</p></div>
            </div>
            <button type="button" data-theme-toggle>
                <span data-theme-icon aria-hidden="true">☀</span>
                <span data-theme-label>Terang</span>
            </button>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-5 py-8">@yield('content')</main>
</body>
</html>
