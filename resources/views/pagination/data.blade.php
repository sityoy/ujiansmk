@php
    $pageName = $paginator->getPageName();
    $sizeName = \App\Support\TablePagination::sizeName($pageName);
    $selectedSize = \App\Support\TablePagination::selected($pageName);
    $fragment = $paginator->fragment();
@endphp
<div class="space-y-3" data-table-pagination="{{ $pageName }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs leading-6 text-slate-400" aria-live="polite">
            Menampilkan {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} data
        </p>
        <form method="GET" action="{{ request()->url() }}{{ $fragment ? '#'.$fragment : '' }}" class="flex flex-wrap items-center gap-2">
            @include('pagination.query-inputs', ['values' => request()->except([$pageName, $sizeName]), 'prefix' => null])
            <label for="{{ $sizeName }}" class="text-xs text-slate-400">Data per halaman</label>
            <select id="{{ $sizeName }}" name="{{ $sizeName }}" onchange="this.form.submit()"
                class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                @foreach (\App\Support\TablePagination::OPTIONS as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSize === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl border border-cyan-400/25 px-3 py-2 text-xs font-semibold text-cyan-200">Tampilkan</button>
        </form>
    </div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Halaman data" class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-500">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300">Sebelumnya</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true" class="px-2 text-slate-500">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="Halaman {{ $page }}" class="rounded-lg bg-cyan-400 px-3 py-2 text-sm font-semibold text-slate-950">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Halaman {{ $page }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300">Berikutnya</a>
            @else
                <span aria-disabled="true" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-500">Berikutnya</span>
            @endif
        </nav>
    @endif
</div>
