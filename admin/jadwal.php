<?php
// Tampilkan pesan error jika ada masalah agar kita tahu penyebabnya
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'cek_admin.php';

// 1. Ambil daftar mapel otomatis dari Bank Soal
try {
    $stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
    $list_mapel = $stmtMapel->fetchAll();
} catch (Exception $e) {
    die("Error Database (Tabel Soal): " . $e->getMessage());
}

// 2. Proses Tambah atau Update Jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_ujian     = trim($_POST['nama_ujian']);
    $mata_pelajaran = trim($_POST['mata_pelajaran']);
    $waktu_mulai    = $_POST['waktu_mulai'];
    $waktu_selesai  = $_POST['waktu_selesai'];

    // Cegah waktu selesai lebih awal dari waktu mulai
    if (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        echo "<script>alert('ERROR: Waktu Selesai tidak boleh lebih awal atau sama dengan Waktu Mulai!'); window.history.back();</script>";
        exit;
    }

    if (isset($_POST['tambah'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pengaturan_ujian (nama_ujian, mata_pelajaran, waktu_mulai, waktu_selesai) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_ujian, $mata_pelajaran, $waktu_mulai, $waktu_selesai]);
            echo "<script>alert('Jadwal Ujian berhasil ditambahkan!'); window.location='jadwal.php';</script>";
            exit;
        } catch (Exception $e) {
            die("Gagal menambah jadwal: " . $e->getMessage());
        }
    } elseif (isset($_POST['edit'])) {
        $id_jadwal = $_POST['id_jadwal'];
        try {
            $stmt = $pdo->prepare("UPDATE pengaturan_ujian SET nama_ujian=?, mata_pelajaran=?, waktu_mulai=?, waktu_selesai=? WHERE id=?");
            $stmt->execute([$nama_ujian, $mata_pelajaran, $waktu_mulai, $waktu_selesai, $id_jadwal]);
            echo "<script>alert('Jadwal Ujian berhasil diupdate!'); window.location='jadwal.php';</script>";
            exit;
        } catch (Exception $e) {
            die("Gagal mengupdate jadwal: " . $e->getMessage());
        }
    }
}

// 3. Proses Hapus Jadwal
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    try {
        $stmtHapus = $pdo->prepare("DELETE FROM pengaturan_ujian WHERE id = ?");
        $stmtHapus->execute([$id_hapus]);
        echo "<script>alert('Jadwal berhasil dihapus!'); window.location='jadwal.php';</script>";
        exit;
    } catch (Exception $e) {
        die("Gagal menghapus jadwal: " . $e->getMessage());
    }
}

// 4. Ambil data untuk Form Edit
$edit_data = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmtEdit = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
        $stmtEdit->execute([$_GET['edit_id']]);
        $edit_data = $stmtEdit->fetch();
    } catch (Exception $e) {
        die("Gagal memuat data edit: " . $e->getMessage());
    }
}

// 5. Ambil Semua Jadwal untuk Tabel
try {
    $stmtAll = $pdo->query("SELECT * FROM pengaturan_ujian ORDER BY waktu_mulai DESC");
    $semua_jadwal = $stmtAll->fetchAll();
} catch (Exception $e) {
    die("Error Database (Tabel Pengaturan Ujian): " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Jadwal Ujian</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; padding: 20px; color: #333; margin: 0; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #17a2b8; }
        h2 { color: #17a2b8; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .form-box { background: #f8f9fa; padding: 25px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; font-size: 14px; }
        input[type="text"], input[type="datetime-local"], select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 15px; }
        .btn { padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; color: white; transition: 0.3s; font-size: 14px; }
        .btn-primary { background: #007bff; } .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; } .btn-success:hover { background: #218838; }
        .btn-warning { background: #6c757d; text-decoration: none; padding: 12px 20px; color: white; display: inline-block;} .btn-warning:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; text-decoration: none; padding: 8px 12px; border-radius: 4px; font-size: 13px; color: white; }
        .btn-info { background: #17a2b8; text-decoration: none; padding: 8px 12px; border-radius: 4px; font-size: 13px; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; font-size: 14px; }
        th { background: #f1f1f1; color: #333; text-align: center; }
        tr:hover { background-color: #f9f9f9; }
        .status-aktif { color: #28a745; font-weight: bold; }
        .status-tutup { color: #dc3545; font-weight: bold; }
        .status-belum { color: #ffc107; font-weight: bold; }
        .nav-atas { margin-bottom: 20px; }
        .nav-atas a { background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-atas">
            <a href="index.php">⬅️ Kembali ke Dashboard</a>
        </div>
        
        <h2>📅 Kelola Jadwal Ujian</h2>

        <div class="form-box">
            <form method="POST" action="jadwal.php">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id_jadwal" value="<?php echo $edit_data['id']; ?>">
                    <h3 style="margin-top:0; color:#007bff;">✏️ Edit Jadwal</h3>
                <?php else: ?>
                    <h3 style="margin-top:0; color:#28a745;">➕ Tambah Jadwal Baru</h3>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Nama Ujian</label>
                        <input type="text" name="nama_ujian" value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_ujian']) : ''; ?>" placeholder="Contoh: PAS Semester 1" required>
                    </div>
                    <div class="form-group">
                        <label>Mata Pelajaran (Diambil dari Bank Soal)</label>
                        <select name="mata_pelajaran" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach($list_mapel as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['mata_pelajaran']); ?>" <?php echo ($edit_data && $edit_data['mata_pelajaran'] == $m['mata_pelajaran']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['mata_pelajaran']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Waktu Mulai Ujian</label>
                        <input type="datetime-local" name="waktu_mulai" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_mulai'])) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Waktu Selesai Ujian</label>
                        <input type="datetime-local" name="waktu_selesai" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_selesai'])) : ''; ?>" required>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <?php if($edit_data): ?>
                        <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="jadwal.php" class="btn btn-warning">Batal</a>
                    <?php else: ?>
                        <button type="submit" name="tambah" class="btn btn-success">Simpan Jadwal</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th>Nama Ujian</th>
                    <th>Mata Pelajaran</th>
                    <th>Waktu Mulai</th>
                    <th>Waktu Selesai</th>
                    <th>Status</th>
                    <th width="15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $sekarang = date('Y-m-d H:i:s');
                foreach($semua_jadwal as $j): 
                    
                    // Deteksi Status Ujian
                    if ($sekarang < $j['waktu_mulai']) {
                        $status = "<span class='status-belum'>⏳ Belum Mulai</span>";
                    } elseif ($sekarang >= $j['waktu_mulai'] && $sekarang <= $j['waktu_selesai']) {
                        $status = "<span class='status-aktif'>🟢 Aktif</span>";
                    } else {
                        $status = "<span class='status-tutup'>🔴 Selesai</span>";
                    }
                ?>
                <tr>
                    <td align="center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($j['nama_ujian']); ?></td>
                    <td><strong style="color: #007bff;"><?php echo htmlspecialchars($j['mata_pelajaran']); ?></strong></td>
                    <td align="center"><?php echo date('d-M-Y H:i', strtotime($j['waktu_mulai'])); ?></td>
                    <td align="center"><?php echo date('d-M-Y H:i', strtotime($j['waktu_selesai'])); ?></td>
                    <td align="center"><?php echo $status; ?></td>
                    <td align="center">
                        <div style="display: flex; gap: 5px; justify-content: center;">
                            <a href="jadwal.php?edit_id=<?php echo $j['id']; ?>" class="btn-info">Edit</a>
                            <a href="jadwal.php?hapus=<?php echo $j['id']; ?>" class="btn-danger" onclick="return confirm('Hapus jadwal mapel ini?')">Hapus</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($semua_jadwal)): ?>
                <tr><td colspan="7" align="center" style="padding: 20px; color: #777;">Belum ada jadwal ujian.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php include "footer.php"; ?>
    </div>
</body>
</html>