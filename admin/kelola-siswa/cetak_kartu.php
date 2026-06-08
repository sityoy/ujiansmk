<?php
require 'cek_admin.php';

$stmt = $pdo->query("SELECT * FROM siswa ORDER BY kelas ASC, nama_siswa ASC");
$siswa_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu Peserta Ujian</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');

        /* Setting dasar untuk layar komputer */
        body { 
            font-family: 'Roboto', Arial, sans-serif; 
            background: #525659; /* Warna abu-abu ala PDF Viewer */
            margin: 0; 
            padding: 20px; 
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Tombol Cetak */
        .btn-print { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            font-size: 16px; 
            font-weight: bold; 
            border-radius: 5px; 
            cursor: pointer; 
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: 0.3s;
        }
        .btn-print:hover { background: #218838; transform: translateY(-2px); }

        /* Kertas A4 (Ukuran Asli 210mm x 297mm) */
        .kertas-a4 { 
            width: 210mm; 
            background: white; 
            padding: 10mm; /* Margin dalam kertas */
            box-sizing: border-box; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            
            /* Menggunakan Grid agar kartu berjejer rapi 2 kolom */
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px; /* Jarak antar kartu */
        }

        /* Desain Kartu (Presisi) */
        .kartu { 
            border: 2px solid #333; 
            border-radius: 8px; 
            padding: 15px; 
            box-sizing: border-box; 
            background: #fff;
            
            /* KUNCI: Mencegah kartu terpotong di tengah saat beda halaman */
            break-inside: avoid; 
            page-break-inside: avoid; 
        }

        /* Kop Kartu */
        .kop-kartu { 
            text-align: center; 
            border-bottom: 3px double #333; 
            padding-bottom: 8px; 
            margin-bottom: 12px; 
        }
        .kop-kartu h3 { margin: 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; color: #111; }
        .kop-kartu p { margin: 3px 0 0; font-size: 11px; font-weight: bold; color: #444; }

        /* Tabel Data Presisi (Agar nama panjang aman) */
        .tabel-data { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; /* Memaksa ukuran kolom konsisten */
        }
        .tabel-data td { 
            padding: 4px 0; 
            font-size: 12px; 
            vertical-align: top; 
            color: #222;
        }
        .kolom-label { width: 30%; font-weight: bold; }
        .kolom-titik { width: 5%; text-align: center; }
        .kolom-isi { 
            width: 65%; 
            font-weight: bold; 
            word-wrap: break-word; /* Jika nama sangat panjang, otomatis turun ke bawah */
        }

        .highlight-password {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 2px 5px;
            border-radius: 3px;
            color: #856404;
            font-family: monospace;
            font-size: 13px;
        }

        /* Tanda Tangan */
        .ttd-area { 
            margin-top: 15px; 
            text-align: right; 
            font-size: 11px; 
        }
        .ttd-area p { margin: 0 0 35px 0; }
        .ttd-area strong { display: inline-block; border-bottom: 1px solid #333; width: 120px; text-align: center; }

        /* ======================================= */
        /* SETTING KHUSUS SAAT TOMBOL PRINT DIKLIK */
        /* ======================================= */
        @media print {
            @page { 
                size: A4 portrait; 
                margin: 10mm; /* Set margin printer */
            }
            body { 
                background: white; 
                padding: 0; 
                margin: 0; 
                -webkit-print-color-adjust: exact; /* Paksa warna background ikut tercetak */
                print-color-adjust: exact;
            }
            .btn-print { display: none !important; } /* Hilangkan tombol saat dicetak */
            .kertas-a4 { 
                box-shadow: none; 
                width: 100%; 
                padding: 0; 
            }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">🖨️ Cetak Kartu (A4)</button>

    <div class="kertas-a4">
        <?php foreach ($siswa_list as $s): ?>
        <div class="kartu">
            <div class="kop-kartu">
                <h3>KARTU PESERTA UJIAN</h3>
                <p>CBT SMK ISLAM BAHAGIA - T.P 2025/2026</p>
            </div>
            
            <table class="tabel-data">
                <tr>
                    <td class="kolom-label">No. Peserta</td>
                    <td class="kolom-titik">:</td>
                    <td class="kolom-isi"><?php echo htmlspecialchars($s['kartu_peserta']); ?></td>
                </tr>
                <tr>
                    <td class="kolom-label">Nama Siswa</td>
                    <td class="kolom-titik">:</td>
                    <td class="kolom-isi" style="font-size: 13px; color: #0056b3;"><?php echo htmlspecialchars($s['nama_siswa']); ?></td>
                </tr>
                <tr>
                    <td class="kolom-label">Kelas</td>
                    <td class="kolom-titik">:</td>
                    <td class="kolom-isi"><?php echo htmlspecialchars($s['kelas']); ?></td>
                </tr>
                <tr>
                    <td class="kolom-label">Password</td>
                    <td class="kolom-titik">:</td>
                    <td class="kolom-isi"><span class="highlight-password"><?php echo htmlspecialchars($s['password']); ?></span></td>
                </tr>
            </table>

            <div class="ttd-area">
                <p>Panitia Ujian,</p>
                <strong></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</body>
</html>