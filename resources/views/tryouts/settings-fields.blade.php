<label class="block text-sm text-slate-300">Judul
    <input name="title" value="{{ old('title', $trial?->title) }}" required placeholder="Uji Coba Sejarah" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
</label>
<label class="block text-sm text-slate-300">Kode bersama
    <input name="access_code" value="{{ old('access_code', $trial?->access_code) }}" required placeholder="" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase">
    <span class="mt-1 block text-xs text-slate-500">Huruf, angka, dan tanda hubung.</span>
</label>
<label class="block text-sm text-slate-300">Durasi (menit)
    <input type="number" name="duration_minutes" min="10" max="300" value="{{ old('duration_minutes', $trial?->duration_minutes ?? 60) }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
</label>
<div class="grid gap-3 sm:grid-cols-2">
    <label class="block text-sm text-slate-300">Mulai (opsional)
        <input type="datetime-local" name="starts_at" step="1" value="{{ old('starts_at', $trial?->starts_at?->format('Y-m-d\TH:i:s')) }}"
            class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3 text-xs">
    </label>
    <label class="block text-sm text-slate-300">Selesai (opsional)
        <input type="datetime-local" name="ends_at" step="1" value="{{ old('ends_at', $trial?->ends_at?->format('Y-m-d\TH:i:s')) }}"
            class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3 text-xs">
    </label>
</div>
@if ($hasCheckins ?? false)
    <p class="rounded-xl border border-amber-400/20 bg-amber-400/10 p-3 text-sm text-amber-200">Lokasi simulasi {{ $trial->location?->name }} · absensi sudah tercatat.</p>
@else
    <label class="block text-sm text-slate-300">Lokasi dan absensi simulasi
        <select name="trial_location_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Tanpa absensi lokasi</option>
            @foreach ($trialLocations as $location)
                <option value="{{ $location->id }}" @selected(old('trial_location_id', $trial?->trial_location_id) == $location->id)>{{ $location->name }} · GPS wajib</option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-slate-400">Untuk simulasi di sekolah, pilih lokasi agar siswa absen sebelum waktu ujian berjalan.</span>
    </label>
@endif
<a href="{{ route('trial-locations.index') }}" class="inline-flex text-sm font-semibold text-cyan-300">Kelola lokasi simulasi →</a>
<input type="hidden" name="is_active" value="0">
<label class="flex items-center gap-3 text-sm text-slate-300">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $trial?->is_active))
        class="size-4 rounded border-white/20 bg-slate-950">
    Aktifkan ujian
</label>
