<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }

$stmt = $pdo->query("SELECT * FROM siswa ORDER BY kelas ASC, nama_siswa ASC");
$siswa_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu Peserta Ujian</title>
    <style>
        body { font-family: Arial, sans-serif; background: #ccc; margin: 0; padding: 20px; }
        .print-container { width: 210mm; background: white; margin: 0 auto; padding: 10mm; box-sizing: border-box; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; }
        
        .kartu { width: calc(50% - 10px); border: 2px dashed #333; padding: 15px; box-sizing: border-box; border-radius: 8px; margin-bottom: 10px; break-inside: avoid; }
        .kartu-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .kartu-header h3 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .kartu-header p { margin: 5px 0 0 0; font-size: 12px; }
        
        table { width: 100%; font-size: 14px; margin-bottom: 15px;}
        table td { padding: 4px 0; vertical-align: top;}
        table td:first-child { font-weight: bold; width: 35%; }
        
        .password-highlight { font-family: monospace; background: #e9ecef; padding: 2px 5px; border: 1px solid #ccc; font-weight: bold; font-size: 16px; letter-spacing: 2px;}
        
        .ttd { text-align: right; font-size: 12px; margin-top: 10px; }
        .ttd p { margin: 0 0 40px 0; }

        .btn-print { display: block; width: 200px; margin: 0 auto 20px auto; padding: 15px; background: #007bff; color: white; text-align: center; text-decoration: none; font-weight: bold; border-radius: 5px; font-size: 16px; cursor: pointer; border: none; }

        /* Pengaturan Cetak / Print */
        @media print {
            body { background: white; padding: 0; }
            .print-container { width: 100%; padding: 0; }
            .btn-print { display: none; } /* Sembunyikan tombol saat dicetak */
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">🖨️ Cetak Kartu Sekarang</button>

    <div class="print-container">
        <?php foreach ($siswa_list as $s): ?>
        <div class="kartu">
            <div class="kartu-header">
                <h3>KARTU PESERTA UJIAN (CBT)</h3>
                <p>Tahun Pelajaran 2025/2026</p>
            </div>
            <table>
                <tr>
                    <td>No. Peserta</td>
                    <td>: <?php echo htmlspecialchars($s['kartu_peserta']); ?></td>
                </tr>
                <tr>
                    <td>Nama Siswa</td>
                    <td>: <strong><?php echo htmlspecialchars($s['nama_siswa']); ?></strong></td>
                </tr>
                <tr>
                    <td>Kelas</td>
                    <td>: <?php echo htmlspecialchars($s['kelas']); ?></td>
                </tr>
                <tr>
                    <td>Password</td>
                    <td>: <span class="password-highlight"><?php echo htmlspecialchars($s['password']); ?></span></td>
                </tr>
            </table>
            <div class="ttd">
                <p>Panitia Ujian,</p>
                <strong>(.............................)</strong>
            </div>
        </div>
        <?php endforeach; ?>

        <?php include "footer.php"; ?>
    </div>

</body>
</html>