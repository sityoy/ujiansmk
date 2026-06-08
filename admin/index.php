<?php
// session_start();
require 'cek_admin.php';

// Cek sesi login
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit;
// }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - CBT System</title>
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --secondary: #3b82f6;
            --secondary-hover: #2563eb;
            --danger: #ef4444;
            --warning: #f59e0b;
            --purple: #8b5cf6;
            --teal: #14b8a6;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-main); 
            color: var(--text-main); 
            margin: 0; 
            padding: 20px; 
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--secondary), #60a5fa);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
            margin-top: 20px;
        }
        .welcome-banner h2 { margin: 0 0 10px 0; font-size: 28px; font-weight: 700; }
        .welcome-banner p { margin: 0; font-size: 16px; opacity: 0.9; }

        /* Grid System */
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }

        /* Stat Cards */
        .card-stat { 
            background: var(--card-bg); 
            padding: 25px 20px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.02), 0 1px 3px rgba(0,0,0,0.05); 
            text-align: left; 
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid var(--border-color);
        }
        
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.05);
        }

        .card-stat::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .card-blue::after { background-color: var(--secondary); }
        .card-green::after { background-color: var(--primary); }
        .card-red::after { background-color: var(--danger); }
        .card-purple::after { background-color: var(--purple); }
        .card-warning::after { background-color: var(--warning); }
        .card-teal::after { background-color: var(--teal); }

        .stat-icon {
            font-size: 32px;
            position: absolute;
            right: 20px;
            top: 25px;
            opacity: 0.2;
        }

        .card-stat h2 { 
            margin: 0; 
            font-size: 36px; 
            color: var(--text-main); 
            font-weight: 800;
        }
        .card-stat p { 
            margin: 5px 0 0 0; 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Ranking Section */
        .ranking-section {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
        }

        .ranking-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }

        .ranking-header h3 {
            margin: 0;
            color: var(--text-main);
            font-size: 20px;
            font-weight: 700;
        }

        /* Table Styles */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { border-bottom: 1px solid var(--border-color); padding: 16px 15px; text-align: left; }
        th { 
            background-color: #f8fafc; 
            color: var(--text-muted); 
            font-size: 13px; 
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover { background-color: #f1f5f9; }
        
        .score-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
        }

        .medal { font-size: 18px; }
        .footer-wrap { margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="welcome-banner">
            <h2>👋 Selamat datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>!</h2>
            <p>Pantau perkembangan ujian, kelola soal, dan lihat statistik performa siswa hari ini.</p>
        </div>
        
        <!-- Statistik Utama -->
        <div class="dashboard-grid">
            <div class="card-stat card-blue">
                <div class="stat-icon">📚</div>
                <h2><?php echo $total_soal; ?></h2>
                <p>Total Soal</p>
            </div>
            <div class="card-stat card-purple">
                <div class="stat-icon">👥</div>
                <h2><?php echo $total_siswa; ?></h2>
                <p>Total Siswa</p>
            </div>
            <div class="card-stat card-teal">
                <div class="stat-icon">📅</div>
                <h2><?php echo $total_jadwal; ?></h2>
                <p>Jadwal Ujian</p>
            </div>
            <div class="card-stat card-green">
                <div class="stat-icon">📝</div>
                <h2><?php echo $total_hasil; ?></h2>
                <p>Hasil Ujian</p>
            </div>
        </div>

        <!-- Statistik Nilai & Keamanan -->
        <div class="dashboard-grid">
            <div class="card-stat card-warning">
                <div class="stat-icon">📊</div>
                <h2><?php echo $rata_nilai ?: 0; ?></h2>
                <p>Rata-rata Nilai</p>
            </div>
            <div class="card-stat card-green">
                <div class="stat-icon">🏆</div>
                <h2><?php echo $nilai_tertinggi ?: 0; ?></h2>
                <p>Nilai Tertinggi</p>
            </div>
            <div class="card-stat card-red">
                <div class="stat-icon">📉</div>
                <h2><?php echo $nilai_terendah ?: 0; ?></h2>
                <p>Nilai Terendah</p>
            </div>
            <div class="card-stat card-red">
                <div class="stat-icon">⚠️</div>
                <h2><?php echo $total_pelanggaran ?: 0; ?></h2>
                <p>Total Pelanggaran</p>
            </div>
        </div>

        <!-- Papan Peringkat -->
        <div class="ranking-section">
            <div class="ranking-header">
                <h3>🏆 Top 10 Nilai Tertinggi</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th width="8%">Rank</th>
                            <th>Nama Siswa</th>
                            <th>Mata Pelajaran</th>
                            <th style="text-align: center;">Skor Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        foreach($ranking as $r): 
                            $medal = '';
                            if($no == 1) $medal = '🥇';
                            elseif($no == 2) $medal = '🥈';
                            elseif($no == 3) $medal = '🥉';
                            else $medal = "<span style='color: var(--text-muted); font-weight: bold;'>#$no</span>";
                        ?>
                        <tr>
                            <td align="center" class="medal"><?php echo $medal; ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($r['nama_siswa']); ?></td>
                            <td><?php echo htmlspecialchars($r['mata_pelajaran']); ?></td>
                            <td align="center">
                                <span class="score-badge"><?php echo $r['nilai']; ?></span>
                            </td>
                        </tr>
                        <?php 
                        $no++;
                        endforeach; 
                        
                        if(empty($ranking)):
                        ?>
                        <tr>
                            <td colspan="4" align="center" style="padding: 30px; color: var(--text-muted);">
                                <em>Belum ada data nilai ujian.</em>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-wrap">
            <?php include "footer.php"; ?>
        </div>
    </div>
</body>
</html>