@props(['path', 'label'])

<div class="px-4 pb-3 pl-10">
    <x-question-attachment :path="$path" :alt="'Lampiran pilihan '.$label" :link-label="'Buka lampiran pilihan '.$label" />
</div>
