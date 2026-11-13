@props(['name', 'value' => '', 'format' => 'plain', 'label' => 'Teks soal', 'rows' => 4, 'limit' => 5000, 'showLabel' => true])
@php($editorId = 'question-editor-'.Str::uuid())

<div data-question-editor-field @if ($rows <= 2) data-compact @endif>
    @if ($showLabel)
        <label for="{{ $editorId }}" class="mb-2 block text-xs text-slate-400">{{ $label }}</label>
    @endif
    <textarea id="{{ $editorId }}" name="{{ $name }}" rows="{{ $rows }}" maxlength="{{ $limit }}"
        data-question-editor data-text-format="{{ $format }}" aria-label="{{ $label }}"
        {{ $attributes->class('block w-full rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400') }}>{{ $value }}</textarea>
    <p data-editor-error hidden role="alert" class="mt-2 text-xs text-rose-300"></p>
</div>
