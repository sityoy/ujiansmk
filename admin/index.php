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

// Ambil daftar mapel yang sudah ada nilainya
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM ujian_siswa WHERE nilai IS NOT NULL ORDER BY mata_pelajaran ASC");
$list_mapel = $stmtMapel->fetchAll(PDO::FETCH_COLUMN);


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
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::after {
            content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
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
            border-left: 5px solid transparent;
            transition: 0.3s;
        }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .card-blue { border-color: var(--secondary); }
        .card-purple { border-color: var(--purple); }
        .card-teal { border-color: var(--teal); }
        .card-green { border-color: var(--primary); }
        .card-warning { border-color: var(--warning); }
        .card-red { border-color: var(--danger); }

        .stat-icon {
            position: absolute; right: 20px; top: 25px; font-size: 40px; opacity: 0.15;
        }

        .card-stat h2 { margin: 0 0 5px 0; font-size: 32px; color: var(--text-main); font-weight: 800; }
        .card-stat p { 
            margin: 0; color: var(--text-muted); 
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

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 100%; }
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

        <?php if (empty($list_mapel)): ?>
            <div class="ranking-section">
                <div class="ranking-header">
                    <h3 style="margin: 0; font-size: 18px;">🏆 Peringkat Tertinggi</h3>
                </div>
                <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                    <em>Belum ada data nilai ujian.</em>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                <?php foreach($list_mapel as $mapel): ?>
                    <?php
                        // PERBAIKAN: Menambahkan s.kelas ke dalam SELECT dan memastikan JOIN sudah benar
                        $stmtRank = $pdo->prepare("SELECT s.nama_siswa, s.kelas, u.nilai 
                                                   FROM ujian_siswa u 
                                                   JOIN siswa s ON s.id=u.siswa_id 
                                                   WHERE u.mata_pelajaran = ? AND u.nilai IS NOT NULL 
                                                   ORDER BY u.nilai DESC LIMIT 5");
                        $stmtRank->execute([$mapel]);
                        $ranking = $stmtRank->fetchAll();
                    ?>
                    <div class="ranking-section" style="margin-bottom: 0;">
                        <div class="ranking-header">
                            <h3 style="margin: 0; font-size: 16px; color: var(--text-main);">🏆 Top 5 - <?php echo htmlspecialchars($mapel); ?></h3>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th width="12%">Rank</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th style="text-align: center; width: 30%;">Skor Akhir</th>
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
                                        
                                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($r['kelas']); ?></td>
                                        
                                        <td align="center">
                                            <span class="score-badge"><?php echo $r['nilai']; ?></span>
                                        </td>
                                    </tr>
                                    <?php 
                                    $no++;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="footer-wrap">
            <?php include "footer.php"; ?>
        </div>
    </div>
</body>
</html>