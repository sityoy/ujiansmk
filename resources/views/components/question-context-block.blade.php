@props(['context', 'label' => null, 'print' => false])

<div {{ $attributes->class($print ? 'shared-statement' : 'exam-statement') }}
    @if (! $print && $attributes->has('id')) tabindex="-1" @endif>
    <p class="{{ $print ? 'text-sm font-semibold' : 'exam-statement-title' }}">
        {{ $label === null ? 'Pernyataan bersama' : 'Pernyataan atau gambar untuk nomor '.$label }}
    </p>
    @if (filled($context->statement))
        <x-question-text :text="$context->statement" :format="$context->text_format" />
    @endif
    @if ($context->image_path)
        @if ($print)
            <x-print-question-attachment :path="$context->image_path" :caption="$context->image_caption" label="Lampiran pernyataan bersama" />
        @else
            <x-question-attachment :path="$context->image_path" :caption="$context->image_caption" alt="Gambar pernyataan bersama" link-label="Buka lampiran pernyataan bersama" />
        @endif
    @endif
</div>
