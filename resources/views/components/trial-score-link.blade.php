@props(['trial' => null])

<a href="{{ $trial ? route('tryouts.scores.print', $trial) : route('tryouts.scores.all') }}" target="_blank" rel="noopener"
    {{ $attributes->class('inline-flex rounded-lg bg-cyan-400 px-3 py-2 text-xs font-semibold text-slate-950') }}>
    {{ $trial ? 'Cetak Nilai' : 'Cetak Nilai Semua Mapel' }}
</a>
