<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Seluruh Ujian Uji Coba</title>
    <style>
        @page { size: A4 portrait; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; background: #0f172a; color: #fff; }
        .toolbar button { border: 0; border-radius: 8px; background: #22d3ee; padding: 9px 16px; font-weight: 700; cursor: pointer; }
        .sheet { width: 192mm; margin: 12px auto; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 6mm; }
        .card { overflow: hidden; border: 1.2px solid #0f172a; border-radius: 10px; background: #fff; break-inside: avoid; page-break-inside: avoid; }
        .head { padding: 10px 12px; color: #fff; background: #0f172a; }
        .school { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .title { margin-top: 3px; font-size: 9px; font-weight: 700; letter-spacing: 1.3px; color: #67e8f9; }
        .body { padding: 11px 12px; font-size: 10px; }
        .name { margin-bottom: 8px; font-size: 14px; font-weight: 700; }
        .identity { display: grid; grid-template-columns: 66px 8px minmax(0, 1fr); margin: 3px 0; }
        .exam-list { margin-top: 9px; display: grid; gap: 7px; }
        .exam { padding: 8px; border: 1px solid #cbd5e1; border-radius: 7px; background: #f8fafc; }
        .exam-head { display: flex; justify-content: space-between; gap: 8px; font-size: 10px; font-weight: 700; }
        .status { white-space: nowrap; font-size: 8px; font-weight: 700; color: #047857; }
        .status.off { color: #64748b; }
        .access { margin-top: 5px; display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 3px 6px; font-size: 9px; }
        .code { font-weight: 700; letter-spacing: .6px; color: #0e7490; }
        .url { overflow-wrap: anywhere; font-size: 7.8px; color: #334155; }
        .note { margin-top: 8px; font-size: 8px; line-height: 1.4; color: #64748b; }
        .empty { grid-column: 1 / -1; padding: 40px; text-align: center; background: #fff; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; gap: 6mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>Kartu seluruh link Uji Coba · {{ $students->count() }} siswa · {{ $trialExams->count() }} tautan</div>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
        @forelse ($students as $student)
            @php($studentTrials = $trialsByGrade->get($student->schoolClass->grade_level, collect()))
            <section class="card">
                <header class="head">
                    <div class="school">{{ $school?->name ?? config('app.name') }}</div>
                    <div class="title">KARTU SELURUH UJIAN UJI COBA</div>
                </header>
                <div class="body">
                    <div class="name">{{ $student->full_name }}</div>
                    <div class="identity"><span>Kelas</span><span>:</span><strong>{{ $student->schoolClass->name }}</strong></div>
                    <div class="identity"><span>NIS</span><span>:</span><span>{{ $student->student_number }}</span></div>
                    <div class="identity"><span>NISN</span><span>:</span><span>{{ $student->nisn ?? '—' }}</span></div>

                    <div class="exam-list">
                        @foreach ($studentTrials as $trialExam)
                            <div class="exam">
                                <div class="exam-head">
                                    <span>{{ $trialExam->questionBank->subject->name }} · {{ $trialExam->title }}</span>
                                    <span class="status {{ $trialExam->is_active ? '' : 'off' }}">{{ $trialExam->is_active ? 'AKTIF' : 'BELUM AKTIF' }}</span>
                                </div>
                                <div class="access">
                                    <span>Kode</span><span class="code">{{ $trialExam->access_code }}</span>
                                    <span>Tautan</span><span class="url">{{ route('tryout.entry', $trialExam) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="note">Masuk menggunakan NIS atau NISN di atas. Nama peserta diambil otomatis dari data sekolah. Gunakan tautan dan kode sesuai mapel yang akan dikerjakan. Nilai Uji Coba tidak ditampilkan.</div>
                </div>
            </section>
        @empty
            <div class="empty">Belum ada siswa aktif atau tautan Ujian Uji Coba yang dapat dicetak.</div>
        @endforelse
    </main>
</body>
</html>
