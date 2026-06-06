<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Ambil daftar mapel unik untuk dropdown
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM ujian_siswa WHERE mata_pelajaran IS NOT NULL");
$list_mapel = $stmtMapel->fetchAll();

// 2. Ambil daftar kelas unik dari tabel siswa untuk dropdown
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
$list_kelas = $stmtKelas->fetchAll();

// 3. Tangkap nilai filter dari URL (GET)
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$limit = 20; 

$where = "WHERE 1=1";
$params = [];

// Tambahkan kondisi pencarian berdasarkan Mapel
if ($mapel_filter != '') {
    $where .= " AND u.mata_pelajaran = ?";
    $params[] = $mapel_filter;
}

// Tambahkan kondisi pencarian berdasarkan Kelas
if ($kelas_filter != '') {
    $where .= " AND s.kelas = ?";
    $params[] = $kelas_filter;
}

// 4. Hitung total data untuk Pagination (WAJIB pakai JOIN ke s karena filter kelas ada di tabel siswa)
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ujian_siswa u JOIN siswa s ON u.siswa_id = s.id $where");
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

// 5. Query utama untuk mengambil data nilai
$query = "SELECT u.*, s.kartu_peserta, s.nama_siswa, s.kelas 
          FROM ujian_siswa u 
          JOIN siswa s ON u.siswa_id = s.id 
          $where ORDER BY u.id DESC $limit_query";
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
        body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; min-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .btn-primary { background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; border: none; cursor: pointer;}
        .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; }
        .pagination a { padding: 8px 12px; border: 1px solid #007bff; text-decoration: none; color: #007bff; border-radius: 3px; }
        .pagination a.active, .pagination a:hover { background: #007bff; color: white; }
        .filter-box { margin-bottom: 15px; background: #e9ecef; padding: 15px; border-radius: 5px; }
        
        /* Modal Pratinjau */
        #modal-pratinjau { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center; flex-direction: column; }
        #modal-pratinjau img { max-width: 90%; max-height: 85vh; border-radius: 8px; }
        .close-btn { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div id="modal-pratinjau">
        <span class="close-btn" onclick="tutupFoto()">&times;</span>
        <img id="gambar-besar" src="">
    </div>

    <div class="card">
        <h2>Rekap Nilai Ujian Siswa</h2>
        
        <div class="filter-box">
            <form method="GET" action="nilai.php" style="display:flex; align-items:center; gap: 10px; flex-wrap:wrap;">
                
                <label><strong>Filter Kelas:</strong></label>
                <select name="kelas" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; max-width: 200px;">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach($list_kelas as $lk): ?>
                        <option value="<?php echo htmlspecialchars($lk['kelas']); ?>" <?php if($kelas_filter == $lk['kelas']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($lk['kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label><strong>Mapel:</strong></label>
                <select name="mapel" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; max-width: 200px;">
                    <option value="">-- Semua Mapel --</option>
                    <?php foreach($list_mapel as $lm): ?>
                        <option value="<?php echo htmlspecialchars($lm['mata_pelajaran']); ?>" <?php if($mapel_filter == $lm['mata_pelajaran']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($lm['mata_pelajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-primary">Tampilkan</button>
                <a href="export_nilai.php?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>" style="background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-left: 10px;">
                📥 Download Excel
                </a>
            </form>
        </div>

        <a href="export_nilai.php?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>" class="btn-primary" style="display:inline-block; margin-bottom:15px; text-decoration:none;">📥 Export Nilai Excel</a>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>No. Peserta</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Mapel</th>
                    <th>Nilai</th>
                    <th>Pelanggaran</th>
                    <th>Selfie</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data_nilai)): ?>
                    <tr><td colspan="9">Belum ada data nilai.</td></tr>
                <?php else: ?>
                    <?php $no = ($page === 'all') ? 1 : $offset + 1; foreach ($data_nilai as $row): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kartu_peserta']); ?></td>
                            <td style="text-align: left;"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                            <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                            <td><?php echo htmlspecialchars($row['mata_pelajaran']); ?></td>
                            <td><strong style="font-size: 18px; color: #28a745;"><?php echo number_format($row['nilai'], 2); ?></strong></td>
                            
                            <td>
                                <?php if($row['jumlah_pelanggaran'] >= 2): ?>
                                    <span style="background: #dc3545; color: white; padding: 5px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; display: inline-block;">
                                        ⚠️ <?php echo $row['jumlah_pelanggaran']; ?>x (BATAS)
                                    </span>
                                <?php elseif($row['jumlah_pelanggaran'] > 0): ?>
                                    <span style="color: #d39e00; font-weight: bold;"><?php echo $row['jumlah_pelanggaran']; ?>x</span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">0x</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if($row['foto_selfie']): ?>
                                    <img src="../assets/<?php echo $row['foto_selfie']; ?>" width="50" style="border-radius: 5px; cursor: zoom-in; border: 1px solid #ccc;" onclick="bukaFoto(this.src)">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <a href="detail_nilai.php?id=<?php echo $row['id']; ?>" class="btn-primary" style="display:block; margin-bottom:5px;">Detail</a>
                                <a href="reset_ujian.php?id_ujian=<?php echo $row['id']; ?>&id_siswa=<?php echo $row['siswa_id']; ?>" class="btn-danger" style="display:block;" onclick="return confirm('Yakin reset ujian?')">Reset</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1 && $page !== 'all'): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&page=<?php echo $i; ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
                
                <a href="?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&page=all" style="background: #17a2b8; color: white; border-color: #17a2b8;">Lihat Semua</a>
            </div>
        <?php elseif ($page === 'all'): ?>
             <div class="pagination"><a href="?mapel=<?php echo urlencode($mapel_filter); ?>&kelas=<?php echo urlencode($kelas_filter); ?>&page=1" style="background: #6c757d; color: white;">Kembali ke Pagination</a></div>
        <?php endif; ?>

        <?php include "footer.php"; ?>
    </div>

    <script>
        // Script untuk pop up foto
        function bukaFoto(src) {
            document.getElementById('gambar-besar').src = src;
            document.getElementById('modal-pratinjau').style.display = 'flex';
        }
        function tutupFoto() {
            document.getElementById('modal-pratinjau').style.display = 'none';
        }
    </script>
</body>
</html>
