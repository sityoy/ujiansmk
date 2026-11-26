@props(['context', 'label' => null, 'print' => false])

<div {{ $attributes->class($print ? 'shared-statement' : 'rounded-xl border border-cyan-400/30 bg-cyan-400/[0.06] p-3 space-y-2') }}>
    <p class="text-sm font-semibold {{ $print ? '' : 'text-cyan-300' }}">
        {{ $label === null ? 'Pernyataan bersama' : 'Pernyataan atau gambar untuk nomor '.$label }}
    </p>
    @if (filled($context->statement))
        <x-question-text :text="$context->statement" :format="$context->text_format" />
    @endif
    @if ($context->image_path)
        @if ($print)
            <x-print-question-attachment :path="$context->image_path" label="Lampiran pernyataan bersama" />
        @else
            <x-question-attachment :path="$context->image_path" alt="Gambar pernyataan bersama" link-label="Buka lampiran pernyataan bersama" />
        @endif
    @endif
</div>
