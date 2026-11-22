<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flyer {{ $kind }} · {{ $period->academicYear->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #dce5df; font-family: Arial, sans-serif; color: #10233c; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 24px; background: #10233c; color: white; }
        button { padding: 10px 18px; border: 0; border-radius: 8px; background: #d7f5c9; color: #123c2e; font-weight: bold; cursor: pointer; }
        .flyer { width: min(210mm, 100%); margin: 20px auto; padding: 9mm 12mm 8mm; background: #fffcf5; border-top: 6mm solid #126348; border-bottom: 5mm solid #126348; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .brand { display: flex; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid #d6ba6b; }
        .logo { width: 64px; height: 64px; flex-shrink: 0; object-fit: contain; background: white; }
        .school { font-size: 21px; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
        .eyebrow { margin: 15px 0 6px; color: #126348; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        h1 { margin: 0; font-size: 34px; line-height: 1.1; text-transform: uppercase; overflow-wrap: anywhere; }
        .year { display: inline-block; margin: 10px 0 0; padding: 7px 13px; border-radius: 20px; background: #126348; color: white; font-size: 13px; font-weight: bold; }
        .subtitle { margin: 9px 0 12px; color: #436151; font-size: 13px; font-weight: bold; }
        h2 { margin: 0 0 8px; font-size: 18px; line-height: 1.2; overflow-wrap: anywhere; }
        .details { display: flex; flex-wrap: wrap; row-gap: 8px; padding: 12px; border: 1px solid #d9dfcf; border-radius: 12px; background: #f0f5e9; }
        .details h2 { width: 100%; margin: 0; }
        .details > div { width: 50%; min-width: 0; padding-right: 8px; }
        .draft { margin: 0 0 8px; color: #853c16; font-size: 13px; font-weight: bold; }
        .label { margin: 0 0 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #436151; }
        .value { margin: 0; font-size: 13px; line-height: 1.35; overflow-wrap: anywhere; }
        .access { display: flex; flex-wrap: wrap; gap: 9px 20px; margin: 10px 0; padding: 10px 12px; border-radius: 12px; background: #10233c; color: white; }
        .access div { min-width: 0; }
        .access .label { color: #bae4b6; }
        .code { margin: 0; font-size: 15px; letter-spacing: 1px; }
        .url { color: white; font-size: 12px; line-height: 1.4; overflow-wrap: anywhere; }
        .rules { margin: 12px 0 10px; }
        .rule-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .rule { padding: 9px 10px; border: 1px solid #d9dfcf; border-radius: 10px; background: #f0f5e9; font-size: 12px; line-height: 1.42; }
        .rule h3 { margin: 0 0 4px; color: #126348; font-size: 13px; }
        .rule p, .warning-note p { margin: 0; }
        .rule p + p, .warning-note p + p { margin-top: 5px; }
        .warning-note { padding: 10px 12px; border: 1px solid #dcb470; border-radius: 10px; background: #fff1d9; color: #622d17; font-size: 12px; line-height: 1.42; }
        .warning-note h2 { font-size: 15px; margin-bottom: 5px; }
        .schedule-heading { margin: 0 0 10px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
        th, td { padding: 7px; text-align: left; vertical-align: top; border: 1px solid #c6cec1; }
        th { background: #f0f5e9; }
        @media screen and (max-width: 600px) { .flyer { margin: 0; padding: 19px; } h1 { font-size: 26px; } .school { font-size: 18px; } .details > div { width: 100%; } .rule-grid { grid-template-columns: 1fr; } }
        @media print {
            body { background: white; } .toolbar { display: none; } .flyer { width: 210mm; margin: 0; }
            .brand, .details, .access, .rule, .warning-note, tr { break-inside: avoid; }
            .schedule { break-before: page; } thead { display: table-header-group; } .table-wrap { overflow: visible; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><span>Flyer {{ $kind }} · {{ $period->academicYear->name }}</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main class="flyer">
        <header class="brand">
            <img class="logo" src="{{ asset('images/logo-ypmi-bahagia.jpeg') }}" alt="Logo YPMI Bahagia" width="64" height="64">
            <div class="school">{{ $schoolName }}</div>
        </header>
        <p class="eyebrow">INFORMASI PESERTA</p>
        <h1>{{ $kind }}</h1>
        <div class="year">TAHUN AJARAN {{ $period->academicYear->name }}</div>
        <p class="subtitle">{{ $subtitle }}</p>
        @if ($warning)
            <h2>{{ $headline }}</h2>
        @else
            @if ($draft)<p class="draft">Draf jadwal. Tunggu jadwal resmi dari panitia.</p>@endif
            <section class="details">
                <h2>{{ $headline }}</h2>
                @if ($headline !== $period->name)
                    <div><p class="label">Periode ujian</p><p class="value">{{ $period->name }}</p></div>
                @endif
                @foreach ($details as [$label, $value])
                    <div><p class="label">{{ $label }}</p><p class="value">{{ $value }}</p></div>
                @endforeach
            </section>
            <section class="access">
                @if ($code !== null)<div><p class="label">Kode uji coba</p><p class="code">{{ $code }}</p></div>@endif
                <div><p class="label">{{ $trial ? 'Tautan uji coba' : 'Tautan masuk peserta' }}</p><a class="url" href="{{ $entryUrl }}">{{ $entryUrl }}</a></div>
            </section>
        @endif
        @include('flyers.rules')
    </main>
    @if ($schedule->isNotEmpty())
        <section class="flyer schedule" aria-labelledby="schedule-title">
            <h2 id="schedule-title">Jadwal per mapel dan rombel</h2>
            <p class="subtitle schedule-heading">{{ $period->name }} · {{ $period->academicYear->name }}</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Mata pelajaran dan rombel</th><th scope="col">Sesi</th><th scope="col">Tanggal dan waktu</th><th scope="col">Lokasi dan ruangan</th></tr></thead>
                    <tbody>
                        @foreach ($schedule as $row)
                            <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</body>
</html>
