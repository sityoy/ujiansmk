<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nilai {{ $period->name }} - {{ $schoolClass->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111; font-size: 10px; }
        .toolbar { display: flex; justify-content: space-between; padding: 10px 14px; margin-bottom: 12px; color: white; background: #0f172a; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 14px; background: #34d399; font-weight: 700; cursor: pointer; }
        .letterhead { width: 100%; max-height: 30mm; object-fit: contain; margin-bottom: 5mm; }
        h1 { margin: 0; text-align: center; font-size: 16px; }
        .meta { margin: 4mm 0; display: grid; grid-template-columns: 30mm 1fr 30mm 1fr; gap: 2mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 3px; text-align: center; }
        th { background: #e2e8f0; font-size: 8px; }
        td.name { min-width: 42mm; text-align: left; }
        td.number { white-space: nowrap; }
        .signature { width: 60mm; margin: 7mm 0 0 auto; text-align: center; line-height: 1.5; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><span>{{ $period->name }} · {{ $schoolClass->name }}</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    @if ($letterheadData)<img src="{{ $letterheadData }}" alt="Kop sekolah" class="letterhead">@endif
    <h1>REKAP HASIL {{ strtoupper($period->name) }}</h1>
    <div class="meta">
        <strong>Sekolah</strong><span>: {{ $school?->name ?? config('app.name') }}</span>
        <strong>Kelas</strong><span>: {{ $schoolClass->name }}</span>
        <strong>Tahun Ajaran</strong><span>: {{ $period->academicYear->name }}</span>
        <strong>Jenis Asesmen</strong><span>: {{ strtoupper($period->type->value) }}</span>
    </div>
    <table>
        <thead><tr><th>No.</th><th>NIS/NISN</th><th>Nama Peserta Didik</th>
            @foreach ($subjects as $subject)<th>{{ $subject->subject->name }}</th>@endforeach
            <th>Rata-rata</th><th>Rank</th>
        </tr></thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr><td>{{ $index + 1 }}</td><td class="number">{{ $row['student']->student_number }}<br>{{ $row['student']->nisn ?? '—' }}</td><td class="name">{{ $row['student']->full_name }}</td>
                    @foreach ($subjects as $subject)<td>{{ $row['scores'][$subject->id] === null ? '—' : number_format($row['scores'][$subject->id], 2, ',', '.') }}</td>@endforeach
                    <td>{{ $row['average'] === null ? '—' : number_format($row['average'], 2, ',', '.') }}</td><td>{{ $row['rank'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $subjects->count() + 5 }}">Belum ada peserta pada kelas ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="signature">
        <div>{{ $school?->city ?: 'Jakarta' }}, {{ $period->ends_on->translatedFormat('d F Y') }}</div>
        <div>Wali Kelas</div><br><br><br>
        <strong><u>{{ $schoolClass->homeroomTeacher?->name ?? '........................................' }}</u></strong>
    </div>
</body>
</html>
