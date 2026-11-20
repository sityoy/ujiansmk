@props(['incidents'])

<details class="mt-2 text-xs">
    <summary class="cursor-pointer text-cyan-300">Riwayat pengawasan</summary>
    @forelse ($incidents as $incident)
        <p class="mt-3 leading-5"><x-exam-incident-entry :incident="$incident" /></p>
    @empty
        <p class="mt-2 text-slate-500">Belum ada catatan.</p>
    @endforelse
</details>
