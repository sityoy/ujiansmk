<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absensi Simulasi · {{ $trialExam->title }}</title>
    <style>
        @page { size: A4; margin: 13mm; }
        * { box-sizing: border-box; }
        body { margin: auto; max-width: 960px; font: 11pt/1.5 Arial, sans-serif; color: #172033; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding: 10px 0; }
        button { background: #164e63; color: #fff; border: 0; border-radius: 6px; padding: 8px 12px; cursor: pointer; }
        h1 { font-size: 19pt; margin: 0 0 4px; }
        h2 { font-size: 13pt; margin: 18px 0 6px; break-after: avoid; }
        p { margin: 0 0 4px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #64748b; text-align: left; padding: 5px 7px; }
        th { background: #e8eff2; }
        tr { break-inside: avoid; }
        thead { display: table-header-group; }
        .table-wrap { overflow-x: auto; }
        @media screen { body { padding: 0 12px 12px; } }
        @media print { .toolbar { display: none; } .table-wrap { overflow: visible; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('tryouts.participants', $trialExam) }}">← Pemantauan peserta</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <header>
        <p><strong>{{ $school?->name ?? config('app.name') }}</strong></p>
        <h1>Laporan Absensi Simulasi</h1>
        <p>{{ $trialExam->title }} · {{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
        <p>Lokasi {{ $trialExam->location->name }} · Dicetak {{ now()->format('d/m/Y H:i') }}</p>
        <p>{{ $total }} siswa · {{ $presentCount }} hadir terverifikasi · {{ $total - $presentCount }} belum absen</p>
    </header>
    @forelse ($classes as $className => $rows)
        <section>
            <h2>{{ $className }}</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">NIS</th>
                            <th scope="col">Nama siswa</th>
                            <th scope="col">Status</th>
                            <th scope="col">Waktu verifikasi</th>
                            <th scope="col">Jarak / akurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($checkin = $row['checkin'])
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['student']->student_number }}</td>
                                <td>{{ $row['student']->full_name }}</td>
                                <td>{{ $checkin ? 'Hadir terverifikasi' : 'Belum absen' }}</td>
                                <td>{{ $checkin?->verified_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                <td>{{ $checkin ? round((float) $checkin->distance_meters).' m / ±'.round((float) $checkin->accuracy_meters).' m' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p>Belum ada siswa pada tingkat ini.</p>
    @endforelse
</body>
</html>
