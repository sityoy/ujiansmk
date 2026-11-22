@props(['bank'])

<form method="POST" action="{{ route('trial-question-banks.destroy', $bank) }}" onsubmit="return confirm('Batalkan penambahan paket ini? Soal dan riwayatnya akan diarsipkan. Tautan simulasi yang memakai paket ini akan dinonaktifkan.')">
    @csrf @method('DELETE')
    <button type="submit" class="rounded-xl border border-rose-400/20 px-3 py-2.5 text-sm text-rose-300">Batalkan penambahan</button>
</form>
