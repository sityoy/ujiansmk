<div id="exam-image-backdrop" hidden aria-hidden="true" class="fixed inset-0 z-30 bg-slate-950/85"></div>
<dialog id="exam-image-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="exam-image-title"
    class="fixed inset-0 z-40 m-auto w-[calc(100%_-_2rem)] max-w-3xl overflow-hidden rounded-2xl border border-slate-500 bg-slate-900 p-0 text-white backdrop:bg-slate-950/85">
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <h2 id="exam-image-title" class="text-sm font-semibold">Gambar soal</h2>
        <button type="button" data-exam-image-close autofocus
            class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Tutup</button>
    </div>
    <div class="flex justify-center bg-white p-3">
        <img data-exam-image-preview alt="" class="max-h-[64vh] max-w-full object-contain">
        <p data-exam-image-error hidden class="p-4 text-sm text-slate-800">Gambar gagal dimuat. Tutup dan coba lagi.</p>
    </div>
</dialog>

<script src="{{ asset('js/exam-image-preview.js') }}?v=20261119-1" defer></script>
