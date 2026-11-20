<p id="exam-submit-message" role="status" class="mb-3 text-sm text-rose-300" @if (! $errors->has('submission')) hidden @endif>{{ $errors->first('submission') }}</p>
<div id="exam-submit-backdrop" hidden aria-hidden="true" class="fixed inset-0 z-30 bg-slate-950/85"></div>
<dialog id="exam-submit-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="exam-submit-title" aria-describedby="exam-submit-description"
    class="fixed inset-0 z-40 m-auto max-h-[calc(100%_-_2rem)] w-[calc(100%_-_2rem)] max-w-md overflow-y-auto rounded-2xl border border-slate-500 bg-slate-900 p-5 text-white shadow-xl backdrop:bg-slate-950/85 sm:p-6">
    <h2 id="exam-submit-title" class="text-xl font-semibold">Kumpulkan jawaban ujian?</h2>
    <p id="exam-submit-description" class="mt-3 text-sm leading-6 text-slate-300">Setelah dikumpulkan, jawaban tidak bisa diubah. Pilih Batal untuk kembali memeriksa jawaban.</p>
    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <button type="button" data-submit-cancel autofocus class="rounded-xl border border-white/20 px-4 py-3 text-sm font-semibold">Batal</button>
        <button type="button" data-submit-confirm class="rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950">Kumpulkan sekarang</button>
    </div>
</dialog>
