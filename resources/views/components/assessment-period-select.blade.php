@props(['periods', 'selected' => null, 'name' => 'assessment_period_id', 'label' => 'Periode ujian', 'required' => false])

<label {{ $attributes->class(['block text-xs font-medium text-slate-400']) }}>{{ $label }}
    <select name="{{ $name }}" @required($required) class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm">
        <option value="">Pilih periode ujian</option>
        @foreach ($periods as $period)
            <option value="{{ $period->id }}" @selected((string) $selected === (string) $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
        @endforeach
    </select>
</label>
