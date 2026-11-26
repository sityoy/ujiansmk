<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nilai {{ $period->name }} - {{ $schoolClass->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; max-width: 1600px; padding: 0 16px; font: 12px/1.45 Arial, sans-serif; color: #0f172a; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; margin-bottom: 12px; color: white; background: #0f172a; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 14px; background: #34d399; font-weight: 700; cursor: pointer; }
        .results-section { padding-top: 12px; }
        .results-section + .results-section { margin-top: 16px; }
        .letterhead { width: 100%; max-height: 30mm; object-fit: contain; margin-bottom: 5mm; }
        h1 { margin: 0; text-align: center; font-size: 16px; }
        .meta { margin: 4mm 0; display: grid; grid-template-columns: 30mm 1fr 30mm 1fr; gap: 2mm; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #94a3b8; padding: 3px 4px; text-align: center; vertical-align: top; }
        th { background: #e2e8f0; font-size: 11px; }
        .row-number { width: 3%; }
        .identifier { width: 10%; }
        .student-name { width: 17%; }
        .average { width: 7%; }
        .rank { width: 4%; }
        .score-heading { font-size: 10px; overflow-wrap: anywhere; }
        .final-score { background: #f1f5f9; font-weight: 700; }
        .note, .pending { font-size: 10px; color: #475569; }
        .pending { display: block; }
        .name { text-align: left; }
        tr { break-inside: avoid; }
        .signature { width: 60mm; margin: 7mm 0 0 auto; text-align: center; line-height: 1.5; }
        .print-results { display: none; font-size: 11px; }
        .document-export .print-results { display: block; }
        .screen-results { --student-name-width: 11rem; }
        .screen-results h1 { text-align: left; }
        .screen-results p { margin: 4px 0 8px; }
        .screen-results .note { font-size: 12px; }
        .results-scroll { max-height: 70vh; overflow: auto; border: 1px solid #94a3b8; }
        .results-scroll:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .screen-results table {
            width: max(100%, calc(var(--student-name-width) + 10rem + var(--score-columns) * 4.5rem));
            border-collapse: separate;
            border-spacing: 0;
        }
        .screen-results :is(th, td) { border-width: 0 1px 1px 0; }
        .screen-results thead { position: sticky; z-index: 2; top: 0; }
        .screen-results .row-number { width: 2.25rem; }
        .screen-results .student-name { width: var(--student-name-width); }
        .screen-results .average { width: 4.75rem; }
        .screen-results .rank { width: 3rem; }
        .screen-results .name { position: sticky; left: 0; }
        .screen-results th.name { z-index: 1; }
        .screen-results td.name { background: #fff; z-index: 1; }
        .screen-results tr:nth-child(even) td.name { background: #f8fafc; }
        .screen-results .student-id { display: block; font-size: 10px; color: #475569; }
        @media (max-width: 600px) {
            body { padding: 0 8px; }
            .screen-results { --student-name-width: 8.5rem; }
        }
        @media print {
            body { padding: 0; }
            .toolbar, .screen-results { display: none; }
            .print-results { display: block; }
            .results-section + .results-section { break-before: page; margin-top: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><span>{{ $period->name }} · {{ $schoolClass->name }}</span><x-print-actions filename="rekap-nilai-ujian-inti" landscape :margin="10" /></div>

    <main class="screen-results report-sheet" aria-labelledby="screen-results-title">
        <x-school-logo :school="$school" custom-only width="38" height="38" />
        <h1 id="screen-results-title">Rekap Hasil Ujian</h1>
        <p>{{ $school?->name ?? config('app.name') }} · {{ $period->academicYear->name }} · {{ $period->type->label() }}</p>
        <p class="note">Geser tabel ke samping untuk melihat mapel lain. Rata-rata memakai nilai akhir yang tersedia. Peringkat muncul setelah nilai semua mapel lengkap.</p>
        <div class="results-scroll" role="region" aria-labelledby="screen-results-title" tabindex="0">
            @include('reports.exam-results.table', ['groups' => $subjectGroups, 'compact' => true])
        </div>
    </main>

    <div class="print-results report-sheet" data-print-content>
    @foreach ($subjectPages as $subjectPage)
        <section class="results-section" data-print-break>
            @if ($letterheadData && ! $school?->logo_path)
                <img src="{{ $letterheadData }}" alt="Kop sekolah" class="letterhead">
            @else
                <x-school-logo :school="$school" width="44" height="44" style="display:block;margin:0 auto 4px" />
            @endif
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
            @include('reports.exam-results.table', ['groups' => $subjectPage, 'compact' => false])
        </section>
    @endforeach
    <div class="signature" data-print-block>
        <div>{{ $school?->city ?: 'Jakarta' }}, {{ $period->ends_on->translatedFormat('d F Y') }}</div>
        <div>Wali Kelas</div><br><br><br>
        <strong><u>{{ $schoolClass->homeroomTeacher?->name ?? '........................................' }}</u></strong>
    </div>
    </div>
</body>
</html>
