<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nilai Simulasi · {{ $trialExam->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; max-width: 1120px; font: 11pt/1.4 Arial, sans-serif; color: #172033; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 0; }
        .toolbar button { border: 1px solid #0f172a; border-radius: 6px; background: #0f172a; color: white; padding: 8px 12px; cursor: pointer; }
        header { padding: 12px 0; border-bottom: 2px solid #17434b; margin-bottom: 12px; }
        h1, p { margin: 0 0 4px; }
        h1 { font-size: 19pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 7px; border: 1px solid #64748b; }
        th { text-align: left; background: #e4edf0; }
        .number { text-align: center; white-space: nowrap; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        @media screen { body { padding: 0 12px 12px; } .table-wrap { overflow-x: auto; } }
        @media print { .toolbar { display: none; } body { max-width: none; font-size: 10pt; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('tryouts.participants', $trialExam) }}">Kembali ke peserta</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <header>
        <p><strong>{{ $school?->name ?? config('app.name') }}</strong></p>
        <h1>Rekap Nilai Simulasi Ujian</h1>
        <p>{{ $trialExam->title }} · {{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
        @if ($period = $trialExam->assessmentSubject?->assessmentPeriod)
            <p>{{ $period->name }} · Tahun Ajaran {{ $period->academicYear->name }}</p>
        @endif
        <p>{{ $attempts->count() }} peserta selesai · Dokumen internal petugas</p>
    </header>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th class="number" scope="col">No.</th>
                <th scope="col">NIS / NISN</th>
                <th scope="col">Nama peserta</th>
                <th scope="col">Kelas</th>
                <th class="number" scope="col">Benar / Soal</th>
                <th class="number" scope="col">Nilai</th>
                <th scope="col">Waktu selesai</th>
            </tr></thead>
            <tbody>
                @forelse ($attempts as $attempt)
                    <tr>
                        <td class="number">{{ $loop->iteration }}</td>
                        <td>
                            @if ($attempt->student)
                                {{ $attempt->student->student_number }} / {{ $attempt->student->nisn ?: '—' }}
                            @else
                                {{ $attempt->participant_identifier ?: '—' }}
                            @endif
                        </td>
                        <td>{{ $attempt->participant_name }}</td>
                        <td>{{ $attempt->student?->schoolClass?->name ?? '—' }}</td>
                        <td class="number">{{ $attempt->correct_answers }} / {{ $attempt->total_questions }}</td>
                        <td class="number">{{ $attempt->score === null ? '—' : number_format((float) $attempt->score, 2, ',', '.') }}</td>
                        <td>{{ $attempt->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada peserta yang menyelesaikan simulasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
