<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Nilai {{ $period->name }} - {{ $schoolClass->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111; font-size: 10px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 10px 14px; background: #0f172a; color: white; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 14px; background: #22d3ee; font-weight: 700; cursor: pointer; }
        .letterhead { width: 100%; max-height: 30mm; object-fit: contain; margin-bottom: 5mm; }
        h1 { margin: 0; text-align: center; font-size: 16px; }
        .meta { margin: 4mm 0; display: grid; grid-template-columns: 30mm 1fr 30mm 1fr; gap: 2mm; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { border: 1px solid #333; padding: 4px 3px; text-align: center; }
        th { background: #e2e8f0; font-size: 8px; }
        td.name { min-width: 42mm; text-align: left; }
        td.number { white-space: nowrap; }
        .footer { margin-top: 6mm; display: flex; justify-content: flex-end; }
        .signature { width: 60mm; text-align: center; line-height: 1.5; }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Rekap nilai {{ $period->name }} · {{ $schoolClass->name }}</span>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    @if ($letterheadData)
        <img src="{{ $letterheadData }}" alt="Kop sekolah" class="letterhead">
    @endif

    <h1>REKAP NILAI {{ strtoupper($period->name) }}</h1>
    <div class="meta">
        <strong>Sekolah</strong><span>: {{ $school?->name ?? config('app.name') }}</span>
        <strong>Kelas</strong><span>: {{ $schoolClass->name }}</span>
        <strong>Tahun Ajaran</strong><span>: {{ $period->academicYear->name }}</span>
        <strong>Semester</strong><span>: {{ $period->semester->value === 'odd' ? 'Ganjil' : 'Genap' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>NIS/NISN</th>
                <th>Nama Peserta Didik</th>
                @foreach ($subjects as $assessmentSubject)
                    <th>{{ $assessmentSubject->subject->name }}</th>
                @endforeach
                <th>Rata-rata</th>
                <th>Rank</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="number">{{ $row['student']->student_number }}<br>{{ $row['student']->nisn ?? '—' }}</td>
                    <td class="name">{{ $row['student']->full_name }}</td>
                    @foreach ($subjects as $assessmentSubject)
                        <td>{{ $row['scores'][$assessmentSubject->id] === null ? '—' : number_format($row['scores'][$assessmentSubject->id], 2, ',', '.') }}</td>
                    @endforeach
                    <td><strong>{{ number_format($row['average'], 2, ',', '.') }}</strong></td>
                    <td><strong>{{ $row['rank'] ?? '—' }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="{{ $subjects->count() + 5 }}">Belum ada data nilai pada periode dan kelas ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">
            <div>{{ $period->report_place ?: ($school?->city ?: 'Jakarta') }}, {{ ($period->report_date ?? $period->ends_on)->translatedFormat('d F Y') }}</div>
            <div>Wali Kelas</div>
            <br><br><br>
            <strong><u>{{ $schoolClass->homeroomTeacher?->name ?? '........................................' }}</u></strong>
        </div>
    </div>
</body>
</html>
