<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Cetak Akun Petugas — {{ $school?->name ?? config('app.name') }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #e2e8f0; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; background: #0f172a; color: #fff; }
        .toolbar small { display: block; margin-top: 3px; color: #cbd5e1; }
        .toolbar button { border: 0; border-radius: 8px; background: #22d3ee; padding: 10px 16px; color: #0f172a; font-weight: 700; cursor: pointer; }
        .warning { width: 190mm; margin: 12px auto 0; border: 1px solid #f59e0b; border-radius: 9px; padding: 10px 12px; background: #fffbeb; color: #92400e; font-size: 11px; line-height: 1.5; }
        .sheet { width: 190mm; margin: 12px auto; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7mm; align-items: start; }
        .card { min-height: 64mm; overflow: hidden; border: 1.2px solid #0f172a; border-radius: 10px; background: #fff; break-inside: avoid; page-break-inside: avoid; }
        .head { padding: 9px 12px; color: #fff; background: #0f172a; }
        .school { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .title { margin-top: 3px; color: #67e8f9; font-size: 9px; font-weight: 700; letter-spacing: 1.4px; }
        .body { padding: 11px 12px; font-size: 11px; }
        .name { margin-bottom: 8px; font-size: 14px; font-weight: 700; }
        .role { display: inline-block; margin-bottom: 8px; border-radius: 999px; padding: 3px 8px; background: #cffafe; color: #155e75; font-size: 9px; font-weight: 700; }
        .row { display: grid; grid-template-columns: 74px 8px minmax(0, 1fr); margin: 4px 0; }
        .value { overflow-wrap: anywhere; }
        .login { margin-top: 8px; border: 1px dashed #0891b2; border-radius: 7px; padding: 8px; background: #ecfeff; }
        .password { font-family: Consolas, monospace; font-size: 12px; letter-spacing: .3px; }
        .note { margin-top: 7px; color: #64748b; font-size: 8.5px; line-height: 1.45; }
        @media (max-width: 760px) {
            .warning, .sheet { width: calc(100% - 24px); }
            .sheet { grid-template-columns: 1fr; }
        }
        @media print {
            body {
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .toolbar, .warning { display: none !important; }
            .sheet {
                display: grid !important;
                width: 100% !important;
                margin: 0 !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 6mm !important;
                align-items: start;
            }
            .card {
                width: auto !important;
                min-height: 64mm;
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ $credentials->count() }} akun siap dicetak</strong>
            <small>Dibuat {{ $generatedAt->format('d/m/Y H:i') }} WIB · password hanya tampil pada halaman ini</small>
        </div>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="warning">
        Password akun yang tercetak telah direset menjadi password sementara. Jangan memuat ulang halaman ini.
        Pengguna wajib mengganti password ketika login pertama dan kartu harus diberikan kepada pemilik akun yang tepat.
    </div>

    <main class="sheet">
        @foreach ($credentials as $credential)
            <section class="card">
                <header class="head">
                    <div class="school">{{ $school?->name ?? config('app.name') }}</div>
                    <div class="title">KARTU AKUN SISTEM UJIAN SEKOLAH</div>
                </header>
                <div class="body">
                    <div class="name">{{ $credential['name'] }}</div>
                    <div class="role">{{ $credential['role'] }}</div>
                    <div class="login">
                        <div class="row"><span>Login</span><span>:</span><strong class="value">{{ $credential['email'] }}</strong></div>
                        <div class="row"><span>Password</span><span>:</span><strong class="value password">{{ $credential['password'] }}</strong></div>
                    </div>
                    <div class="row"><span>Alamat sistem</span><span>:</span><span class="value">{{ config('app.url') }}</span></div>
                    <div class="note">Rahasiakan akun ini. Sistem akan meminta penggantian password sementara setelah login berhasil.</div>
                </div>
            </section>
        @endforeach
    </main>
</body>
</html>
