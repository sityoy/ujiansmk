<?php
session_start();
require 'cek_admin.php';

// Cek sesi login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$total_soal = $pdo->query("SELECT COUNT(*) FROM soal")->fetchColumn();
$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$total_jadwal = $pdo->query("SELECT COUNT(*) FROM pengaturan_ujian")->fetchColumn();
$total_hasil = $pdo->query("SELECT COUNT(*) FROM ujian_siswa")->fetchColumn();
$rata_nilai = $pdo->query("SELECT ROUND(AVG(nilai),2) FROM ujian_siswa WHERE nilai IS NOT NULL")->fetchColumn();
$nilai_tertinggi = $pdo->query("SELECT MAX(nilai) FROM ujian_siswa")->fetchColumn();
$nilai_terendah = $pdo->query("SELECT MIN(nilai) FROM ujian_siswa WHERE nilai IS NOT NULL")->fetchColumn();
$total_pelanggaran = $pdo->query("SELECT SUM(jumlah_pelanggaran) FROM ujian_siswa")->fetchColumn();

$stmtRanking = $pdo->query("SELECT s.nama_siswa, u.mata_pelajaran, u.nilai FROM ujian_siswa u JOIN siswa s ON s.id=u.siswa_id ORDER BY u.nilai DESC LIMIT 10");
$ranking = $stmtRanking->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Penting untuk HP -->
    <title>Dashboard Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .content { margin-top: 20px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .card-stat { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; border-bottom: 4px solid #007bff; }
        .card-stat h2 { margin: 0; font-size: 32px; color: #007bff; }
        .card-stat p { margin-top: 10px; color: #666; font-weight: bold; }
        
        /* Tabel Responsif */
        .table-responsive { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #007bff; color: white; text-align: center; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="content">
        <h2>Selamat datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>!</h2>
        <p>Gunakan menu di atas untuk mengatur soal ujian dan melihat nilai siswa.</p>
        
        <div class="dashboard-grid">
            <div class="card-stat"><h2><?php echo $total_soal; ?></h2><p>Total Soal</p></div>
            <div class="card-stat"><h2><?php echo $total_siswa; ?></h2><p>Total Siswa</p></div>
            <div class="card-stat"><h2><?php echo $total_jadwal; ?></h2><p>Jadwal Ujian</p></div>
            <div class="card-stat"><h2><?php echo $total_hasil; ?></h2><p>Hasil Ujian</p></div>
        </div>

        <div class="dashboard-grid">
            <div class="card-stat" style="border-color:#28a745;"><h2><?php echo $rata_nilai ?: 0; ?></h2><p>Rata-rata Nilai</p></div>
            <div class="card-stat" style="border-color:#28a745;"><h2><?php echo $nilai_tertinggi ?: 0; ?></h2><p>Nilai Tertinggi</p></div>
            <div class="card-stat" style="border-color:#dc3545;"><h2><?php echo $nilai_terendah ?: 0; ?></h2><p>Nilai Terendah</p></div>
            <div class="card-stat" style="border-color:#dc3545;"><h2><?php echo $total_pelanggaran ?: 0; ?></h2><p>Total Pelanggaran</p></div>
        </div>

        <div class="content" style="margin-top: 40px;">
            <h3 style="color: #28a745;">🏆 10 Nilai Tertinggi</h3>
            <div class="table-responsive">
                <table>
                    <tr><th width="5%">No</th><th>Nama Siswa</th><th>Mata Pelajaran</th><th>Nilai</th></tr>
                    <?php $no=1; foreach($ranking as $r): ?>
                    <tr>
                        <td align="center"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($r['nama_siswa']); ?></td>
                        <td><?php echo htmlspecialchars($r['mata_pelajaran']); ?></td>
                        <td align="center"><b style="color:#28a745; font-size:16px;"><?php echo $r['nilai']; ?></b></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php include "footer.php"; ?>
            </div>
        </div>
    </div>
</body>
</html>