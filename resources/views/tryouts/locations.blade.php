@extends('layouts.app')

@section('title', 'Lokasi Simulasi')
@section('eyebrow', 'Ujian Uji Coba')
@section('heading', 'Lokasi Simulasi')

@section('content')
    <a href="{{ route('tryouts.index') }}" class="mb-4 inline-block text-sm font-semibold text-cyan-300">← Daftar Ujian Uji Coba</a>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('trial-locations.store') }}" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.035] p-5">
            @csrf
            <h2 class="text-lg font-semibold">Tambah lokasi simulasi</h2>
            <p class="text-sm text-slate-400">Masukkan koordinat sekolah, lalu pilih lokasi ini saat membuat atau mengedit Ujian Uji Coba.</p>
            @include('tryouts.location-fields', ['location' => null])
            <button class="rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Simpan lokasi</button>
        </form>

        <section class="space-y-3" aria-label="Daftar lokasi simulasi">
            @forelse ($locations as $location)
                <details class="rounded-2xl border border-white/10 bg-white/[0.035] p-5">
                    <summary class="cursor-pointer font-semibold">{{ $location->name }} · Radius {{ $location->radius_meters }} m</summary>
                    <form method="POST" action="{{ route('trial-locations.update', $location) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        @include('tryouts.location-fields', ['location' => $location])
                        <p class="text-sm text-slate-400">Perubahan koordinat atau radius berlaku untuk pemeriksaan GPS berikutnya pada ujian yang memakai lokasi ini.</p>
                        <button class="rounded-xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950">Perbarui lokasi</button>
                    </form>
                </details>
            @empty
                <p class="rounded-2xl border border-white/10 p-5 text-sm text-slate-400">Belum ada lokasi simulasi.</p>
            @endforelse
        </section>
    </div>
@endsection
