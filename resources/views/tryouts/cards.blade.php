<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Uji Coba - {{ $trialExam->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 18px; background: #0f172a; color: #fff; }
        .toolbar button { border: 0; border-radius: 8px; background: #22d3ee; padding: 9px 16px; font-weight: 700; cursor: pointer; }
        .sheet { width: 190mm; margin: 12px auto; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7mm; }
        .card { min-height: 80mm; overflow: hidden; border: 1.2px solid #0f172a; border-radius: 10px; background: #fff; break-inside: avoid; page-break-inside: avoid; }
        .head { padding: 10px 12px; color: #fff; background: #0f172a; }
        .head { display: flex; align-items: center; gap: 10px; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .brand-logo { width: 46px; height: 46px; flex-shrink: 0; object-fit: contain; border-radius: 6px; background: #fff; padding: 3px; }
        .brand-copy { min-width: 0; overflow-wrap: anywhere; }
        .school { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .title { margin-top: 3px; font-size: 9px; font-weight: 700; letter-spacing: 1.4px; color: #67e8f9; }
        .body { padding: 11px 12px; font-size: 10.5px; }
        .name { margin-bottom: 8px; font-size: 14px; font-weight: 700; }
        .row { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); margin: 4px 0; }
        .exam { margin-top: 9px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 7px; background: #f8fafc; }
        .access { margin-top: 8px; padding: 8px; border: 1px dashed #0891b2; border-radius: 7px; background: #ecfeff; }
        .url { overflow-wrap: anywhere; font-size: 8.5px; }
        .code { font-size: 13px; letter-spacing: 1px; }
        .note { margin-top: 7px; font-size: 8px; line-height: 1.35; color: #64748b; }
        .empty { grid-column: 1 / -1; padding: 40px; text-align: center; background: #fff; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; gap: 7mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>Kartu {{ $trialExam->title }} · {{ $students->count() }} siswa kelas {{ $trialExam->questionBank->grade_level }}</div>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main class="sheet">
        @forelse ($students as $student)
            <section class="card">
                <header class="head">
                    <x-school-print-heading :school="$school" title="KARTU PESERTA UJIAN UJI COBA" />
                </header>
                <div class="body">
                    <div class="name">{{ $student->full_name }}</div>
                    <div class="row"><span>Kelas</span><span>:</span><strong>{{ $student->schoolClass->name }}</strong></div>
                    <div class="row"><span>Tahun ajaran</span><span>:</span><span>{{ $student->schoolClass->academicYear->name }}</span></div>
                    <div class="row"><span>NIS</span><span>:</span><span>{{ $student->student_number }}</span></div>
                    <div class="row"><span>NISN</span><span>:</span><span>{{ $student->nisn ?? '—' }}</span></div>
                    <div class="exam">
                        <div class="row"><span>Ujian</span><span>:</span><strong>{{ $trialExam->title }}</strong></div>
                        <div class="row"><span>Mapel</span><span>:</span><span>{{ $trialExam->questionBank->subject->name }}</span></div>
                        <div class="row"><span>Durasi</span><span>:</span><span>{{ $trialExam->duration_minutes }} menit</span></div>
                    </div>
                    <div class="access">
                        <div class="row"><span>Kode</span><span>:</span><strong class="code">{{ $trialExam->access_code }}</strong></div>
                        <div class="row"><span>Tautan</span><span>:</span><span class="url">{{ route('tryout.entry', $trialExam) }}</span></div>
                    </div>
                    <div class="note">Masuk menggunakan NIS atau NISN di atas. Nama peserta diambil otomatis dari data sekolah. Nilai Uji Coba tidak ditampilkan kepada peserta.</div>
                </div>
            </section>
        @empty
            <div class="empty">Tidak ada siswa aktif pada tingkat kelas {{ $trialExam->questionBank->grade_level }}.</div>
        @endforelse
    </main>
</body>
</html>
