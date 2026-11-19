<label class="block text-sm text-slate-300">Nama lokasi simulasi
    <input name="name" value="{{ $location?->name }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
</label>
<div class="grid gap-3 sm:grid-cols-2">
    <label class="block text-sm text-slate-300">Latitude
        <input name="latitude" type="number" step="any" min="-90" max="90" value="{{ $location?->latitude }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>
    <label class="block text-sm text-slate-300">Longitude
        <input name="longitude" type="number" step="any" min="-180" max="180" value="{{ $location?->longitude }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>
    <label class="block text-sm text-slate-300">Radius lokasi (meter)
        <input name="radius_meters" type="number" min="10" max="5000" value="{{ $location?->radius_meters ?? 100 }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>
    <label class="block text-sm text-slate-300">Batas akurasi GPS (meter)
        <input name="max_accuracy_meters" type="number" min="5" max="500" value="{{ $location?->max_accuracy_meters ?? 50 }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>
</div>
