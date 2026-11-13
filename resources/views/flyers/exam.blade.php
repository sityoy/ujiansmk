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
        .flyer { position: relative; width: min(210mm, 100%); min-height: 297mm; margin: 20px auto; padding: 13mm 15mm 12mm; overflow: hidden; background: #fffcf5; border-top: 9mm solid #126348; border-bottom: 7mm solid #126348; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .brand { display: flex; align-items: center; gap: 18px; padding-bottom: 18px; border-bottom: 2px solid #d6ba6b; }
        .logo { width: 94px; height: 94px; flex-shrink: 0; object-fit: contain; background: white; }
        .school { font-size: 21px; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
        .foundation { margin-top: 8px; color: #43795b; font-size: 11px; letter-spacing: 2px; font-weight: bold; }
        .eyebrow { margin: 28px 0 9px; color: #126348; font-size: 12px; font-weight: bold; letter-spacing: 2px; }
        h1 { margin: 0; font-size: 54px; line-height: 1.03; letter-spacing: -1px; }
        .year { display: inline-block; margin: 17px 0 0; padding: 11px 19px; border-radius: 24px; background: #126348; color: white; font-size: 17px; font-weight: bold; }
        .subtitle { margin: 15px 0 20px; color: #436151; font-size: 16px; font-weight: bold; }
        h2 { margin: 0 0 15px; font-size: 21px; line-height: 1.25; overflow-wrap: anywhere; }
        .details { padding: 20px; border: 1px solid #d9dfcf; border-radius: 15px; background: #f0f5e9; }
        .detail + .detail { margin-top: 12px; }
        .label { margin: 0 0 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #436151; }
        .value { margin: 0; font-size: 15px; line-height: 1.35; overflow-wrap: anywhere; }
        .access { margin: 17px 0; padding: 17px 20px; border-radius: 15px; background: #10233c; color: white; }
        .access .label { color: #bae4b6; }
        .code { margin: 3px 0 12px; font-size: 23px; letter-spacing: 1px; }
        .url { color: white; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; }
        .checklist { display: grid; gap: 11px; margin: 20px 0; }
        .step { display: flex; gap: 12px; align-items: center; font-size: 13px; line-height: 1.4; }
        .number { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #126348; color: white; font-weight: bold; flex-shrink: 0; }
        .footer { margin-top: 22px; padding-top: 14px; border-top: 1px solid #d6ba6b; color: #126348; font-size: 15px; font-weight: bold; text-align: center; }
        @media (max-width: 600px) { .flyer { margin: 0; padding: 25px; } h1 { font-size: 37px; } .school { font-size: 18px; } .logo { width: 74px; height: 74px; } }
        @media print { body { background: white; } .toolbar { display: none; } .flyer { width: 210mm; margin: 0; } .brand,.details,.access,.step { break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="toolbar"><span>Flyer {{ $kind }} · {{ $period->academicYear->name }}</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main class="flyer">
        <header class="brand">
            <img class="logo" src="{{ asset('images/logo-ypmi-bahagia.jpeg') }}" alt="Logo YPMI Bahagia" width="94" height="94">
            <div><div class="school">{{ $school?->name ?? config('app.name') }}</div><div class="foundation">YPMI BAHAGIA</div></div>
        </header>
        <p class="eyebrow">INFORMASI PESERTA</p>
        <h1>{{ $kind }}</h1>
        <div class="year">TAHUN AJARAN {{ $period->academicYear->name }}</div>
        <p class="subtitle">{{ $subtitle }}</p>
        <section class="details">
            <h2>{{ $headline }}</h2>
            <div class="detail"><p class="label">Periode ujian</p><p class="value">{{ $period->name }}</p></div>
            @foreach ($details as [$label, $value])<div class="detail"><p class="label">{{ $label }}</p><p class="value">{{ $value }}</p></div>@endforeach
        </section>
        <section class="access">
            @if ($code !== null)<p class="label">Kode uji coba</p><p class="code">{{ $code }}</p>@endif
            <p class="label">{{ $code !== null ? 'Tautan uji coba' : 'Tautan masuk peserta' }}</p><a class="url" href="{{ $entryUrl }}">{{ $entryUrl }}</a>
        </section>
        <section class="checklist">
            <div class="step"><span class="number">1</span><span>{{ $code !== null ? 'Gunakan NIS/NISN dan kode uji coba yang tertera.' : 'Siapkan perangkat dan kartu peserta ujian.' }}</span></div>
            <div class="step"><span class="number">2</span><span>Aktifkan Jangan Ganggu dan tutup aplikasi mengambang sebelum masuk.</span></div>
            <div class="step"><span class="number">3</span><span>Ikuti jadwal dan arahan panitia. Kerjakan dengan jujur dan tertib.</span></div>
        </section>
        <footer class="footer">{{ $code !== null ? 'Berlatih · Mengenal Sistem · Lebih Siap' : 'Jujur · Tertib · Percaya Diri' }}</footer>
    </main>
</body>
</html>
