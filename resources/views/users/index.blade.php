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
            <p class="mt-2 text-xs leading-5 text-slate-500">Kepala Sekolah maksimal 1 akun dan Super Admin maksimal 4 akun.</p>

            <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-3">
                @csrf
                <input name="name" value="{{ old('name') }}" required placeholder="Nama lengkap"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="Alamat email"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <select name="role" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    <option value="">Pilih hak akses</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <input type="password" name="password" required placeholder="Password awal"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <input type="password" name="password_confirmation" required placeholder="Ulangi password"
                    class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-cyan-400">
                <p class="text-xs leading-5 text-slate-500">Minimal 10 karakter, berisi huruf besar, huruf kecil, dan angka. Pengguna wajib menggantinya saat login pertama.</p>
                <button class="w-full rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Buat akun</button>
            </form>
        </section>

        <section class="rounded-3xl border border-violet-400/20 bg-violet-400/[0.045] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Excel · Banyak Akun</p>
            <h2 class="mt-2 text-xl font-semibold text-white">Impor dan ekspor akun</h2>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                Akses dapat ditulis sebagai Super Admin, Panitia, Guru, Pengawas, atau Kepala Sekolah.
                ID dipakai untuk memperbarui akun hasil ekspor; biarkan kosong untuk membuat akun baru.
            </p>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('users.template') }}" class="rounded-xl border border-violet-400/30 px-4 py-2.5 text-sm font-medium text-violet-200 hover:bg-violet-400/10">Unduh template Excel</a>
                <a href="{{ route('users.export') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/5">Ekspor seluruh akun</a>
            </div>

            <form method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data" class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]">
                @csrf
                <input type="file" name="account_spreadsheet" accept=".xlsx,.csv" required
                    class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/50 px-4 py-3 text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-400 file:px-3 file:py-2 file:font-semibold file:text-slate-950">
                <button class="rounded-xl bg-violet-400 px-5 py-3 text-sm font-semibold text-slate-950">Impor akun</button>
                <div class="sm:col-span-2 space-y-1 text-xs leading-5 text-slate-500">
                    <p>Format XLSX/CSV, maksimal 10 MB dan 500 akun sekali impor.</p>
                    <p class="text-amber-200/80">Password lama tidak dapat diekspor. Kolom Password Baru wajib untuk akun baru; kosongkan pada akun lama agar password tetap sama.</p>
                </div>
            </form>
        </section>
    </div>

    @if ($editingUser)
        <section id="edit-account" class="mt-6 rounded-3xl border border-amber-400/20 bg-amber-400/[0.045] p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">Edit Akun</p>
                    <h2 class="mt-2 text-xl font-semibold text-white">{{ $editingUser->name }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Kosongkan password jika tidak ingin meresetnya.</p>
                </div>
                <a href="{{ route('users.index', request()->except(['edit', 'page'])) }}" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300">Tutup</a>
            </div>

            <form method="POST" action="{{ route('users.update', $editingUser) }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                @method('PUT')
                <input name="name" value="{{ old('name', $editingUser->name) }}" required placeholder="Nama lengkap"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <input type="email" name="email" value="{{ old('email', $editingUser->email) }}" required placeholder="Alamat email"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <select name="role" required class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $editingUser->role->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <input type="password" name="password" placeholder="Password baru (opsional)"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <input type="password" name="password_confirmation" placeholder="Ulangi password baru"
                    class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-amber-400">
                <label class="flex items-center gap-3 rounded-xl border border-white/10 px-4 py-3 text-sm text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingUser->is_active))>
                    Akun aktif
                </label>
                <div class="flex gap-2 md:col-span-2 xl:col-span-3">
                    <button class="rounded-xl bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950">Simpan perubahan</button>
                    <a href="{{ route('users.index', request()->except(['edit', 'page'])) }}" class="rounded-xl border border-white/10 px-5 py-3 text-sm text-slate-300">Batal</a>
                </div>
            </form>
        </section>
    @endif

    <section class="mt-6 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
        <div class="border-b border-white/10 p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Pengguna sistem</h2>
                    <p class="mt-1 text-sm text-slate-500">10 akun per halaman. Siswa dikelola melalui menu Data Akademik.</p>
                </div>
                <form method="GET" action="{{ route('users.index') }}" class="grid gap-2 sm:grid-cols-[220px_170px_auto]">
                    <input name="q" value="{{ $search }}" placeholder="Cari nama atau email"
                        class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-xs outline-none focus:border-cyan-400">
                    <select name="role" class="rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-xs">
                        <option value="">Semua akses</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl border border-cyan-400/30 px-4 py-2 text-xs font-semibold text-cyan-200">Tampilkan</button>
                </form>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-4">Pengguna</th><th class="px-5 py-4">Akses</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($users as $account)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-medium text-white">{{ $account->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $account->email }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ $account->role->label() }}</td>
                            <td class="px-5 py-4">
                                <span class="{{ $account->is_active ? 'text-emerald-300' : 'text-rose-300' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('users.index', [...request()->query(), 'edit' => $account->id]) }}#edit-account" class="text-xs text-amber-300">Edit</a>
                                    <form method="POST" action="{{ route('users.toggle', $account) }}">@csrf @method('PATCH')
                                        <button class="text-xs {{ $account->is(auth()->user()) ? 'cursor-not-allowed text-slate-600' : 'text-cyan-300' }}" {{ $account->is(auth()->user()) ? 'disabled' : '' }}>
                                            {{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Tidak ada akun sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-white/10 p-5">
            <p class="mb-3 text-xs text-slate-500">Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} akun.</p>
            @if ($users->hasPages())
                {{ $users->links() }}
            @endif
        </div>
    </section>
@endsection
