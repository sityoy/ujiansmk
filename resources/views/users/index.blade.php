@extends('layouts.app')

@section('title', 'Manajemen Akun')
@section('eyebrow', 'Keamanan Akses')
@section('heading', 'Manajemen Akun')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
        <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Akun Baru</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Tambah pengguna</h2>

            <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-3">
                @csrf
                <input name="name" value="{{ old('name') }}" required placeholder="Nama lengkap"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="Alamat email"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <select name="role" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih hak akses</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                <input type="password" name="password" required placeholder="Password"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <input type="password" name="password_confirmation" required placeholder="Ulangi password"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Buat akun</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
            <div class="border-b border-white/10 p-6">
                <h2 class="text-xl font-semibold text-white">Pengguna sistem</h2>
                <p class="mt-1 text-sm text-slate-500">Nonaktifkan akun tanpa menghapus riwayat aktivitasnya.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500">
                        <tr><th class="px-5 py-4">Pengguna</th><th class="px-5 py-4">Role</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($users as $account)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-white">{{ $account->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $account->email }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-300">{{ $account->role->label() }}</td>
                                <td class="px-5 py-4">
                                    <span class="{{ $account->is_active ? 'text-emerald-300' : 'text-rose-300' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <form method="POST" action="{{ route('users.toggle', $account) }}">@csrf @method('PATCH')
                                        <button class="text-xs {{ $account->is(auth()->user()) ? 'cursor-not-allowed text-slate-600' : 'text-cyan-300' }}" {{ $account->is(auth()->user()) ? 'disabled' : '' }}>
                                            {{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-white/10 p-5">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
@endsection
