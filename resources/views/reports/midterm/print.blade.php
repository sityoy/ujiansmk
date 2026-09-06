<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapor ATS — {{ $row['student']->full_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; background: #e5e7eb; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm; background: white; }
        .header { text-align: center; }
        .letterhead { display: block; width: 100%; height: auto; max-height: 36mm; object-fit: contain; }
        .fallback-header { border-bottom: 3px double #111827; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 11px; }
        .title { margin: 14px 0 18px; text-align: center; }
        .title h2 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .title p { margin: 5px 0 0; font-size: 11px; }
        .identity-layout { display: grid; grid-template-columns: 1.55fr 1fr; gap: 28px; margin: 14px 0 0; padding-bottom: 12px; border-bottom: 1px solid #9ca3af; }
        .identity { width: 100%; font-size: 12px; border-collapse: collapse; }
        .identity td { padding: 2px 0; vertical-align: top; }
        .identity td:first-child { width: 120px; }
        .identity-layout .identity:last-child td:first-child { width: 90px; }
        .section-title { margin: 16px 0 6px; font-size: 12px; font-weight: bold; }
        .scores { width: 100%; border-collapse: collapse; font-size: 10px; }
        .scores th, .scores td { border: 1px solid #111827; padding: 6px; vertical-align: top; }
        .scores th { background: #f3f4f6; }
        .score-number { text-align: center; width: 58px; }
        .description { line-height: 1.45; }
        .summary { margin-top: 16px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .summary div { border: 1px solid #9ca3af; padding: 10px; text-align: center; }
        .summary small { display: block; color: #4b5563; font-size: 10px; }
        .summary strong { display: block; margin-top: 4px; font-size: 17px; }
        .note { margin-top: 12px; font-size: 10px; color: #4b5563; }
        .attendance-layout { display: grid; grid-template-columns: 1fr 1.4fr; gap: 12px; align-items: start; }
        .signature { margin-top: 30px; font-size: 11px; page-break-inside: avoid; }
        .signature-top { display: grid; grid-template-columns: 1fr 1fr; gap: 34%; }
        .signature-bottom { width: 42%; margin: 12px auto 0; text-align: center; }
        .signature-box { text-align: left; }
        .signature-top .signature-box:last-child { justify-self: end; min-width: 220px; }
        .signature .space { height: 64px; }
        .signature-bottom .space { height: 62px; }
        .avoid-break { page-break-inside: avoid; }
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
            @if ($letterheadData)
                <img src="{{ $letterheadData }}" alt="KOP {{ $schoolProfile?->name }}" class="letterhead">
            @else
                <div class="fallback-header">
                    <h1>{{ $schoolProfile?->name ?? 'Nama Sekolah' }}</h1>
                    @if ($schoolProfile?->address)
                        <p>{{ $schoolProfile->address }}{{ $schoolProfile->city ? ', '.$schoolProfile->city : '' }}</p>
                    @endif
                    <p>
                        @if ($schoolProfile?->npsn) NPSN: {{ $schoolProfile->npsn }} @endif
                        @if ($schoolProfile?->phone) · Telp: {{ $schoolProfile->phone }} @endif
                        @if ($schoolProfile?->email) · {{ $schoolProfile->email }} @endif
                    </p>
                </div>
            @endif
        </header>

        @php
            $phase = match (true) {
                $schoolClass->grade_level <= 2 => 'A',
                $schoolClass->grade_level <= 4 => 'B',
                $schoolClass->grade_level <= 6 => 'C',
                $schoolClass->grade_level <= 9 => 'D',
                $schoolClass->grade_level === 10 => 'E',
                default => 'F',
            };
            $semesterNumber = $period->semester === \App\Enums\Semester::Odd ? 1 : 2;
        @endphp

        <div class="identity-layout">
            <table class="identity">
                <tr><td>Nama Murid</td><td>: <strong>{{ $row['student']->full_name }}</strong></td></tr>
                <tr><td>NIS/NISN</td><td>: {{ $row['student']->student_number }} / {{ $row['student']->nisn ?? '—' }}</td></tr>
                <tr><td>Sekolah</td><td>: {{ $schoolProfile?->name ?? 'Nama Sekolah' }}</td></tr>
                <tr><td>Alamat</td><td>: {{ $schoolProfile?->address ?? '—' }}</td></tr>
            </table>
            <table class="identity">
                <tr><td>Kelas</td><td>: {{ $schoolClass->name }}</td></tr>
                <tr><td>Fase</td><td>: {{ $phase }}</td></tr>
                <tr><td>Semester</td><td>: {{ $semesterNumber }}</td></tr>
                <tr><td>Tahun Ajaran</td><td>: {{ $period->academicYear->name }}</td></tr>
            </table>
        </div>

        <section class="title">
            <h2>Laporan Hasil Belajar</h2>
            <p>Asesmen Tengah Semester · {{ $period->name }}</p>
        </section>

        <p class="section-title">A. Capaian Hasil Belajar</p>
        <table class="scores">
            <thead>
                <tr><th style="width: 36px">No.</th><th style="width: 145px">Mata Pelajaran</th><th style="width: 58px">Nilai Akhir</th><th>Capaian Kompetensi</th></tr>
            </thead>
            <tbody>
                @foreach ($subjects as $assessmentSubject)
                    <tr>
                        <td style="text-align:center">{{ $loop->iteration }}</td>
                        <td>{{ $assessmentSubject->subject->name }}</td>
                        <td class="score-number">{{ $row['scores'][$assessmentSubject->id] === null ? '—' : number_format($row['scores'][$assessmentSubject->id], 2, ',', '.') }}</td>
                        <td class="description">{{ $row['descriptions'][$assessmentSubject->id] ?? 'Belum diisi oleh guru mata pelajaran.' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div><small>Total Nilai</small><strong>{{ number_format($row['total'], 2, ',', '.') }}</strong></div>
            <div><small>Rata-rata</small><strong>{{ number_format($row['average'], 2, ',', '.') }}</strong></div>
            <div><small>Peringkat Kelas</small><strong>{{ $row['rank'] ?? '—' }}</strong></div>
        </div>

        <div class="avoid-break">
            <p class="section-title">B. Ekstrakurikuler</p>
            <table class="scores">
                <thead><tr><th style="width: 36px">No.</th><th style="width: 145px">Kegiatan</th><th style="width: 90px">Predikat</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($row['extracurriculars'] as $item)
                        <tr>
                            <td class="score-number">{{ $loop->iteration }}</td>
                            <td>{{ $item['activity']->name }}</td>
                            <td class="score-number">{{ $item['rating']?->label() ?? '—' }}</td>
                            <td class="description">{{ $item['description'] ?? 'Belum dinilai oleh guru pembina.' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center">Tidak ada kegiatan ekstrakurikuler yang tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="attendance-layout avoid-break">
            <div>
                <p class="section-title">C. Ketidakhadiran</p>
                <table class="scores">
                    <tr><td>Sakit</td><td class="score-number">{{ $row['attendance']->sick_days }} hari</td></tr>
                    <tr><td>Izin</td><td class="score-number">{{ $row['attendance']->excused_days }} hari</td></tr>
                    <tr><td>Tanpa Keterangan</td><td class="score-number">{{ $row['attendance']->unexcused_days }} hari</td></tr>
                </table>
            </div>
            <div>
                <p class="section-title">D. Catatan Wali Kelas</p>
                <table class="scores"><tr><td style="height:74px">{{ $row['attendance']->notes ?: '—' }}</td></tr></table>
            </div>
        </div>

        @unless ($row['is_complete'])
            <p class="note">Catatan: masih terdapat nilai mata pelajaran yang belum tersedia. Peringkat pada dokumen ini bersifat sementara.</p>
        @endunless

        <div class="signature">
            <div class="signature-top">
                <div class="signature-box">
                    <p>Orang Tua/Wali</p>
                    <div class="space"></div>
                    <p><strong><u>................................</u></strong></p>
                </div>
                <div class="signature-box">
                    <p>{{ $schoolProfile?->city ?? '................' }}, {{ now()->translatedFormat('d F Y') }}<br>Wali Kelas</p>
                    <div class="space"></div>
                    <p><strong><u>{{ $schoolClass->homeroomTeacher?->name ?? '................................' }}</u></strong></p>
                </div>
            </div>
            <div class="signature-bottom">
                <p>Mengetahui,<br>Kepala Sekolah</p>
                <div class="space"></div>
                <p><strong><u>{{ $schoolProfile?->principal_name ?? '................................' }}</u></strong></p>
            </div>
        </div>
    </main>
</body>
</html>
