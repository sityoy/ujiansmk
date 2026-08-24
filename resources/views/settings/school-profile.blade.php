@extends('layouts.app')

@section('title', 'Identitas Sekolah')
@section('eyebrow', 'Pengaturan')
@section('heading', 'Identitas Sekolah')

@section('content')
    <div class="max-w-4xl">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 md:p-8">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Branding & Rapor</p>
                <h2 class="mt-2 text-2xl font-semibold text-white">Lengkapi data sekolah</h2>
                <p class="mt-3 text-sm leading-6 text-slate-400">
                    Nama sekolah digunakan pada halaman masuk, dashboard, dan kepala rapor ATS.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.school.update') }}" class="mt-8 grid gap-5 md:grid-cols-2">
                @csrf
                @method('PUT')

                <div class="md:col-span-2">
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-200">Nama sekolah</label>
                    <input id="name" name="name" value="{{ old('name', $profile->name) }}" required
                        placeholder="Contoh: SMK Permata Bunda I Jakarta"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70 focus:ring-4 focus:ring-cyan-400/10">
                </div>

                <div>
                    <label for="npsn" class="mb-2 block text-sm font-medium text-slate-200">NPSN</label>
                    <input id="npsn" name="npsn" value="{{ old('npsn', $profile->npsn) }}"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">
                </div>

                <div>
                    <label for="city" class="mb-2 block text-sm font-medium text-slate-200">Kota/Kabupaten</label>
                    <input id="city" name="city" value="{{ old('city', $profile->city) }}"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="mb-2 block text-sm font-medium text-slate-200">Alamat sekolah</label>
                    <textarea id="address" name="address" rows="3"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">{{ old('address', $profile->address) }}</textarea>
                </div>

                <div>
                    <label for="phone" class="mb-2 block text-sm font-medium text-slate-200">Telepon</label>
                    <input id="phone" name="phone" value="{{ old('phone', $profile->phone) }}"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Email sekolah</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $profile->email) }}"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">
                </div>

                <div class="md:col-span-2">
                    <label for="principal_name" class="mb-2 block text-sm font-medium text-slate-200">Nama kepala sekolah</label>
                    <input id="principal_name" name="principal_name" value="{{ old('principal_name', $profile->principal_name) }}"
                        class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400/70">
                </div>

                <div class="md:col-span-2 flex justify-end border-t border-white/10 pt-6">
                    <button type="submit" class="rounded-2xl bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                        Simpan identitas sekolah
                    </button>
                </div>
            </form>
        </section>
    </div>
@endsection
