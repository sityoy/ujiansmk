<?php
// session_start();
require 'cek_admin.php';

// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit;
// }

// 1. Ambil daftar mapel unik untuk dropdown
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM ujian_siswa WHERE mata_pelajaran IS NOT NULL");
$list_mapel = $stmtMapel->fetchAll();

// 2. Ambil daftar kelas unik dari tabel siswa untuk dropdown
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
$list_kelas = $stmtKelas->fetchAll();

// 3. Ambil daftar nama ujian unik dari tabel pengaturan_ujian
$stmtNamaUjian = $pdo->query("SELECT DISTINCT nama_ujian FROM pengaturan_ujian WHERE nama_ujian IS NOT NULL AND nama_ujian != '' ORDER BY nama_ujian ASC");
$list_nama_ujian = $stmtNamaUjian->fetchAll();

// 4. Tangkap nilai filter dari URL (GET)
$nama_ujian_filter = isset($_GET['nama_ujian']) ? $_GET['nama_ujian'] : '';
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$limit = 20; 

$where = "WHERE 1=1";
$params = [];

// Filter berdasarkan Nama Ujian
if ($nama_ujian_filter != '') {
    $where .= " AND p.nama_ujian = ?";
    $params[] = $nama_ujian_filter;
}

// Filter berdasarkan Mapel
if ($mapel_filter != '') {
    $where .= " AND u.mata_pelajaran = ?";
    $params[] = $mapel_filter;
}

// Filter berdasarkan Kelas
if ($kelas_filter != '') {
    $where .= " AND s.kelas = ?";
    $params[] = $kelas_filter;
}

// Filter Search Nama Siswa atau Nomor Kartu Peserta
if ($search_filter != '') {
    $where .= " AND (s.nama_siswa LIKE ? OR s.kartu_peserta LIKE ?)";
    $params[] = "%" . $search_filter . "%";
    $params[] = "%" . $search_filter . "%";
}

// =========================================================================
// PERBAIKAN LOGIKA JOIN: Kita cocokkan berdasarkan Mapel, Kelas, DAN TANGGAL
// Karena jadwal Utama dan Susulan tanggalnya beda, data tidak akan ganda lagi
// =========================================================================
$join_logic = "LEFT JOIN pengaturan_ujian p ON 
                u.mata_pelajaran = p.mata_pelajaran 
                AND p.kelas = (CASE 
                                WHEN s.kelas LIKE 'XII%' THEN 'XII' 
                                WHEN s.kelas LIKE 'XI%' THEN 'XI' 
                                ELSE 'X' 
                               END)
                AND DATE(u.waktu_selesai) BETWEEN DATE(p.waktu_mulai) AND DATE(p.waktu_selesai)"; 

// 5. Hitung total data untuk Pagination
$queryCount = "SELECT COUNT(*) FROM ujian_siswa u 
               JOIN siswa s ON u.siswa_id = s.id 
               $join_logic
               $where";
$stmtCount = $pdo->prepare($queryCount);
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();

if ($page === 'all') {
    $limit_query = "";
    $total_pages = 1;
} else {
    $page = (int)$page;
    $offset = ($page - 1) * $limit;
    $limit_query = "LIMIT $limit OFFSET $offset";
    $total_pages = ceil($total_data / $limit);
}

// =========================================================================
// --- FITUR CETAK / SIMPAN PDF A4 ---
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] == 'print') {
    $queryPrint = "SELECT u.mata_pelajaran, p.nama_ujian, s.kartu_peserta, s.nama_siswa, s.kelas, u.nilai, u.benar, u.salah, u.jumlah_pelanggaran, u.waktu_selesai, u.foto_selfie
                   FROM ujian_siswa u 
                   JOIN siswa s ON u.siswa_id = s.id 
                   $join_logic
                   $where ORDER BY s.kelas ASC, s.nama_siswa ASC";
    $stmtPrint = $pdo->prepare($queryPrint);
    $stmtPrint->execute($params);
    $dataPrint = $stmtPrint->fetchAll(PDO::FETCH_ASSOC);

    $title_ujian = ($nama_ujian_filter != '') ? "UJIAN: " . htmlspecialchars($nama_ujian_filter) : "SEMUA UJIAN";
    $title_kelas = ($kelas_filter != '') ? "KELAS: " . htmlspecialchars($kelas_filter) : "SEMUA KELAS";
    $title_mapel = ($mapel_filter != '') ? "MAPEL: " . htmlspecialchars($mapel_filter) : "SEMUA MATA PELAJARAN";
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Rekap_Nilai_<?php echo $nama_ujian_filter ?: 'SemuaUjian'; ?>_<?php echo $kelas_filter ?: 'SemuaKelas'; ?>_<?php echo date('Ymd'); ?></title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 10mm; }
            .print-container { width: 100%; }
            .kop-surat { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .kop-surat h2 { margin: 0; font-size: 16pt; font-weight: bold; text-transform: uppercase; }
            .kop-surat .filter-info { margin: 5px 0 0 0; font-size: 11pt; font-weight: 600; }
            table.tabel-data { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.tabel-data th, table.tabel-data td { border: 1px solid #333; padding: 8px; text-align: center; }
            table.tabel-data th { background-color: #e2e8f0 !important; color: #000; }
            thead { display: table-header-group; }
            .no-print-area { background: #f8fafc; padding: 10px; text-align: center; margin-bottom: 20px; }
            .btn-print { background: #0ea5e9; color: white; padding: 8px 20px; border-radius: 5px; cursor: pointer; border:none; }
            @media print { 
                .no-print-area { display: none; }
                @page { size: A4 portrait; margin: 10mm; }
            }
        </style>
    </head>
    <body>
        <div class="no-print-area">
            <button class="btn-print" onclick="window.print()">🖨️ Klik Untuk Cetak / Save ke PDF</button>
        </div>

        <div class="print-container">
            <table class="tabel-data">
                <thead>
                    <tr>
                        <th colspan="11">
                            <div class="kop-surat">
                                <h2>REKAP NILAI UJIAN</h2>
                                <div class="filter-info"><?php echo $title_ujian . " | " . $title_kelas . " | " . $title_mapel; ?></div>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th>No</th>
                        <th>No. Peserta</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Nama Ujian</th>
                        <th>Mapel</th>
                        <th width="8%">Benar</th>
                        <th width="8%">Salah</th>
                        <th width="8%">Nilai</th>
                        <th>Pelanggaran</th>
                        <th>Selfie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach($dataPrint as $row): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kartu_peserta']); ?></td>
                            <td style="text-align: left;"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                            <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_ujian'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['mata_pelajaran']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['benar']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($row['salah']); ?></strong></td>
                            <td><strong><span class="score-text"><?php echo number_format($row['nilai'], 2); ?></span></strong></td>
                            <td>
                                <?php if($row['jumlah_pelanggaran'] >= 2): ?>
                                    <span class="badge badge-danger">⚠️ <?php echo $row['jumlah_pelanggaran']; ?>x (BATAS)</span>
                                <?php elseif($row['jumlah_pelanggaran'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo $row['jumlah_pelanggaran']; ?>x</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">0x</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['foto_selfie']): ?>
                                    <img src="../assets/<?php echo $row['foto_selfie']; ?>" class="selfie-img" style="width: 50px; height: 50px;">
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); }</script>
    </body>
    </html>
    <?php
    exit;
}
// =========================================================================

// 6. Query utama dengan LEFT JOIN yang difilter dengan waktu ujian
$query = "SELECT u.*, s.kartu_peserta, s.nama_siswa, s.kelas, p.nama_ujian 
          FROM ujian_siswa u 
          JOIN siswa s ON u.siswa_id = s.id 
          $join_logic
          $where 
          ORDER BY u.id DESC $limit_query";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data_nilai = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai Siswa</title>
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --secondary: #3b82f6;
            --secondary-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-main); color: var(--text-main); margin: 0; padding: 20px; }
        .container { max-width: 1300px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        .header-title { margin-top: 0; margin-bottom: 25px; color: var(--text-main); border-bottom: 2px solid var(--border-color); padding-bottom: 15px; font-size: 24px; }
        .filter-box { background: #f1f5f9; padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-form-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .filter-label { font-weight: 600; font-size: 14px; color: var(--text-main); }
        .form-control { padding: 10px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; min-width: 150px; transition: 0.3s; }
        .form-control:focus { border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
        .btn { padding: 10px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-primary { background: var(--secondary); color: white; }
        .btn-primary:hover { background: var(--secondary-hover); }
        .btn-success { background: var(--primary); color: white; }
        .btn-success:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-hover); }
        .btn-info { background: var(--info); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 13px; width: 100%; margin-bottom: 5px; box-sizing: border-box;}
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; margin-top: 10px;}
        th, td { border-bottom: 1px solid var(--border-color); padding: 14px 12px; text-align: center; font-size: 14.5px;}
        th { background-color: #f8fafc; color: var(--text-muted); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background-color: #f1f5f9; }
        .text-left { text-align: left; }
        .fw-bold { font-weight: 600; }
        .score-text { font-size: 18px; font-weight: 800; color: var(--primary); }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-muted { background: #f1f5f9; color: #64748b; }
        .badge-ujian { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;}
        .selfie-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: zoom-in; border: 2px solid var(--border-color); transition: 0.2s; }
        .selfie-img:hover { border-color: var(--secondary); transform: scale(1.05); }
        .pagination { margin-top: 30px; display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .page-link { padding: 8px 14px; border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); border-radius: 6px; font-weight: 600; font-size: 14px; transition: 0.2s; }
        .page-link.active { background: var(--secondary); color: white; border-color: var(--secondary); }
        .page-link:hover:not(.active) { background: #e2e8f0; }
        #modal-pratinjau { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.9); z-index: 10000; justify-content: center; align-items: center; flex-direction: column; backdrop-filter: blur(4px); }
        #modal-pratinjau img { max-width: 90%; max-height: 85vh; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .close-btn { position: absolute; top: 25px; right: 40px; color: white; font-size: 45px; cursor: pointer; font-weight: 300; transition: 0.2s; }
        .close-btn:hover { color: var(--danger); transform: scale(1.1); }
        .footer-wrap { margin-top: 30px; text-align: center; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div id="modal-pratinjau">
        <span class="close-btn" onclick="tutupFoto()">&times;</span>
        <img id="gambar-besar" src="">
    </div>
    
    <div class="container">
        <h2 class="header-title">📊 Rekap Nilai Ujian Siswa</h2>
        
        <div class="filter-box">
            <form method="GET" action="nilai.php" class="filter-form-group">
                <div class="filter-label">Cari:</div>
                <input type="text" name="search" class="form-control" placeholder="Nama / No. Peserta..." value="<?php echo htmlspecialchars($search_filter); ?>" style="max-width: 150px;">

                <div class="filter-label" style="margin-left: 10px;">Nama Ujian:</div>
                <select name="nama_ujian" class="form-control" style="max-width: 180px;">
                    <option value="">-- Semua Ujian --</option>
                    <?php 
                    if (!empty($list_nama_ujian)) {
                        foreach($list_nama_ujian as $nu): 
                            $nama = is_array($nu) ? $nu['nama_ujian'] : $nu;
                            if(!empty($nama)):
                    ?>
                        <option value="<?php echo htmlspecialchars($nama); ?>" <?php if($nama_ujian_filter == $nama) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                    <?php 
                            endif;
                        endforeach; 
                    }
                    ?>
                </select>

                <div class="filter-label" style="margin-left: 10px;">Kelas:</div>
                <select name="kelas" class="form-control" style="max-width: 120px;">
                    <option value="">-- Semua --</option>
                    <?php foreach($list_kelas as $lk): ?>
                        <option value="<?php echo htmlspecialchars($lk['kelas']); ?>" <?php if($kelas_filter == $lk['kelas']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($lk['kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="filter-label" style="margin-left: 10px;">Mapel:</div>
                <select name="mapel" class="form-control" style="max-width: 150px;">
                    <option value="">-- Semua --</option>
                    <?php foreach($list_mapel as $lm): ?>
                        <option value="<?php echo htmlspecialchars($lm['mata_pelajaran']); ?>" <?php if($mapel_filter == $lm['mata_pelajaran']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($lm['mata_pelajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn btn-primary">🔍 Cari</button>
            </form>
            
            <div style="display: flex; gap: 10px;">
                <a href="export_nilai.php?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>" class="btn btn-success">
                    📥 Excel
                </a>
                
                <a href="?export=print&nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>" target="_blank" class="btn btn-info">
                    🖨️ Cetak
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>No. Peserta</th>
                        <th class="text-left">Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Nama Ujian</th>
                        <th>Mapel</th>
                        <th>Nilai</th>
                        <th>Pelanggaran</th>
                        <th>Selfie</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data_nilai)): ?>
                        <tr>
                            <td colspan="10" style="padding: 40px; color: var(--text-muted);">
                                <em>Belum ada data nilai ujian untuk filter tersebut.</em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = ($page === 'all') ? 1 : $offset + 1; foreach ($data_nilai as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['kartu_peserta']); ?></td>
                                <td class="text-left fw-bold"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                                <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                                
                                <td><span class="badge badge-ujian"><?php echo htmlspecialchars($row['nama_ujian'] ?: '-'); ?></span></td>
                                
                                <td><span style="color: var(--secondary); font-weight: 600;"><?php echo htmlspecialchars($row['mata_pelajaran']); ?></span></td>
                                <td><span class="score-text"><?php echo number_format($row['nilai'], 2); ?></span></td>
                                
                                <td>
                                    <?php if($row['jumlah_pelanggaran'] >= 2): ?>
                                        <span class="badge badge-danger">⚠️ <?php echo $row['jumlah_pelanggaran']; ?>x (BATAS)</span>
                                    <?php elseif($row['jumlah_pelanggaran'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $row['jumlah_pelanggaran']; ?>x</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">0x</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if($row['foto_selfie']): ?>
                                        <img src="../assets/<?php echo $row['foto_selfie']; ?>" class="selfie-img" onclick="bukaFoto(this.src)" title="Klik untuk perbesar">
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <a href="detail_nilai.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Detail</a>
                                    <a href="reset_ujian.php?id_ujian=<?php echo $row['id']; ?>&id_siswa=<?php echo $row['siswa_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin mereset ujian siswa ini?')">Reset</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1 && $page !== 'all'): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>&page=<?php echo $page - 1; ?>" class="page-link">&laquo; Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>&page=<?php echo $i; ?>" class="page-link <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>&page=<?php echo $page + 1; ?>" class="page-link">Next &raquo;</a>
                <?php endif; ?>
                
                <a href="?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>&page=all" class="page-link" style="background: var(--info); color: white; border-color: var(--info);">Lihat Semua</a>
            </div>
        <?php elseif ($page === 'all'): ?>
             <div class="pagination">
                 <a href="?nama_ujian=<?php echo urlencode($nama_ujian_filter); ?>&mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&search=<?php echo urlencode($search_filter); ?>&page=1" class="page-link" style="background: var(--text-muted); color: white;">Kembali ke Halaman Terpisah</a>
             </div>
        <?php endif; ?>

        <div class="footer-wrap">
            <?php include "footer.php"; ?>
        </div>
    </div>

    <script>
        function bukaFoto(src) {
            document.getElementById('gambar-besar').src = src;
            document.getElementById('modal-pratinjau').style.display = 'flex';
            document.body.style.overflow = 'hidden'; 
        }
        function tutupFoto() {
            document.getElementById('modal-pratinjau').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        document.getElementById('modal-pratinjau').addEventListener('click', function(e) {
            if (e.target === this) {
                tutupFoto();
            }
        });
    </script>
</body>
</html>