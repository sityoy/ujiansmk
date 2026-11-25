<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap {{ $reportKind ?? "Bank Soal Inti" }} · {{ $gradeLabel }}</title>
    @vite('resources/js/question-bank-report.js')
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #0f172a; font: 13px/1.5 Arial, sans-serif; }
        .tools { max-width: 1180px; margin: 20px auto; padding: 16px; background: white; border: 1px solid #94a3b8; border-radius: 12px; }
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        button, .back { font: inherit; padding: 9px 16px; border: 1px solid #475569; border-radius: 8px; background: white; color: #0f172a; cursor: pointer; text-decoration: none; }
        button.primary { color: white; background: #334155; }
        button:disabled { opacity: .5; cursor: wait; }
        .tools p { margin: 10px 0 0; color: #475569; }
        .sheet { max-width: 1180px; margin: 20px auto; padding: 28px; background: white; border: 1px solid #94a3b8; }
        .brand { display: flex; align-items: center; gap: 18px; border-bottom: 3px solid #334155; padding-bottom: 16px; }
        .brand img { width: 64px; height: 64px; object-fit: contain; }
        h1 { margin: 0; font-size: 19px; } .brand p { margin: 4px 0 0; } .muted { color: #475569; }
        .meta { margin: 16px 0; } .totals { padding: 12px; border: 1px solid #94a3b8; background: #f1f5f9; }
        .group { margin-top: 24px; } h2 { margin: 0; font-size: 17px; } .group > p { margin: 4px 0; }
        .group > .deadline { margin-bottom: 12px; }
        .table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border: 1px solid #94a3b8; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e2e8f0; font-size: 12px; } tbody tr:nth-child(even) { background: #f8fafc; }
        .num { text-align: center; } .detail { white-space: pre-line; } .status { font-size: 11px; } .note { margin-top: 24px; font-size: 11px; color: #475569; }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { background: white; font-size: 10px; } .tools { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border: 0; }
            .group + .group { break-before: page; } tr { break-inside: avoid; } thead { display: table-header-group; }
            .table-wrap { overflow: visible; } th, td { padding: 6px; }
        }
    </style>
</head>
<body>
    <div class="tools">
        <div class="actions">
            <button type="button" class="primary" data-download-report="pdf">Unduh PDF</button>
            <button type="button" data-download-report="png">Unduh gambar PNG</button>
            <button type="button" onclick="window.print()">Cetak</button>
            <a class="back" href="{{ $backUrl ?? route('question-bank.index', array_merge(request()->only(['period_id', 'grade_level', 'q', 'question_status']), ['view' => 'summary'])) }}">Kembali ke rekap</a>
        </div>
        <p>Unduhan memuat seluruh data sesuai filter, dikelompokkan {{ $groupingLabel ?? 'per tingkat dan periode' }}. Gambar yang terdiri dari beberapa halaman diunduh dalam satu file ZIP.</p>
        <p id="report-status" role="status" aria-live="polite"></p>
        <noscript><p>Aktifkan JavaScript untuk mengunduh PDF atau PNG. Tombol Cetak tetap dapat digunakan melalui menu cetak browser.</p></noscript>
    </div>
    <main class="sheet">
        <header class="brand">
            <img id="report-logo" src="{{ asset(config('tenant.npsn') ? 'images/logo-pt-sityoy.png' : 'images/logo-ypmi-bahagia.jpeg') }}" alt="Logo sekolah">
            <div><h1>{{ $school?->name ?? config('app.name') }}</h1><p><strong>REKAP PEMBUATAN SOAL · {{ mb_strtoupper($reportKind ?? 'Bank Soal Inti') }}</strong></p><p class="muted">{{ $school?->address }}</p></div>
        </header>
        <div class="meta"><strong>{{ $gradeLabel }} · {{ $periodLabel }}</strong>
            @if ($filterLabel)<p>{{ $filterLabel }}</p>@endif
            <p class="muted">Dibuat {{ $generatedAt }}</p>
        </div>
        <p class="totals">{{ $totals['banks'] }} bank soal · {{ $totals['filled'] }} sudah ada soal · {{ $totals['empty'] }} belum ada soal · <strong>{{ $totals['questions'] }} soal</strong></p>
        @forelse ($groups as $group)
            <section class="group">
                <h2>{{ $group['title'] }}</h2><p class="muted">{{ $group['subtitle'] }}</p>
                @if (isset($group['deadline']))
                    <p class="deadline"><strong>Batas pembuatan soal:</strong> {{ $group['deadline'] }}</p>
                @endif
                <div class="table-wrap"><table>
                    <thead><tr><th>No.</th><th>Guru penanggung jawab</th><th>Mata pelajaran</th><th>{{ $detailColumn ?? 'Rombel' }}</th><th class="num">PG</th><th class="num">Esai</th><th class="num">Isian singkat</th><th class="num">Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr><td class="num">{{ $loop->iteration }}</td>
                                @foreach ($row as $index => $value)<td class="{{ $index >= 3 && $index <= 6 ? 'num' : '' }} {{ $index === 7 ? 'status' : ($index === 2 ? 'detail' : '') }}">{{ $value }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="4">Jumlah · {{ count($group['rows']) }} bank soal</th>
                        @foreach ([3, 4, 5, 6] as $index)<th class="num">{{ array_sum(array_column($group['rows'], $index)) }}</th>@endforeach
                        <th></th></tr></tfoot>
                </table></div>
            </section>
        @empty
            <p>Tidak ada bank soal sesuai filter.</p>
            @if ($emptyDeadline)
                <p><strong>Batas pembuatan soal:</strong> {{ $emptyDeadline }}</p>
            @endif
        @endforelse
        <p class="note">{{ $note ?? 'Bank bersama antarrombel dihitung satu kali. Nama guru mengikuti penugasan di Penjadwalan. Rekap ini memuat jumlah soal dan tidak memuat isi atau kunci jawaban.' }}</p>
    </main>
    <script type="application/json" id="question-bank-report-data">{!! \Illuminate\Support\Js::encode([
        'school' => $school?->name ?? config('app.name'), 'address' => $school?->address ?? '',
        'scope' => $gradeLabel.' · '.$periodLabel, 'filter' => $filterLabel,
        'generatedAt' => $generatedAt, 'filename' => $filename, 'totals' => $totals, 'groups' => $groups,
        'emptyDeadline' => $emptyDeadline,
        'reportKind' => $reportKind ?? null, 'detailColumn' => $detailColumn ?? null,
    ]) !!}</script>
</body>
</html>
