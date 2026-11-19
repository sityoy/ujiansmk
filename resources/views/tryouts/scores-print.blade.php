<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nilai Simulasi · {{ $singleExam?->title ?? 'Semua Mapel' }}</title>
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
        .score-sheet + .score-sheet { margin-top: 24px; }
        @media screen { body { padding: 0 12px 12px; } .table-wrap { overflow-x: auto; } }
        @media print {
            .toolbar { display: none; }
            body { max-width: none; font-size: 10pt; }
            .score-sheet + .score-sheet { break-before: page; margin-top: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $singleExam ? route('tryouts.participants', $singleExam) : route('operations.index').'#pemantauan-uji-coba' }}">Kembali ke peserta</a>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main>
        @forelse ($reports as $report)
            @php
                $trialExam = $report['exam'];
                $rows = $report['rows'];
                $statistics = $report['statistics'];
            @endphp
            <section class="score-sheet">
                <header>
                    <p><strong>{{ $school?->name ?? config('app.name') }}</strong></p>
                    <h1>Rekap Nilai Simulasi Ujian</h1>
                    <p>{{ $trialExam->title }} · {{ $trialExam->questionBank->subject->name }} · Kelas {{ $trialExam->questionBank->grade_level }}</p>
                    @if ($period = $trialExam->assessmentSubject?->assessmentPeriod)
                        <p>{{ $period->name }} · Tahun Ajaran {{ $period->academicYear->name }}</p>
                    @endif
                    <p>{{ $statistics['total'] }} peserta · {{ $statistics['submitted'] }} selesai · {{ $statistics['not_started'] }} belum mengerjakan · {{ $statistics['in_progress'] }} mengerjakan · {{ $statistics['locked'] }} terkunci</p>
                    @if ($statistics['submitted'] === 0)
                        <p>Belum ada peserta yang menyelesaikan simulasi.</p>
                    @endif
                </header>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th class="number" scope="col">No.</th>
                            <th scope="col">NIS / NISN</th>
                            <th scope="col">Nama peserta</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Status</th>
                            <th class="number" scope="col">Benar / Soal</th>
                            <th class="number" scope="col">Nilai</th>
                            <th scope="col">Waktu selesai</th>
                        </tr></thead>
                        <tbody>
                            @php $labels = ['not_started' => 'Belum mengerjakan', 'in_progress' => 'Mengerjakan', 'locked' => 'Terkunci', 'submitted' => 'Selesai']; @endphp
                            @forelse ($rows as $row)
                                @php
                                    $student = $row['student'];
                                    $attempt = $row['attempt'];
                                    $finished = $row['status'] === 'submitted';
                                @endphp
                                <tr>
                                    <td class="number">{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($student)
                                            {{ $student->student_number }} / {{ $student->nisn ?: '—' }}
                                        @else
                                            {{ $attempt?->participant_identifier ?: '—' }}
                                        @endif
                                    </td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $student?->schoolClass?->name ?? '—' }}</td>
                                    <td>{{ $labels[$row['status']] }}</td>
                                    <td class="number">{{ $finished ? $attempt->correct_answers.' / '.$attempt->total_questions : '—' }}</td>
                                    <td class="number">{{ $finished && $attempt->score !== null ? number_format((float) $attempt->score, 2, ',', '.') : '—' }}</td>
                                    <td>{{ $finished ? ($attempt->submitted_at?->format('d/m/Y H:i') ?? '—') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8">Tidak ada peserta pada tingkat kelas ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <h1>Rekap Nilai Simulasi Ujian</h1>
            <p>Belum ada Ujian Uji Coba yang dapat dicetak.</p>
        @endforelse
    </main>
</body>
</html>
