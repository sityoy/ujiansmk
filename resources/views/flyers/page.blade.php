<article class="flyer">
    <header class="brand">
        <img class="logo" src="{{ asset('images/logo-ypmi-bahagia.jpeg') }}" alt="Logo YPMI Bahagia" width="64" height="64">
        <div class="school">{{ $schoolName }}</div>
    </header>
    <p class="eyebrow">{{ $trial ? 'FLYER SIMULASI UJIAN' : 'FLYER ASESMEN' }}</p>
    <h1>{{ $kind }}</h1>
    <div class="year">TAHUN AJARAN {{ $period->academicYear->name }}</div>
    <p class="subtitle">{{ $subtitle }}</p>
    @if ($notice)<p class="notice">{{ $notice }}</p>@endif
    <section class="details">
        <h2>{{ $headline }}</h2>
        @if ($headline !== $period->name)
            <div><p class="label">Periode ujian</p><p class="value">{{ $period->name }}</p></div>
        @endif
        @foreach ($details as [$label, $value])
            <div><p class="label">{{ $label }}</p><p class="value">{{ $value }}</p></div>
        @endforeach
    </section>
    <section class="access">
        @if ($trial)<div><p class="label">Kode uji coba</p><p class="code">{{ $code }}</p></div>@endif
        <div><p class="label">{{ $trial ? 'Tautan uji coba' : 'Tautan masuk peserta' }}</p><a class="url" href="{{ $entryUrl }}">{{ $entryUrl }}</a></div>
    </section>
    @include('flyers.rules')
</article>
@if ($schedule->isNotEmpty())
    <section class="flyer schedule" aria-labelledby="schedule-title">
        <h2 id="schedule-title">Jadwal per mapel dan rombel</h2>
        <p class="subtitle schedule-heading">{{ $period->name }} · {{ $period->academicYear->name }}</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Mata pelajaran dan rombel</th><th scope="col">Sesi</th><th scope="col">Tanggal dan waktu</th><th scope="col">Lokasi dan ruangan</th></tr></thead>
                <tbody>
                    @foreach ($schedule as $row)
                        <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
