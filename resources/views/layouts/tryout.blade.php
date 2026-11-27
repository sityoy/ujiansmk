<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="author" content="PT SITYOY IT SOLUTIONS">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ujian Uji Coba') — {{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</title>
    <script src="{{ asset('js/theme.js') }}?v=2"></script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tryout-page min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-white/10 bg-slate-950/90 px-5 py-4">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                @if ($schoolProfile?->logo_path)
                    <x-school-logo :school="$schoolProfile" class="size-10 shrink-0 rounded-xl bg-white p-1" width="40" height="40" />
                @else
                    <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-cyan-400 font-bold text-slate-950">U</span>
                @endif
                <div class="min-w-0"><p class="truncate font-semibold">@yield('portal-label', 'Ujian Uji Coba')</p><p class="text-xs text-slate-400">{{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</p></div>
            </div>
            <x-theme-picker />
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-5 py-8">@yield('content')</main>
</body>
</html>
