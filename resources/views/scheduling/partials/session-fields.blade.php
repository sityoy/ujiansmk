@php
    $editingMembers = $session ? $members->whereIn('id', $sessions->pluck('assessment_subject_id')) : $members;
    $sourceOptions = $sourceSchedules->map(fn ($sources) => $session && ! $session->schedule_group_id
        ? $sources->firstWhere('assessment_subject_id', $session->assessment_subject_id)
        : $sources->first())->filter()->where('id', '!=', $session?->id);
    $sourceId = $session?->source_session_id;
    $selectedSource = $allSessions->firstWhere('id', $sourceId);
    $selectedSourceGroup = $selectedSource?->schedule_group_id;
@endphp
<div class="grid gap-3 md:grid-cols-2">
    <label class="text-xs text-slate-400">Lokasi
        <select name="campus_id" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Pilih lokasi</option>
            @foreach ($campuses->where('is_active', true) as $campus)
                <option value="{{ $campus->id }}" @selected($session?->campus_id === $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs text-slate-400">Jenis sesi
        <select name="kind" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="regular" @selected($session?->kind->value === 'regular')>Reguler</option>
            <option value="makeup" @selected($session?->kind->value === 'makeup')>Susulan</option>
        </select>
    </label>
    <label class="text-xs text-slate-400">Tanggal dan jam mulai
        <input type="datetime-local" name="starts_at" value="{{ $session?->starts_at->format('Y-m-d\TH:i') }}" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
    </label>
    <label class="text-xs text-slate-400">Durasi (menit)
        <input type="number" name="duration_minutes" value="{{ $session?->duration_minutes }}" min="10" max="600" required placeholder="Isi durasi sendiri" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
    </label>
    <label class="text-xs text-slate-400">Status
        <select name="status" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            @foreach ($sessionStatuses as $status)
                @if ($session || $status->value !== 'closed')
                    <option value="{{ $status->value }}" @selected($session?->status === $status)>{{ $statusLabels[$status->value] }}</option>
                @endif
            @endforeach
        </select>
    </label>
    <label class="text-xs text-slate-400">Jadwal reguler sumber (khusus susulan)
        <select name="source_session_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
            <option value="">Pilih sumber untuk sesi susulan</option>
            @foreach ($sourceOptions as $source)
                <option value="{{ $source->id }}" @selected($source->id === $sourceId || ($selectedSourceGroup && $source->schedule_group_id === $selectedSourceGroup))>{{ $source->starts_at->format('d/m/Y H:i') }} · {{ $source->assessmentSubject->schoolClass->name }}{{ $source->schedule_group_id ? ' dan rombel satu jadwal' : '' }}</option>
            @endforeach
        </select>
    </label>
</div>
<details class="rounded-xl border border-white/10 p-3">
    <summary class="cursor-pointer text-xs text-slate-400">Ruangan per rombel (opsional)</summary>
    <div class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach ($editingMembers as $member)
            <label class="text-xs text-slate-400">{{ $member->schoolClass->name }}
                <input name="rooms[{{ $member->id }}]" value="{{ $sessions->firstWhere('assessment_subject_id', $member->id)?->room_name }}" maxlength="100" placeholder="Ruangan {{ $member->schoolClass->name }}" class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm">
            </label>
        @endforeach
    </div>
</details>
