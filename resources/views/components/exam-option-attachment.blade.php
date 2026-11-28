@props(['path', 'label', 'caption' => null])

<div class="px-4 pb-3 pl-10">
    <x-question-attachment :path="$path" :caption="$caption" :alt="'Lampiran pilihan '.$label" :link-label="'Buka lampiran pilihan '.$label" />
</div>
