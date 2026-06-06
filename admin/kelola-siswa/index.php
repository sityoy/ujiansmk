<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['admin_id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

// --- SETUP FILTER KELAS ---
$filter_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$where_sql = "";

if ($filter_kelas === 'XII') {
    $where_sql = "WHERE kelas LIKE 'XII%'";
} elseif ($filter_kelas === 'XI') {
    $where_sql = "WHERE kelas LIKE 'XI%'";
} elseif ($filter_kelas === 'X') {
    // Agar pencarian "X" tidak memunculkan "XI" atau "XII"
    $where_sql = "WHERE kelas LIKE 'X%' AND kelas NOT LIKE 'XI%' AND kelas NOT LIKE 'XII%'";
}

// --- SETUP PAGINATION ---
$limit = 20; // Batas data per halaman (Diubah menjadi 20)
$page = isset($_GET['page']) ? $_GET['page'] : 1;

// Hitung total data siswa (dengan filter)
$stmtCount = $pdo->query("SELECT COUNT(*) FROM siswa $where_sql");
$total_data = $stmtCount->fetchColumn();

// Logika jika memilih "All"
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

// Ambil data siswa berdasarkan limit & filter
$stmt = $pdo->query("SELECT * FROM siswa $where_sql ORDER BY kelas ASC, nama_siswa ASC $limit_query");
$siswa_list = $stmt->fetchAll();

// Parameter URL untuk pagination agar filter kelas tetap terbawa saat pindah halaman
$q_kelas = $filter_kelas ? "&kelas=".urlencode($filter_kelas) : "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Kelola Siswa</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body { font-family: 'Inter', sans-serif; background: #f4f7fb; margin: 0; padding: 20px; color: #333; }
        
        .header-nav { margin-bottom: 20px; }
        .btn-back-dash { background: #fff; color: #4f46e5; border: 1px solid #e0e7ff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-back-dash:hover { background: #4f46e5; color: #fff; }

        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #f1f3f5; }
        
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        h2 { color: #1e293b; margin: 0; font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        
        /* Grup Tombol Aksi & Filter */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .action-group { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .filter-group { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #475569; font-size: 14px; }
        .filter-select { padding: 9px 15px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; color: #334155; font-weight: 500; cursor: pointer; outline: none; transition: 0.2s; background: #fff; }
        .filter-select:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .btn { padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; font-size: 14px; border: none; cursor: pointer; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-success { background: #10b981; }
        .btn-excel { background: #059669; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-info { background: #0ea5e9; padding: 6px 12px; font-size: 13px; border-radius: 5px; }
        .btn-danger { background: #ef4444; padding: 6px 12px; font-size: 13px; border-radius: 5px; }
        
        .info-data { font-size: 14px; color: #64748b; font-weight: 600; background: #e2e8f0; padding: 6px 12px; border-radius: 20px; }
        
        /* Tabel Modern */
        .table-responsive { overflow-x: auto; border-radius: 8px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; background: #fff; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 15px; text-align: left; font-size: 14px; }
        th { background-color: #f8fafc; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        tr:hover { background-color: #f1f5f9; }
        
        .badge-id { background: #e2e8f0; color: #475569; padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: 700; font-family: monospace; }
        .badge-peserta { background: #e0e7ff; color: #4f46e5; padding: 5px 10px; border-radius: 6px; font-size: 13px; font-weight: 700; border: 1px solid #c7d2fe; }
        .password-box { font-family: monospace; background: #fef3c7; color: #b45309; padding: 5px 10px; border-radius: 6px; font-size: 13px; font-weight: 700; border: 1px solid #fde68a; }
        .nama-siswa { font-weight: 600; color: #1e293b; font-size: 15px; }
        .kelas-text { font-weight: 600; color: #0f766e; }

        /* Pagination CSS */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 15px; }
        .pagination { display: flex; gap: 5px; flex-wrap: wrap; }
        .pagination a { padding: 8px 15px; border: 1px solid #cbd5e1; text-decoration: none; color: #475569; border-radius: 6px; font-size: 14px; font-weight: 600; transition: 0.2s; background: #fff; }
        .pagination a.active { background: #4f46e5; color: white; border-color: #4f46e5; }
        .pagination a:hover:not(.active) { background: #f1f5f9; border-color: #94a3b8; }
        
        .btn-all { background: #0f172a; color: white !important; border-color: #0f172a !important; margin-left: 10px; }
        .btn-all:hover { background: #334155; }

        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            .action-group { justify-content: space-between; }
            .action-group .btn { flex-grow: 1; justify-content: center; }
            .filter-group { justify-content: space-between; width: 100%; }
            .filter-select { flex-grow: 1; }
            .pagination-container { justify-content: center; }
        }
    </style>
</head>
<body>
    
    <div class="header-nav">
        <a href="../index.php" class="btn-back-dash">⬅️ Kembali ke Dashboard</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>👥 Data Peserta Ujian</h2>
            <div class="info-data">Total: <?php echo $total_data; ?> Siswa</div>
        </div>
        
        <div class="toolbar">
            <div class="action-group">
                <a href="tambah_siswa.php" class="btn btn-success">➕ Tambah Siswa</a>
                <a href="import_siswa.php" class="btn btn-excel">📥 Import Excel</a>
                <a href="cetak_kartu.php" target="_blank" class="btn btn-warning">🖨️ Cetak Kartu</a>
            </div>
            
            <div class="filter-group">
                <label for="filterKelas">Filter Kelas:</label>
                <form method="GET" action="" id="formFilter">
                    <?php if($page === 'all'): ?>
                        <input type="hidden" name="page" value="all">
                    <?php endif; ?>
                    <select name="kelas" id="filterKelas" class="filter-select" onchange="document.getElementById('formFilter').submit();">
                        <option value="">Semua Kelas</option>
                        <option value="X" <?php echo ($filter_kelas == 'X') ? 'selected' : ''; ?>>Kelas X</option>
                        <option value="XI" <?php echo ($filter_kelas == 'XI') ? 'selected' : ''; ?>>Kelas XI</option>
                        <option value="XII" <?php echo ($filter_kelas == 'XII') ? 'selected' : ''; ?>>Kelas XII</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="15%">No Peserta</th>
                        <th width="30%">Nama Lengkap</th>
                        <th width="15%">Kelas</th>
                        <th width="15%">Password</th>
                        <th width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color:#64748b; font-size: 15px;">Belum ada data siswa untuk kelas ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($siswa_list as $s): ?>
                        <tr>
                            <td><span class="badge-id">#<?php echo htmlspecialchars($s['id']); ?></span></td>
                            
                            <td><span class="badge-peserta"><?php echo htmlspecialchars($s['kartu_peserta']); ?></span></td>
                            <td class="nama-siswa"><?php echo htmlspecialchars($s['nama_siswa']); ?></td>
                            <td class="kelas-text"><?php echo htmlspecialchars($s['kelas']); ?></td>
                            <td><span class="password-box"><?php echo htmlspecialchars($s['password']); ?></span></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="edit_siswa.php?id=<?php echo $s['id']; ?>" class="btn btn-info">✏️ Edit</a>
                                    <a href="hapus_siswa.php?id=<?php echo $s['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus siswa ini?')">🗑️ Hapus</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_data > $limit || $page === 'all'): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($page !== 'all'): ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1 . $q_kelas; ?>">&laquo; Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i . $q_kelas; ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1 . $q_kelas; ?>">Next &raquo;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="pagination-action">
                    <?php if ($page !== 'all' && $total_pages > 1): ?>
                        <a href="?page=all<?php echo $q_kelas; ?>" class="btn btn-all">Tampilkan Semua Data (All)</a>
                    <?php elseif ($page === 'all'): ?>
                        <a href="?page=1<?php echo $q_kelas; ?>" class="btn btn-all">Gunakan Pagination (Per 20 Data)</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    
    <?php include "footer.php"; ?>

</body>
</html>