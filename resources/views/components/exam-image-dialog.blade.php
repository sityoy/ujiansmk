<div id="exam-image-backdrop" hidden aria-hidden="true" class="fixed inset-0 z-30 bg-slate-950/85"></div>
<dialog id="exam-image-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="exam-image-title"
    class="fixed inset-0 z-40 m-auto w-[calc(100%_-_2rem)] max-w-3xl overflow-hidden rounded-2xl border border-slate-500 bg-slate-900 p-0 text-white backdrop:bg-slate-950/85">
    <div class="flex shrink-0 items-center justify-between gap-3 px-4 py-3">
        <h2 id="exam-image-title" class="text-sm font-semibold">Gambar soal</h2>
        <button type="button" data-exam-image-close autofocus
            class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Tutup</button>
    </div>
    <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-white/10 px-4 py-2" role="group" aria-label="Zoom gambar">
        <button type="button" data-exam-image-zoom-in disabled class="exam-image-zoom-button">Perbesar</button>
        <button type="button" data-exam-image-zoom-out disabled class="exam-image-zoom-button">Perkecil</button>
        <button type="button" data-exam-image-zoom-reset disabled class="exam-image-zoom-button">Pas ke layar</button>
        <output data-exam-image-scale aria-label="Skala gambar" class="text-xs font-semibold text-slate-300">100%</output>
    </div>
    <p class="px-4 pb-2 text-xs text-slate-300">Gunakan Perbesar atau Perkecil. Geser gambar dengan satu jari untuk melihat bagian lain.</p>
    <div class="exam-image-viewport bg-white p-3" data-exam-image-viewport tabindex="0" role="region" aria-label="Gambar soal yang dapat digeser">
        <img data-exam-image-preview alt="" draggable="false">
        <p data-exam-image-error hidden class="p-4 text-sm text-slate-800">Gambar gagal dimuat. Tutup dan coba lagi.</p>
    </div>
</dialog>

<script src="{{ asset('js/exam-image-preview.js') }}?v=20261123-2" defer></script>
