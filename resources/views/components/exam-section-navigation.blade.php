@if (! $loop->first || ! $loop->last)
    <div class="flex flex-wrap gap-3">
        @unless ($loop->first)
            <button type="button" data-exam-section-back class="rounded-xl border border-white/20 px-4 py-3 text-sm font-semibold">Bagian sebelumnya</button>
        @endunless
        @unless ($loop->last)
            <button type="button" data-exam-section-next class="rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">
                Lanjut ke {{ $questionSections[$loop->index + 1]['type']->label() }}
            </button>
        @endunless
    </div>
    @unless ($loop->last)
        <p data-exam-section-message role="status" class="text-sm text-rose-300" hidden></p>
    @endunless
@endif
