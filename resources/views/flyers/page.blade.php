<article class="flyer" data-print-break>
    <header class="cover">
        <div class="brand">
            <x-school-logo :school="$school" class="logo" width="64" height="64" />
            <div class="school">{{ $schoolName }}</div>
        </div>
        <p class="eyebrow">{{ $trial ? 'FLYER SIMULASI UJIAN' : 'FLYER ASESMEN' }}</p>
        <h1>{{ $kind }}</h1>
        <div class="cover-meta">
            <span class="year">TAHUN AJARAN {{ $period->academicYear->name }}</span>
            <span class="subtitle">{{ $subtitle }}</span>
        </div>
    </header>
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
@foreach ($schedule as $grade => $rows)
    <section class="flyer schedule" data-print-break aria-labelledby="schedule-{{ $pageNumber }}-{{ $grade }}">
        <div class="table-wrap">
            <table>
                <colgroup><col style="width:21%"><col style="width:18%"><col style="width:30%"><col style="width:31%"></colgroup>
                <thead>
                    <tr>
                        <th colspan="4" class="schedule-banner">
                            <span class="schedule-label">Jadwal per mapel dan rombel</span>
                            <h2 id="schedule-{{ $pageNumber }}-{{ $grade }}">Kelas {{ $grade }}</h2>
                            <span class="schedule-period">{{ $schoolName }} · {{ $period->name }} · {{ $period->academicYear->name }}</span>
                        </th>
                    </tr>
                    <tr><th scope="col">Mata pelajaran</th><th scope="col">Rombel</th><th scope="col">Jadwal (WIB)</th><th scope="col">Lokasi</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row">{{ $row['subject'] }}</th>
                            <td>{{ $row['rombel'] }}</td>
                            <td>
                                <strong class="session-time">{{ $row['time'] }}</strong>
                                <span class="session-meta">{{ $row['kind'] }} · {{ $row['duration'] }} menit</span>
                            </td>
                            <td>{{ $row['location'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endforeach
