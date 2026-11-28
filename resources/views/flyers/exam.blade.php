<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $printTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #dce5df; font-family: Arial, sans-serif; color: #10233c; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; background: #10233c; color: white; }
        .toolbar-label { display: grid; gap: 4px; }
        .toolbar-label small { color: #cbd5e1; }
        .flyer { width: min(194mm, 100%); margin: 20px auto; padding: 9mm 12mm 8mm; background: #fffdf8; border-bottom: 3mm solid #126348; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .cover { margin-bottom: 12px; padding: 16px 18px; border-radius: 14px; border-bottom: 4px solid #d6ba6b; background: #104534; color: #fff; }
        .brand { display: flex; align-items: center; gap: 13px; padding-bottom: 10px; border-bottom: 1px solid #6e9b85; }
        .logo { width: 60px; height: 60px; flex-shrink: 0; object-fit: contain; padding: 3px; border-radius: 8px; background: #fff; }
        .school { font-size: 21px; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
        .eyebrow { margin: 12px 0 5px; color: #f7e3a6; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        h1 { margin: 0; font-size: 34px; line-height: 1.1; text-transform: uppercase; overflow-wrap: anywhere; }
        .cover-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 7px 10px; margin-top: 10px; font-size: 11px; font-weight: bold; }
        .year { padding: 5px 9px; border-radius: 5px; background: #e3f1e8; color: #104534; }
        .subtitle { color: #e3f1e8; }
        h2 { margin: 0 0 8px; font-size: 18px; line-height: 1.2; overflow-wrap: anywhere; }
        .details { display: flex; flex-wrap: wrap; row-gap: 8px; padding: 11px 12px; border: 1px solid #adccb9; border-left: 4px solid #126348; border-radius: 10px; background: #f5faf6; }
        .details h2 { width: 100%; margin: 0; }
        .details > div { width: 50%; min-width: 0; padding-right: 8px; }
        .notice { margin: 0 0 8px; padding: 7px 9px; border: 1px solid #dcb470; border-radius: 8px; background: #fff1d9; color: #853c16; font-size: 13px; font-weight: bold; }
        .label { margin: 0 0 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #436151; }
        .value { margin: 0; font-size: 13px; line-height: 1.35; overflow-wrap: anywhere; }
        .access { display: flex; flex-wrap: wrap; gap: 9px 20px; margin: 10px 0; padding: 10px 12px; border-radius: 12px; background: #10233c; color: white; }
        .access div { min-width: 0; }
        .access .label { color: #bae4b6; }
        .code { margin: 0; font-size: 15px; letter-spacing: 1px; }
        .url { color: white; font-size: 12px; line-height: 1.4; overflow-wrap: anywhere; }
        .rules { margin: 12px 0 10px; }
        .rule-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .rule { padding: 9px 10px; border: 1px solid #c7d9ce; border-radius: 10px; background: #f8fbf8; font-size: 12px; line-height: 1.42; }
        .rule h3 { margin: 0 0 4px; color: #126348; font-size: 13px; }
        .rule p, .warning-note p { margin: 0; }
        .rule p + p, .warning-note p + p { margin-top: 5px; }
        .warning-note { padding: 10px 12px; border: 1px solid #dcb470; border-radius: 10px; background: #fff1d9; color: #622d17; font-size: 12px; line-height: 1.42; }
        .warning-note h2 { font-size: 15px; margin-bottom: 5px; }
        .flyer.schedule, .document-export .flyer.schedule { padding: 0; border: 0; background: #fff; }
        .table-wrap { overflow-x: auto; }
        .schedule table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; line-height: 1.4; }
        .schedule :is(th, td) { padding: 7px; text-align: left; vertical-align: top; border: 1px solid #9cb2a9; overflow-wrap: anywhere; }
        .schedule thead th { background: #e3f1e8; color: #104534; }
        .schedule th.schedule-banner { padding: 12px 14px; background: #104534; color: #fff; border-color: #104534; }
        .schedule-banner h2 { margin: 3px 0; font-size: 22px; }
        .schedule-label, .schedule-period, .session-time, .session-meta { display: block; }
        .schedule-period { color: #e3f1e8; }
        .schedule tbody tr:nth-child(even) { background: #f1f7f3; }
        .session-time { font-variant-numeric: tabular-nums; }
        .session-meta { color: #445e50; margin-top: 3px; }
        @media screen and (max-width: 600px) {
            .flyer { margin: 0 auto 12px; padding: 14px; }
            h1 { font-size: 26px; }
            .school { font-size: 18px; }
            .details > div { width: 100%; }
            .rule-grid { grid-template-columns: 1fr; }
            .schedule table { min-width: 530px; }
            .document-export .schedule table { min-width: 0; }
        }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .flyer { width: 100%; margin: 0; }
            .cover, .details, .access, .rule, .warning-note, tr { break-inside: avoid; }
            .flyer + .flyer { break-before: page; }
            thead { display: table-header-group; }
            .table-wrap { overflow: visible; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="toolbar-label"><strong>{{ $toolbarLabel }}</strong><small>A4 potret</small></span>
        @if ($flyers->isNotEmpty())<x-print-actions filename="flyer-ujian" :margin="8" />@endif
    </div>
    <main data-print-content data-print-table-pages>
        @forelse ($flyers as $flyer)
            @include('flyers.page', [...$flyer, 'pageNumber' => $loop->iteration])
        @empty
            <p class="flyer">Belum ada simulasi yang bisa dicetak.</p>
        @endforelse
    </main>
</body>
</html>
