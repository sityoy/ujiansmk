<section class="rounded-3xl border border-white/10 bg-white/[0.035] p-6">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Lokasi absensi</p>
    <h2 class="mt-2 text-xl font-semibold text-white">Lokasi dan radius ujian</h2>

    <form method="POST" action="{{ route('scheduling.campuses.store') }}" class="mt-6 grid gap-3 sm:grid-cols-2">
        @csrf
        <input name="name" value="{{ old('name') }}" required placeholder="Nama lokasi/kampus"
            class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
        <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude') }}" required placeholder="Latitude"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
        <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude') }}" required placeholder="Longitude"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
        <input type="number" name="radius_meters" value="{{ old('radius_meters', 100) }}" min="10" max="5000" required placeholder="Radius (meter)"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
        <input type="number" name="max_accuracy_meters" value="{{ old('max_accuracy_meters', 50) }}" min="5" max="500" required placeholder="Akurasi GPS maksimum"
            class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm outline-none focus:border-emerald-400">
        <button class="sm:col-span-2 rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950">Tambah lokasi</button>
    </form>

    <div class="mt-6 space-y-3">
        @forelse ($campuses as $campus)
            @php($campusIsUsed = $campus->exam_sessions_count > 0 || $campus->daily_checkins_count > 0)
            <article class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-white">{{ $campus->name }}</p>
                            <span class="rounded-full px-2.5 py-1 text-[11px] {{ $campus->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-400/10 text-slate-400' }}">
                                {{ $campus->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Radius {{ number_format($campus->radius_meters, 0, ',', '.') }} m · akurasi GPS ≤ {{ number_format($campus->max_accuracy_meters, 0, ',', '.') }} m</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $campus->latitude }}, {{ $campus->longitude }} · {{ $campus->exam_sessions_count }} sesi · {{ $campus->daily_checkins_count }} absensi</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" data-campus-edit-toggle="campus-edit-{{ $campus->id }}" class="text-xs font-medium text-cyan-300">Edit</button>
                        <form method="POST" action="{{ route('scheduling.campuses.toggle', $campus) }}">
                            @csrf @method('PATCH')
                            <button class="text-xs {{ $campus->is_active ? 'text-amber-300' : 'text-emerald-300' }}">{{ $campus->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                        @if ($campusIsUsed)
                            <span title="Lokasi sudah memiliki sesi atau absensi" class="cursor-help text-xs text-slate-600">Tidak dapat dihapus</span>
                        @else
                            <form method="POST" action="{{ route('scheduling.campuses.destroy', $campus) }}" onsubmit="return confirm('Hapus lokasi {{ addslashes($campus->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-300">Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>

                <form id="campus-edit-{{ $campus->id }}" method="POST" action="{{ route('scheduling.campuses.update', $campus) }}" class="mt-4 hidden grid gap-3 border-t border-white/10 pt-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $campus->name }}" required class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    <div>
                        <label class="mb-1.5 block text-xs text-slate-500">Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" value="{{ $campus->latitude }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs text-slate-500">Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" value="{{ $campus->longitude }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs text-slate-500">Radius maksimal (meter)</label>
                        <input type="number" name="radius_meters" value="{{ $campus->radius_meters }}" min="10" max="5000" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs text-slate-500">Akurasi GPS maksimum (meter)</label>
                        <input type="number" name="max_accuracy_meters" value="{{ $campus->max_accuracy_meters }}" min="5" max="500" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 text-sm">
                    </div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950">Simpan perubahan</button>
                        <button type="button" data-campus-edit-toggle="campus-edit-{{ $campus->id }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-slate-300">Batal</button>
                    </div>
                </form>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-500">Belum ada lokasi ujian.</p>
        @endforelse
    </div>
</section>

@once
    <script>
        document.querySelectorAll('[data-campus-edit-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.dataset.campusEditToggle)?.classList.toggle('hidden');
            });
        });
    </script>
@endonce
