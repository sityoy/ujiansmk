<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Peserta - {{ $period->name }} - {{ $printLabel }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; gap: 12px; padding: 12px 18px; background: #0f172a; color: white; }
        .toolbar button { border: 0; border-radius: 8px; background: #22d3ee; padding: 9px 16px; font-weight: 700; cursor: pointer; }
        .class-heading { width: 190mm; margin: 16px auto 8px; padding: 10px 0; border-bottom: 1px solid #64748b; font-size: 14px; font-weight: 700; }
        .class-heading small { font-size: 11px; font-weight: normal; }
        .sheet { width: 190mm; margin: 12px auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 7mm; }
        .card { min-height: 61mm; overflow: hidden; border: 1.2px solid #0f172a; border-radius: 10px; background: white; break-inside: avoid; }
        .head { padding: 9px 12px; color: white; background: #0f172a; }
        .school { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .title { margin-top: 3px; font-size: 10px; letter-spacing: 1.5px; color: #67e8f9; }
        .body { padding: 11px 12px; font-size: 11px; }
        .name { margin-bottom: 9px; font-size: 14px; font-weight: 700; }
        .row { display: grid; grid-template-columns: 82px 8px minmax(0, 1fr); margin: 4px 0; }
        .row > :last-child { overflow-wrap: anywhere; }
        .login { margin-top: 9px; padding: 8px; border: 1px dashed #0891b2; border-radius: 7px; background: #ecfeff; }
        .note { margin-top: 7px; font-size: 8.5px; color: #64748b; }
        .empty { grid-column: 1 / -1; padding: 40px; text-align: center; background: white; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .class-section + .class-section { break-before: page; page-break-before: always; }
            .class-heading { width: auto; margin: 0 0 5mm; break-after: avoid; }
            .sheet { width: auto; margin: 0; gap: 7mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $period->name }} · Kartu peserta {{ $printLabel }} · {{ $classGroups->count() }} kelas · {{ $students->count() }} siswa</div>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main>
        @forelse ($classGroups as $schoolClass)
            <section class="class-section">
                <h1 class="class-heading">{{ $schoolClass->name }} · {{ $schoolClass->academicYear->name }} <small>· {{ $schoolClass->cardStudents->count() }} siswa</small></h1>
                <div class="sheet">
                    @foreach ($schoolClass->cardStudents as $student)
                        @php
                            $login = $student->user->username ?: $student->user->email;
                            $password = $student->printablePassword();
                        @endphp
                        <section class="card">
                            <header class="head">
                                <div class="school">{{ $school?->name ?? config('app.name') }}</div>
                                <div class="title">KARTU AKUN PESERTA UJIAN</div>
                            </header>
                            <div class="body">
                                <div class="name">{{ $student->full_name }}</div>
                                <div class="row"><span>Kelas</span><span>:</span><strong>{{ $schoolClass->name }}</strong></div>
                                <div class="row"><span>Periode ujian</span><span>:</span><strong>{{ $period->name }}</strong></div>
                                <div class="row"><span>Ruangan</span><span>:</span><strong>{{ $student->examRooms->first()?->room_name ?? 'Belum diatur' }}</strong></div>
                                <div class="row"><span>Tahun ajaran</span><span>:</span><span>{{ $schoolClass->academicYear->name }}</span></div>
                                <div class="row"><span>NIS / NISN</span><span>:</span><span>{{ $student->student_number }} / {{ $student->nisn ?? '—' }}</span></div>
                                <div class="login">
                                    <div class="row"><span>Username</span><span>:</span><strong>{{ $login }}</strong></div>
                                    <div class="row"><span>Password</span><span>:</span><strong>{{ $password ?? "Diatur khusus; konfirmasi ke panitia" }}</strong></div>
                                </div>
                                <div class="note">Siswa langsung masuk ke halaman ujian setelah login tanpa wajib mengganti password. Simpan kartu ini dan rahasiakan akun.</div>
                            </div>
                        </section>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="empty">Tidak ada siswa aktif dengan akun aktif untuk pilihan kelas dan tahun ajaran ini.</div>
        @endforelse
    </main>
</body>
</html>
