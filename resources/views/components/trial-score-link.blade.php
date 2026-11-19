@props(['trial'])

<a href="{{ route('tryouts.scores.print', $trial) }}" target="_blank" rel="noopener"
    {{ $attributes->class('inline-flex rounded-lg bg-cyan-400 px-3 py-2 text-xs font-semibold text-slate-950') }}>Cetak Nilai</a>
