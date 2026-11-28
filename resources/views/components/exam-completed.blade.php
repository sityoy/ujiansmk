@props(['participant', 'exam', 'dashboardUrl'])

<div class="mx-auto max-w-xl text-center">
    <section class="rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.04] p-8">
        <div aria-hidden="true" class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-400/10 text-4xl text-emerald-300">✓</div>
        <h1 class="mt-6 text-2xl font-semibold text-white">Jawaban berhasil dikumpulkan</h1>
        <p class="mt-3 font-medium text-white">{{ $participant }}</p>
        <p class="mt-1 text-slate-400">{{ $exam }}</p>
        {{ $slot }}
        <a href="{{ $dashboardUrl }}" class="mt-7 inline-flex rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950">Kembali ke Dashboard Siswa</a>
    </section>
</div>
