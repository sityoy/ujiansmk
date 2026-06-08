<?php
// session_start();
require 'cek_admin.php';
// ==============================================================
// SISTEM AUTO-LOGOUT JIKA TIDAK ADA AKTIVITAS SELAMA 1 JAM
// ==============================================================
// $timeout_duration = 3600; // 3600 detik = 1 jam

// if (isset($_SESSION['last_activity'])) {
//     $selisih_waktu = time() - $_SESSION['last_activity'];

//     if ($selisih_waktu > $timeout_duration) {
//         if (isset($_SESSION['admin_id'])) {
//             $admin_id = $_SESSION['admin_id'];

//             $aktivitas = "Auto-Logout (Sesi habis karena tidak ada aktivitas 1 jam)";
//             $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, ?, NOW())");
//             $stmtLog->execute([$admin_id, $aktivitas]);

//             $stmtUpdate = $pdo->prepare("UPDATE admin SET is_login = 0 WHERE id = ?");
//             $stmtUpdate->execute([$admin_id]);
//         }

//         session_unset();
//         session_destroy();

//         session_start();
//         $_SESSION['error_login'] = "⚠️ Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 Jam.";
//         header("Location: login.php");
//         exit;
//     }
// }

// $_SESSION['last_activity'] = time();
// ==============================================================

// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit;
// }

// 1. Ambil daftar mapel unik untuk dropdown filter
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
$list_mapel = $stmtMapel->fetchAll();

// 2. Setup Filter Mapel & Kelas
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$where = "WHERE 1=1";
$params = [];
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
$limit = 10; 
$page = isset($_GET['page']) ? $_GET['page'] : 1;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM soal $where");
$stmtCount->execute($params);
$total_data = $stmtCount->fetchColumn();

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
        :root { --primary: #4361ee; --success: #2ecc71; --danger: #e74c3c; --info: #17a2b8; --bg: #f4f7f6; --text: #2b2d42; --border: #e2e8f0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: var(--text); }
        
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-top: 5px solid var(--primary); margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid var(--border); padding-bottom: 15px; }
        .card-header h3 { margin: 0; font-size: 24px; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        
        /* Tombol Aksi Atas */
        .btn-action { padding: 10px 18px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); opacity: 0.9; }
        .btn-add { background: var(--primary); color: white; }
        .btn-import { background: var(--success); color: white; margin-left: 10px; }

        /* Filter Controls */
        .filter-bar { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 15px 20px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .filter-group { display: flex; gap: 12px; align-items: center; }
        select { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e0; outline: none; transition: 0.3s; font-family: inherit; font-size: 14px; background: white; cursor: pointer; }
        select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15); }
        .info-data { color: #64748b; font-size: 14.5px; }
        .info-data strong { color: var(--primary); font-size: 16px; }

        /* Desain Tabel */
        .table-container { overflow-x: auto; border-radius: 8px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 15px 20px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px; white-space: nowrap; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f1f5f9; }
        
        .col-no { text-align: center; width: 50px; font-weight: bold; color: #64748b; }
        .col-aksi { text-align: center; width: 120px; }

        /* Badges & Content */
        .badge-mapel { display: inline-block; background: #e0e7ff; color: #4361ee; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 12px; border: 1px solid #c7d2fe; }
        .badge-kunci { display: inline-block; margin-top: 15px; background: #dcfce7; color: #166534; padding: 8px 15px; border-radius: 6px; font-weight: bold; font-size: 13px; border: 1px solid #bbf7d0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        
        .content-text { line-height: 1.6; font-size: 14.5px; color: #334155; }
        .opsi-item { margin-bottom: 8px; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--border); border-radius: 6px; }
        
        /* Tombol Tabel */
        .btn-table { padding: 8px 12px; text-decoration: none; border-radius: 6px; display: inline-block; font-size: 13px; font-weight: bold; transition: 0.2s; width: 100%; box-sizing: border-box; text-align: center; }
        .btn-table:hover { opacity: 0.8; transform: translateY(-1px); }
        .btn-edit { background: var(--info); color: white; margin-bottom: 8px; }
        .btn-delete { background: var(--danger); color: white; }

        /* Pagination */
        .pagination { margin-top: 25px; text-align: center; display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 8px; }
        .pagination a { padding: 8px 15px; border: 1px solid var(--border); color: #64748b; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; background: white; transition: 0.2s; }
        .pagination a.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 2px 4px rgba(67, 97, 238, 0.2); }
        .pagination a:hover:not(.active) { background: #f1f5f9; color: var(--primary); border-color: #cbd5e0; }
        .btn-all { background: #f8fafc !important; color: var(--primary) !important; border-color: var(--primary) !important; margin-left: 10px; }
        .btn-all:hover { background: var(--primary) !important; color: white !important; }

        /* === PRATINJAU GAMBAR (MODAL ZOOM) === */
        .img-thumb { max-width: 150px; max-height: 100px; display: block; margin: 10px 0; border: 1px solid #cbd5e0; border-radius: 6px; padding: 3px; background: white; object-fit: contain; }
        .img-zoomable { cursor: zoom-in; transition: transform 0.2s, box-shadow 0.2s; }
        .img-zoomable:hover { transform: scale(1.05); box-shadow: 0 4px 10px rgba(0,0,0,0.15); border-color: var(--primary); }
        
        .modal-zoom { display: none; position: fixed; z-index: 999999; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); justify-content: center; align-items: center; cursor: zoom-out; }
        .modal-zoom img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); object-fit: contain; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div id="previewModal" class="modal-zoom" onclick="tutupPreview()">
    <img id="imgPreview" src="" alt="Pratinjau Gambar">
</div>

<div class="card">
    <div class="card-header">
        <h3>📚 Daftar Bank Soal Ujian</h3>
        <div>
            <a href="tambah_soal.php" class="btn-action btn-add">➕ Tambah Manual</a>
            <a href="upload_soal.php" class="btn-action btn-import">📁 Import Excel</a>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <select name="mapel" onchange="this.form.submit()">
                <option value="">-- Semua Mata Pelajaran --</option>
                <?php foreach($list_mapel as $lm): ?>
                    <option value="<?php echo htmlspecialchars($lm['mata_pelajaran']); ?>" <?php echo $mapel_filter == $lm['mata_pelajaran'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lm['mata_pelajaran']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="kelas" onchange="this.form.submit()">
                <option value="">-- Semua Kelas --</option>
                <option value="X" <?= $kelas_filter=='X' ? 'selected' : '' ?>>Kelas X</option>
                <option value="XI" <?= $kelas_filter=='XI' ? 'selected' : '' ?>>Kelas XI</option>
                <option value="XII" <?= $kelas_filter=='XII' ? 'selected' : '' ?>>Kelas XII</option>
            </select>
        </div>
        
        <div class="info-data">Total: <strong><?php echo $total_data; ?></strong> Soal Terdaftar</div>
    </form>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th width="45%">Detail Soal</th>
                    <th width="40%">Pilihan Jawaban</th>
                    <th class="col-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($soal_list)): ?>
                    <tr><td colspan="4" align="center" style="padding: 40px; color: #94a3b8; font-size: 15px;">🔍 Tidak ada soal yang ditemukan berdasarkan filter ini.</td></tr>
                <?php else: ?>
                    <?php 
                    $no = ($page === 'all') ? 1 : (($page - 1) * $limit) + 1; 
                    foreach ($soal_list as $s): 
                    ?>
                    <tr>
                        <td class="col-no"><?php echo $no++; ?></td>
                        <td>
                            <span class="badge-mapel">
                                <?php echo htmlspecialchars($s['kelas']); ?> - <?php echo htmlspecialchars($s['mata_pelajaran']); ?>
                            </span>
                            
                            <div class="content-text">
                                <?php echo $s['deskripsi']; ?>
                            </div>
                            
                            <?php if(!empty($s['gambar'])): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($s['gambar']); ?>" class="img-thumb img-zoomable" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar">
                            <?php endif; ?>

                            <div class="content-text" style="margin-top: 10px; font-weight: 500;">
                                <?php echo $s['pertanyaan']; ?>
                            </div>
                        </td>
                        <td class="content-text">
                            <?php 
                            foreach(['a', 'b', 'c', 'd', 'e'] as $o) {
                                echo "<div class='opsi-item'><strong>" . strtoupper($o) . ".</strong> " . render_opsi($s['opsi_'.$o]) . "</div>";
                                if(!empty($s['gambar_'.$o])) {
                                    // Gambar Opsi Zoomable
                                    echo '<img src="../uploads/'.htmlspecialchars($s['gambar_'.$o]).'" class="img-thumb img-zoomable" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar" style="margin-bottom: 12px; margin-left: 5px;">';
                                }
                            }
                            ?>
                            <div class="badge-kunci">
                                🎯 Kunci Jawaban: <?php echo htmlspecialchars($s['kunci_jawaban']); ?>
                            </div>
                        </td>
                        <td class="col-aksi">
                            <a href="edit_soal.php?id=<?php echo $s['id']; ?>" class="btn-table btn-edit">✏️ Edit</a>
                            <a href="hapus_soal.php?id=<?php echo $s['id']; ?>" class="btn-table btn-delete" onclick="return confirm('Yakin ingin menghapus soal ini?')">🗑️ Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
                <a href="?page=all<?php echo $q_filter; ?>" class="btn-all">👁️ Lihat Semua Data (All)</a>
            <?php elseif ($page === 'all'): ?>
                <a href="?page=1<?php echo $q_filter; ?>" class="btn-all">📄 Gunakan Pagination (Halaman)</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>

<script>
    function bukaPreview(srcGambar) {
        document.getElementById('imgPreview').src = srcGambar;
        document.getElementById('previewModal').style.display = 'flex';
    }

    function tutupPreview() {
        document.getElementById('previewModal').style.display = 'none';
    }
</script>

</body>
</html>