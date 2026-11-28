<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Jadwal Ujian</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0 12px; background: #e2e8f0; color: #0f172a; font: 13px/1.5 Arial, sans-serif; }
        .tools, .sheet { max-width: 1180px; margin: 16px auto; padding: 16px; border: 1px solid #94a3b8; background: white; }
        .tools { border-radius: 12px; }
        .tools > p, .tools details { margin: 12px 0 0; }
        .paper-size { color: #475569; font-size: 12px; font-weight: 600; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
        .filters { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; padding: 12px 0; margin-top: 6px; border-bottom: 1px solid #cbd5e1; }
        label { display: grid; gap: 4px; min-width: 12ch; max-width: 100%; font-weight: 600; }
        select, input, textarea, .button { max-width: 100%; padding: 8px 10px; border: 1px solid #64748b; border-radius: 7px; color: inherit; background: white; font: inherit; }
        .button { display: inline-block; font-weight: 600; text-decoration: none; cursor: pointer; }
        .primary { background: #334155; color: white; }
        summary { font-weight: 600; cursor: pointer; }
        textarea { width: 100%; resize: vertical; margin: 8px 0; }
        .sheet { max-width: calc(277mm + 34px); padding: 16px; font-size: 12px; line-height: 1.45; }
        .brand { display: flex; align-items: center; gap: 14px; border-bottom: 3px solid #334155; padding-bottom: 12px; }
        .brand-logo { flex: none; width: 50px; height: 50px; }
        .brand .school { font-weight: 700; font-size: 19px; }
        .brand .title { font-weight: 700; font-size: 14px; }
        .meta { margin: 12px 0; color: #475569; }
        .meta p { margin: 4px 0; }
        .total { padding: 8px 10px; border: 1px solid #94a3b8; background: #f1f5f9; }
        .draft-note { padding: 8px 10px; border: 1px solid #c08744; background: #fff5df; color: #713f12; }
        .table-wrap { overflow-x: auto; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 6px 7px; border: 1px solid #94a3b8; text-align: left; vertical-align: top; }
        .session-date, .session-time { display: block; }
        .session-time { margin-top: 2px; font-weight: 600; font-variant-numeric: tabular-nums; }
        .session-duration { display: block; color: #475569; }
        .session-subject { font-weight: 600; }
        .report-sheet th.period { padding: 8px; background: #cbd5e1; font-size: 13px; }
        .num { text-align: center; }
        .location + .location { margin-top: 4px; }
        .is-draft { color: #854d0e; font-weight: 700; }
        .note { margin-bottom: 0; color: #475569; }
        tr { break-inside: avoid; }
        @media screen {
            .table-wrap table { min-width: 840px; }
            .document-export .table-wrap table { min-width: 0; }
        }
        @page { size: A4 landscape; margin: 10mm; }
        @media print {
            body { padding: 0; background: white; }
            .tools { display: none; }
            .sheet { margin: 0; padding: 0; border: 0; max-width: none; }
            .table-wrap { overflow: visible; }
        }
    </style>
</head>
<body>
    <header class="tools">
        <div class="toolbar">
            <a class="button" href="{{ $backUrl }}">Kembali ke Penjadwalan</a>
            @if ($sessionCount)
                <x-print-actions :filename="$filename" landscape :margin="10" />
                <span class="paper-size">A4 lanskap (297 × 210 mm)</span>
            @endif
        </div>
        <form method="GET" action="{{ route('scheduling.recap') }}" class="filters">
            <label>Periode
                <select name="session_period_id">
                    <option value="">Semua periode</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected((int) ($filters['session_period_id'] ?? 0) === $period->id)>{{ $period->name }} · {{ $period->academicYear->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kelas
                <select name="session_grade_level">
                    <option value="">Semua kelas</option>
                    @foreach ($gradeOptions as $level => $label)
                        <option value="{{ $level }}" @selected($gradeLevel === $level)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Hari
                <select name="session_weekday">
                    <option value="">Semua hari</option>
                    @foreach ($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected($weekday === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Mapel atau kode
                <input name="session_search" value="{{ $filters['session_search'] ?? '' }}" maxlength="255">
            </label>
            <label>Jenis sesi
                <select name="session_kind">
                    <option value="">Semua jenis</option>
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['session_kind'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="session_status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['session_status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button primary">Tampilkan</button>
            <a class="button" href="{{ route('scheduling.recap') }}">Reset</a>
        </form>
        @if ($sessionCount)
            <p>Untuk pengumuman siswa, pilih status <strong>Terbit atau aktif</strong>. Unduhan mengikuti filter di atas. Beberapa halaman PNG diunduh sebagai ZIP.</p>
            <details>
                <summary>Teks untuk WhatsApp</summary>
                <textarea id="schedule-share" aria-label="Teks jadwal untuk WhatsApp" readonly rows="8">{{ $shareText }}</textarea>
                <button type="button" id="copy-schedule" class="button" hidden>Salin teks jadwal</button>
                <span id="copy-result" role="status"></span>
            </details>
        @endif
    </header>

    <main class="sheet report-sheet" data-print-content data-print-table-pages aria-label="Rekap Jadwal Ujian">
        <header class="brand" data-print-block>
            <x-school-print-heading :school="$school" title="REKAP JADWAL UJIAN" />
        </header>
        <div class="meta" data-print-block>
            <p><strong>{{ $scope }}</strong></p>
            @if ($filterLabel)
                <p>{{ $filterLabel }}</p>
            @endif
            <p>Dibuat {{ $generatedAt }} · Seluruh jam ditampilkan dalam WIB</p>
        </div>
        <p class="total" data-print-block>{{ $sessionCount }} sesi tercatat · {{ $sessionCount - $makeupCount }} reguler · {{ $makeupCount }} susulan</p>
        @if ($hasDraft)
            <p class="draft-note" data-print-block><strong>Rekap memuat jadwal draf.</strong> Tunggu penerbitan panitia sebelum menggunakan jadwal yang berstatus Draf.</p>
        @endif
        @forelse ($groups as $group)
            <div class="table-wrap" role="region" aria-label="{{ $group['period'] }}" tabindex="0">
                <table aria-label="{{ $group['period'] }}">
                    <colgroup>
                        <col style="width:4%"><col style="width:21%"><col style="width:22%"><col style="width:9%">
                        <col style="width:8%"><col style="width:21%"><col style="width:7%"><col style="width:8%">
                    </colgroup>
                    <thead>
                        <tr><th colspan="8" class="period">{{ $group['period'] }}</th></tr>
                        <tr>
                            <th scope="col" class="num">No.</th><th scope="col">Jadwal (WIB)</th>
                            <th scope="col">Mata pelajaran</th><th scope="col">Kelas</th><th scope="col">Jenis sesi</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col" class="num">Peserta</th><th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td class="num">{{ $loop->iteration }}</td>
                                <td>
                                    <strong class="session-date">{{ $row['date'] }}</strong>
                                    <span class="session-time">{{ $row['time'] }}</span>
                                    <span class="session-duration">{{ $row['duration'] }} menit</span>
                                </td>
                                <td class="session-subject">{{ $row['subject'] }}</td><td>{{ $row['grade'] }}</td><td>{{ $row['kind'] }}</td>
                                <td>
                                    @foreach ($row['locations'] as $location)
                                        <div class="location">{{ $location }}</div>
                                    @endforeach
                                </td>
                                <td class="num">{{ $row['participants'] }}</td>
                                <td @class(['is-draft' => $row['draft']])>{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p>Belum ada sesi ujian sesuai filter.</p>
        @endforelse
        @if ($sessionCount)
            <p class="note" data-print-block>Jadwal dengan mapel, waktu, durasi, jenis, dan status yang sama digabung per kelas. Jumlah peserta mencakup semua sesi yang digabung.</p>
        @endif
    </main>
    @if ($sessionCount)
        <script>
            const copyButton = document.getElementById('copy-schedule');
            const shareText = document.getElementById('schedule-share');
            const copyResult = document.getElementById('copy-result');
            copyButton.hidden = false;
            copyButton.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(shareText.value);
                    copyResult.textContent = 'Teks jadwal disalin.';
                } catch {
                    shareText.focus();
                    shareText.select();
                    copyResult.textContent = 'Teks dipilih. Gunakan menu Salin.';
                }
            });
        </script>
    @endif
</body>
</html>
