<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapor ATS — {{ $row['student']->full_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; background: #e5e7eb; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 18mm; background: white; }
        .header { text-align: center; border-bottom: 3px double #111827; padding-bottom: 12px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 11px; }
        .title { margin: 24px 0 18px; text-align: center; }
        .title h2 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .title p { margin: 5px 0 0; font-size: 11px; }
        .identity { width: 100%; margin-bottom: 18px; font-size: 12px; border-collapse: collapse; }
        .identity td { padding: 3px 0; vertical-align: top; }
        .identity td:first-child { width: 145px; }
        .scores { width: 100%; border-collapse: collapse; font-size: 12px; }
        .scores th, .scores td { border: 1px solid #111827; padding: 8px; }
        .scores th { background: #f3f4f6; }
        .scores td:last-child { text-align: center; width: 110px; }
        .summary { margin-top: 16px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .summary div { border: 1px solid #9ca3af; padding: 10px; text-align: center; }
        .summary small { display: block; color: #4b5563; font-size: 10px; }
        .summary strong { display: block; margin-top: 4px; font-size: 17px; }
        .note { margin-top: 12px; font-size: 10px; color: #4b5563; }
        .signature { margin-top: 40px; margin-left: auto; width: 240px; text-align: center; font-size: 12px; }
        .signature .space { height: 70px; }
        .actions { width: 210mm; margin: 12px auto; text-align: right; }
        button { border: 0; border-radius: 8px; padding: 10px 16px; color: white; background: #0891b2; cursor: pointer; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: white; }
            .sheet { margin: 0; width: 210mm; min-height: 297mm; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Cetak Rapor</button></div>

    <main class="sheet">
        <header class="header">
            <h1>{{ $schoolProfile?->name ?? 'Nama Sekolah' }}</h1>
            @if ($schoolProfile?->address)
                <p>{{ $schoolProfile->address }}{{ $schoolProfile->city ? ', '.$schoolProfile->city : '' }}</p>
            @endif
            <p>
                @if ($schoolProfile?->npsn) NPSN: {{ $schoolProfile->npsn }} @endif
                @if ($schoolProfile?->phone) · Telp: {{ $schoolProfile->phone }} @endif
                @if ($schoolProfile?->email) · {{ $schoolProfile->email }} @endif
            </p>
        </header>

        <section class="title">
            <h2>Laporan Hasil Asesmen Tengah Semester</h2>
            <p>{{ $period->name }} · Tahun Ajaran {{ $period->academicYear->name }}</p>
        </section>

        <table class="identity">
            <tr><td>Nama Peserta Didik</td><td>: <strong>{{ $row['student']->full_name }}</strong></td></tr>
            <tr><td>Nomor Peserta/Induk</td><td>: {{ $row['student']->student_number }}</td></tr>
            <tr><td>Kelas</td><td>: {{ $schoolClass->name }}</td></tr>
        </table>

        <table class="scores">
            <thead>
                <tr><th style="width: 42px">No.</th><th>Mata Pelajaran</th><th>Nilai</th></tr>
            </thead>
            <tbody>
                @foreach ($subjects as $assessmentSubject)
                    <tr>
                        <td style="text-align:center">{{ $loop->iteration }}</td>
                        <td>{{ $assessmentSubject->subject->name }}</td>
                        <td>{{ $row['scores'][$assessmentSubject->id] === null ? 'Belum ada' : number_format($row['scores'][$assessmentSubject->id], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div><small>Total Nilai</small><strong>{{ number_format($row['total'], 2, ',', '.') }}</strong></div>
            <div><small>Rata-rata</small><strong>{{ number_format($row['average'], 2, ',', '.') }}</strong></div>
            <div><small>Peringkat Kelas</small><strong>{{ $row['rank'] ?? '—' }}</strong></div>
        </div>

        @unless ($row['is_complete'])
            <p class="note">Catatan: masih terdapat nilai mata pelajaran yang belum tersedia. Peringkat pada dokumen ini bersifat sementara.</p>
        @endunless

        <div class="signature">
            <p>{{ $schoolProfile?->city ?? '................' }}, {{ now()->translatedFormat('d F Y') }}</p>
            <p>Kepala Sekolah</p>
            <div class="space"></div>
            <p><strong><u>{{ $schoolProfile?->principal_name ?? '................................' }}</u></strong></p>
        </div>
    </main>
</body>
</html>
