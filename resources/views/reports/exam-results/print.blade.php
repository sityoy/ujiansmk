<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nilai {{ $period->name }} - {{ $schoolClass->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; max-width: 1180px; padding: 0 16px; font: 12px/1.45 Arial, sans-serif; color: #0f172a; }
        .toolbar { display: flex; justify-content: space-between; padding: 10px 14px; margin-bottom: 12px; color: white; background: #0f172a; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 14px; background: #34d399; font-weight: 700; cursor: pointer; }
        .results-section { padding-top: 12px; }
        .results-section + .results-section { margin-top: 16px; }
        .letterhead { width: 100%; max-height: 30mm; object-fit: contain; margin-bottom: 5mm; }
        h1 { margin: 0; text-align: center; font-size: 16px; }
        .meta { margin: 4mm 0; display: grid; grid-template-columns: 30mm 1fr 30mm 1fr; gap: 2mm; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #94a3b8; padding: 5px; text-align: center; vertical-align: top; }
        th { background: #e2e8f0; font-size: 11px; }
        .row-number { width: 5%; }
        .identifier { width: 12%; }
        .student-name { width: 20%; }
        .average { width: 10%; }
        .rank { width: 6%; }
        .score-heading { font-size: 10px; overflow-wrap: anywhere; }
        .final-score { background: #f1f5f9; font-weight: 700; }
        .note, .pending { font-size: 10px; color: #475569; }
        .pending { display: block; }
        td.name { text-align: left; }
        tr { break-inside: avoid; }
        .signature { width: 60mm; margin: 7mm 0 0 auto; text-align: center; line-height: 1.5; }
        @media screen { table { min-width: 860px; } }
        .document-export table { min-width: 0; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .results-section + .results-section { break-before: page; margin-top: 0; }
            .table-wrap { overflow: visible; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><span>{{ $period->name }} · {{ $schoolClass->name }}</span><x-print-actions filename="rekap-nilai-ujian-inti" landscape :margin="10" /></div>

    <main class="report-sheet" data-print-content>
    @foreach ($subjectPages as $subjectPage)
        <section class="results-section" data-print-break>
            @if ($letterheadData)<img src="{{ $letterheadData }}" alt="Kop sekolah" class="letterhead">@endif
            <h1>REKAP HASIL {{ strtoupper($period->name) }}</h1>
            <div class="meta">
                <strong>Sekolah</strong><span>: {{ $school?->name ?? config('app.name') }}</span>
                <strong>Kelas</strong><span>: {{ $schoolClass->name }}</span>
                <strong>Tahun Ajaran</strong><span>: {{ $period->academicYear->name }}</span>
                <strong>Jenis Asesmen</strong><span>: {{ $period->type->label() }}</span>
            </div>
            @if (count($subjectPages) > 1)
                <p>Kelompok mata pelajaran {{ $loop->iteration }} dari {{ $loop->count }}</p>
            @endif
            <p class="note">Rata-rata dihitung dari Nilai Akhir mapel yang selesai. Peringkat tersedia setelah nilai semua mapel lengkap.</p>
            @php
                $hasBreakdown = collect($subjectPage)->contains(fn ($group) => count($group['columns']) > 1);
                $totalColumns = 5 + collect($subjectPage)->sum(fn ($group) => count($group['columns']));
                $rowspan = $hasBreakdown ? 2 : 1;
            @endphp
            <div class="table-wrap"><table>
                <colgroup><col class="row-number"><col class="identifier"><col class="student-name"></colgroup>
                @foreach ($subjectPage as $group)
                    <colgroup span="{{ count($group['columns']) }}"></colgroup>
                @endforeach
                <colgroup><col class="average"><col class="rank"></colgroup>
                <thead>
                    <tr>
                        <th class="row-number" scope="col" rowspan="{{ $rowspan }}">No.</th>
                        <th class="identifier" scope="col" rowspan="{{ $rowspan }}">NIS/NISN</th>
                        <th class="student-name" scope="col" rowspan="{{ $rowspan }}">Nama Peserta Didik</th>
                        @foreach ($subjectPage as ['subject' => $subject, 'columns' => $columns])
                            @if (count($columns) > 1)
                                <th scope="colgroup" colspan="{{ count($columns) }}">{{ $subject->subject->name }}</th>
                            @else
                                <th scope="col" rowspan="{{ $rowspan }}">{{ $subject->subject->name }}</th>
                            @endif
                        @endforeach
                        <th class="average" scope="col" rowspan="{{ $rowspan }}">Rata-rata</th>
                        <th class="rank" scope="col" rowspan="{{ $rowspan }}">Rank</th>
                    </tr>
                    @if ($hasBreakdown)
                        <tr>
                            @foreach ($subjectPage as $group)
                                @if (count($group['columns']) > 1)
                                    @foreach ($group['columns'] as $key => $label)
                                        <th scope="col" @class(['score-heading', 'final-score' => $key === 'final'])>{{ $label }}</th>
                                    @endforeach
                                @endif
                            @endforeach
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="number">{{ $row['student']->student_number }}<br>{{ $row['student']->nisn ?? '—' }}</td>
                            <td class="name">{{ $row['student']->full_name }}</td>
                            @foreach ($subjectPage as ['subject' => $subject, 'columns' => $columns])
                                @php
                                    $result = $row['results'][$subject->id] ?? null;
                                @endphp
                                @foreach ($columns as $key => $label)
                                    @php
                                        $final = $key === 'final';
                                        $score = $final ? ($result['final'] ?? null) : ($result['components'][$key] ?? null);
                                        $waiting = $result && $result['final'] === null && ($final || array_key_exists($key, $result['components']));
                                    @endphp
                                    <td @class(['final-score' => $final])>
                                        @if ($score !== null)
                                            {{ number_format($score, 2, ',', '.') }}
                                        @elseif ($waiting)
                                            <span class="pending">Menunggu koreksi</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                            <td>
                                {{ $row['average'] === null ? '—' : number_format($row['average'], 2, ',', '.') }}
                                @if ($row['average'] !== null && ! $row['complete'])<span class="pending">Sementara</span>@endif
                            </td>
                            <td>{{ $row['rank'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $totalColumns }}">Belum ada peserta pada kelas ini.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    @endforeach
    <div class="signature" data-print-block>
        <div>{{ $school?->city ?: 'Jakarta' }}, {{ $period->ends_on->translatedFormat('d F Y') }}</div>
        <div>Wali Kelas</div><br><br><br>
        <strong><u>{{ $schoolClass->homeroomTeacher?->name ?? '........................................' }}</u></strong>
    </div>
    </main>
</body>
</html>
