@extends('layouts.app')

@section('title', 'Lokasi dan Radius')
@section('eyebrow', 'Keamanan Kehadiran')
@section('heading', 'Lokasi dan Radius Ujian')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.06] p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Kontrol lokasi</p>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">
            Radius menentukan jarak maksimal siswa dari titik sekolah saat mengirim absensi. Lokasi yang sudah dipakai ujian disimpan sebagai riwayat dan hanya dapat dinonaktifkan.
        </p>
    </div>

    @include('campuses.partials.manager')
@endsection
