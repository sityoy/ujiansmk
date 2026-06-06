<?php
session_start();
require '../koneksi.php';
// ==============================================================
// SISTEM AUTO-LOGOUT JIKA TIDAK ADA AKTIVITAS SELAMA 1 JAM
// ==============================================================
$timeout_duration = 3600; // 3600 detik = 1 jam

if (isset($_SESSION['last_activity'])) {
    // Hitung selisih waktu sekarang dengan waktu aktivitas terakhir
    $selisih_waktu = time() - $_SESSION['last_activity'];

    // Jika selisihnya lebih dari 1 jam (3600 detik)
    if ($selisih_waktu > $timeout_duration) {
        if (isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];

            // 1. REKAM KE LOG BAHWA SISTEM YANG MENGELUARKAN (Auto-Logout)
            $aktivitas = "Auto-Logout (Sesi habis karena tidak ada aktivitas 1 jam)";
            $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, ?, NOW())");
            $stmtLog->execute([$admin_id, $aktivitas]);

            // 2. BUKA KUNCI LOGIN (Sangat Penting!)
            $stmtUpdate = $pdo->prepare("UPDATE admin SET is_login = 0 WHERE id = ?");
            $stmtUpdate->execute([$admin_id]);
        }

        // 3. Hancurkan sesi lama
        session_unset();
        session_destroy();

        // 4. Buat sesi baru hanya untuk melempar pesan error ke halaman login
        session_start();
        $_SESSION['error_login'] = "⚠️ Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 Jam.";
        header("Location: login.php");
        exit;
    }
}

// PERBARUI WAKTU AKTIVITAS TERAKHIR
$_SESSION['last_activity'] = time();
// ==============================================================

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Ambil daftar mapel unik untuk dropdown filter
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
$list_mapel = $stmtMapel->fetchAll();

// 2. Setup Filter Mapel & Kelas
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$where = "WHERE 1=1";
$params = [];

// Variabel pintar untuk menggabungkan URL filter (Solusi agar pagination stabil)
$q_filter = "";

if ($mapel_filter != '') {
    $where .= " AND mata_pelajaran = ?";
    $params[] = $mapel_filter;
    $q_filter .= "&mapel=" . urlencode($mapel_filter);
}

if ($kelas_filter != '') {
    $where .= " AND kelas = ?";
    $params[] = $kelas_filter;
    $q_filter .= "&kelas=" . urlencode($kelas_filter);
}

// 3. Setup Pagination
$limit = 10; // Menampilkan 10 data per halaman
$page = isset($_GET['page']) ? $_GET['page'] : 1;

// Hitung total data berdasarkan filter
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM soal $where");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();

// Cek apakah user memilih opsi "All"
if ($page === 'all') {
    $limit_query = "";
    $total_pages = 1;
} else {
    $page = (int)$page;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $limit_query = "LIMIT $limit OFFSET $offset";
    $total_pages = ceil($total_data / $limit);
}

// 4. Ambil data soal
$stmtSoal = $pdo->prepare("SELECT * FROM soal $where ORDER BY id ASC $limit_query");
$stmtSoal->execute($params);
$soal_list = $stmtSoal->fetchAll();

// Fungsi aman untuk render Opsi A-E
function render_opsi($text) {
    return $text; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Soal - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .navbar { background: #007bff; padding: 15px; color: white; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; padding: 0 15px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-action { padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: bold; display: inline-block; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 15px; vertical-align: top; }
        th { background-color: #007bff; color: white; text-align: center; }
        .img-thumb { max-width: 120px; display: block; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; padding: 2px;}
        .btn-info { background: #17a2b8; color: white; padding: 8px; text-decoration: none; border-radius: 4px; display: block; text-align: center; margin-bottom: 5px; font-size: 13px;}
        .btn-danger { background: #dc3545; color: white; padding: 8px; text-decoration: none; border-radius: 4px; display: block; text-align: center; font-size: 13px;}
        
        /* CSS Pagination */
        .pagination { margin-top: 20px; text-align: center; padding-bottom: 10px; }
        .pagination a { padding: 8px 12px; margin: 0 3px; border: 1px solid #007bff; color: #007bff; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .pagination a.active { background: #007bff; color: white; }
        .pagination a:hover { background: #0056b3; color: white; }
        .btn-all { background: #28a745 !important; border-color: #28a745 !important; color: white !important; margin-left: 15px !important; }
        .btn-all:hover { background: #218838 !important; }
        
        .info-data { text-align: right; color: #666; font-size: 14px; margin-top: 10px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="card">
    <h3>Daftar Bank Soal</h3>
    
    <div style="margin-bottom: 20px;">
        <a href="tambah_soal.php" class="btn-action" style="background: #007bff; color: white;">+ Tambah Soal Manual</a>
        <a href="upload_soal.php" class="btn-action" style="background: #28a745; color: white; margin-left: 10px;">+ Import Excel</a>
    </div>

    <form method="GET" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
        <div style="display: flex; gap: 10px;">
            <select name="mapel" onchange="this.form.submit()" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 250px;">
                <option value="">-- Semua Mata Pelajaran --</option>
                <?php foreach($list_mapel as $lm): ?>
                    <option value="<?php echo htmlspecialchars($lm['mata_pelajaran']); ?>" <?php echo $mapel_filter == $lm['mata_pelajaran'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lm['mata_pelajaran']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="kelas" onchange="this.form.submit()" style="padding:8px; border-radius:4px; border: 1px solid #ccc;">
                <option value="">Semua Kelas</option>
                <option value="X" <?= $kelas_filter=='X' ? 'selected' : '' ?>>Kelas X</option>
                <option value="XI" <?= $kelas_filter=='XI' ? 'selected' : '' ?>>Kelas XI</option>
                <option value="XII" <?= $kelas_filter=='XII' ? 'selected' : '' ?>>Kelas XII</option>
            </select>
        </div>
        
        <div class="info-data">Total: <strong><?php echo $total_data; ?></strong> Soal</div>
    </form>
    
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="45%">Soal</th>
                <th width="40%">Pilihan Jawaban</th>
                <th width="10%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($soal_list)): ?>
                <tr><td colspan="4" align="center" style="padding: 30px; color: #666;">Tidak ada soal yang ditemukan berdasarkan filter ini.</td></tr>
            <?php else: ?>
                <?php 
                // Kalkulasi nomor urut
                $no = ($page === 'all') ? 1 : (($page - 1) * $limit) + 1; 
                foreach ($soal_list as $s): 
                ?>
                <tr>
                    <td align="center"><strong><?php echo $no++; ?></strong></td>
                    <td>
                        <span style="display:inline-block; background:#e9ecef; padding:3px 8px; border-radius:4px; font-size:12px; margin-bottom:10px; font-weight:bold; color:#007bff;">
                            <?php echo htmlspecialchars($s['kelas']); ?> - <?php echo htmlspecialchars($s['mata_pelajaran']); ?>
                        </span><br>
                        
                        <div style="line-height: 1.6; font-size: 15px; color: #333;">
                            <?php echo $s['deskripsi']; ?>
                        </div>
                        
                        <?php if(!empty($s['gambar'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($s['gambar']); ?>" class="img-thumb">
                        <?php endif; ?>

                        <div style="line-height: 1.6; font-size: 15px; color: #333; margin-top: 5px;">
                            <?php echo $s['pertanyaan']; ?>
                        </div>
                    </td>
                    <td style="line-height: 1.6;">
                        <?php 
                        foreach(['a', 'b', 'c', 'd', 'e'] as $o) {
                            echo "<div style='margin-bottom: 5px;'><strong>" . strtoupper($o) . ".</strong> " . render_opsi($s['opsi_'.$o]) . "</div>";
                            if(!empty($s['gambar_'.$o])) {
                                echo '<img src="../uploads/'.htmlspecialchars($s['gambar_'.$o]).'" class="img-thumb" style="margin-bottom: 10px;">';
                            }
                        }
                        ?>
                        <div style="margin-top: 12px; background: #d4edda; color: #155724; padding: 6px 10px; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block; border: 1px solid #c3e6cb;">
                            Kunci Jawaban: <?php echo htmlspecialchars($s['kunci_jawaban']); ?>
                        </div>
                    </td>
                    <td align="center">
                        <a href="edit_soal.php?id=<?php echo $s['id']; ?>" class="btn-info">✏️ Edit</a>
                        <a href="hapus_soal.php?id=<?php echo $s['id']; ?>" class="btn-danger" onclick="return confirm('Yakin ingin menghapus soal ini?')">🗑️ Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_data > $limit || $page === 'all'): ?>
        <div class="pagination">
            <?php if ($page !== 'all'): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $q_filter; ?>">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $q_filter; ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $q_filter; ?>">Next &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($page !== 'all' && $total_pages > 1): ?>
                <a href="?page=all<?php echo $q_filter; ?>" class="btn-all">Lihat Semua Data (All)</a>
            <?php elseif ($page === 'all'): ?>
                <a href="?page=1<?php echo $q_filter; ?>" class="btn-all">Gunakan Pagination (Kembali)</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php include "footer.php"; ?>
</div>

</body>
</html>