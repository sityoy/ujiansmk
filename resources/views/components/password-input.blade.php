<div data-password-field class="relative min-w-0">
    <input type="password" {{ $attributes->class('w-full pr-12') }}>
    <button type="button" data-password-toggle
        aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi"
        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-xl text-slate-400 hover:text-cyan-300 focus-visible:outline-2 focus-visible:outline-cyan-400">
        <svg data-password-show aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
            <circle cx="12" cy="12" r="2.5" />
        </svg>
        <svg data-password-hide aria-hidden="true" hidden class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3l18 18M10.6 6.1C11.1 6 11.5 6 12 6c6.5 0 10 6 10 6a16 16 0 0 1-3.1 3.5M6.2 7.7A16 16 0 0 0 2 12s3.5 6 10 6c1.5 0 2.8-.3 4-.8" />
        </svg>
    </button>
</div>
