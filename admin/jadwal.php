<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 1. Ambil daftar mapel otomatis dari Bank Soal untuk Dropdown
$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
$list_mapel = $stmtMapel->fetchAll();

// 2. Proses Tambah atau Update Jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_ujian = $_POST['nama_ujian'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];

    if (isset($_POST['tambah'])) {
        $stmt = $pdo->prepare("INSERT INTO pengaturan_ujian (nama_ujian, mata_pelajaran, waktu_mulai, waktu_selesai) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nama_ujian, $mata_pelajaran, $waktu_mulai, $waktu_selesai]);
        echo "<script>alert('Jadwal Ujian berhasil ditambahkan!'); window.location='jadwal.php';</script>";
    } elseif (isset($_POST['edit'])) {
        $id_jadwal = $_POST['id_jadwal'];
        $stmt = $pdo->prepare("UPDATE pengaturan_ujian SET nama_ujian=?, mata_pelajaran=?, waktu_mulai=?, waktu_selesai=? WHERE id=?");
        $stmt->execute([$nama_ujian, $mata_pelajaran, $waktu_mulai, $waktu_selesai, $id_jadwal]);
        echo "<script>alert('Jadwal Ujian berhasil diperbarui!'); window.location='jadwal.php';</script>";
    }
}

// 3. Proses Hapus Jadwal
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM pengaturan_ujian WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Jadwal berhasil dihapus!'); window.location='jadwal.php';</script>";
}

// 4. Mode Edit (Jika tombol Edit diklik)
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
    $stmtEdit->execute([$_GET['edit_id']]);
    $edit_data = $stmtEdit->fetch();
}

// Ambil semua daftar jadwal
$stmtJadwal = $pdo->query("SELECT * FROM pengaturan_ujian ORDER BY waktu_mulai DESC");
$list_jadwal = $stmtJadwal->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Jadwal Ujian</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .navbar { background: #007bff; padding: 15px; color: white; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar a { color: white; text-decoration: none; font-weight: bold; padding: 0 15px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; color: #333; }
        input[type="text"], input[type="datetime-local"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        .btn { background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-warning { background: #ffc107; color: #333; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-danger { background: #dc3545; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 13px; }
        .btn-info { background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 13px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <!-- FORM PENGATURAN JADWAL -->
    <div class="card" style="border-top: 4px solid <?php echo $edit_data ? '#ffc107' : '#28a745'; ?>;">
        <h3><?php echo $edit_data ? 'Edit Jadwal Ujian' : 'Buat Jadwal Ujian Baru'; ?></h3>
        
        <form method="POST">
            <?php if($edit_data): ?>
                <input type="hidden" name="id_jadwal" value="<?php echo $edit_data['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Nama Ujian (Contoh: UUB Semester Genap):</label>
                <input type="text" name="nama_ujian" value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_ujian']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Pilih Mata Pelajaran:</label>
                <select name="mata_pelajaran" required>
                    <option value="">-- Pilih Mapel dari Bank Soal --</option>
                    <?php foreach($list_mapel as $lm): ?>
                        <option value="<?php echo htmlspecialchars($lm['mata_pelajaran']); ?>" 
                            <?php if($edit_data && $edit_data['mata_pelajaran'] == $lm['mata_pelajaran']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($lm['mata_pelajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666;">*Jika mapel tidak muncul, pastikan Bapak sudah menambahkan soal untuk mapel tersebut di menu Kelola Soal.</small>
            </div>

            <div class="form-group">
                <label>Waktu Mulai:</label>
                <input type="datetime-local" name="waktu_mulai" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_mulai'])) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Waktu Selesai (Otomatis ditutup):</label>
                <input type="datetime-local" name="waktu_selesai" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_selesai'])) : ''; ?>" required>
            </div>
            
            <?php if($edit_data): ?>
                <button type="submit" name="edit" class="btn-warning">Update Jadwal</button>
                <a href="jadwal.php" class="btn-danger" style="padding: 10px 15px; display: inline-block;">Batal Edit</a>
            <?php else: ?>
                <button type="submit" name="tambah" class="btn">Simpan Jadwal Baru</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABEL DAFTAR JADWAL -->
    <div class="card">
        <h3>Daftar Jadwal Ujian Tersedia</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Ujian</th>
                    <th>Mata Pelajaran</th>
                    <th>Waktu Mulai</th>
                    <th>Waktu Selesai</th>
                    <th>Status Saat Ini</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($list_jadwal)): ?>
                    <tr><td colspan="7">Belum ada jadwal yang diatur.</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach($list_jadwal as $j): 
                        $sekarang = date('Y-m-d H:i:s');
                        $status = "<span style='color:#ffc107; font-weight:bold;'>⏳ Belum Mulai</span>";
                        
                        if($sekarang >= $j['waktu_mulai'] && $sekarang <= $j['waktu_selesai']) {
                            $status = "<span style='color:#28a745; font-weight:bold;'>🟢 Sedang Berjalan</span>";
                        } elseif($sekarang > $j['waktu_selesai']) {
                            $status = "<span style='color:#dc3545; font-weight:bold;'>🔴 Selesai/Ditutup</span>";
                        }
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($j['nama_ujian']); ?></td>
                        <td><strong style="color: #007bff;"><?php echo htmlspecialchars($j['mata_pelajaran']); ?></strong></td>
                        <td><?php echo date('d-M-Y H:i', strtotime($j['waktu_mulai'])); ?></td>
                        <td><?php echo date('d-M-Y H:i', strtotime($j['waktu_selesai'])); ?></td>
                        <td><?php echo $status; ?></td>
                        <td>
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <a href="jadwal.php?edit_id=<?php echo $j['id']; ?>" class="btn-info">Edit</a>
                                <a href="jadwal.php?hapus=<?php echo $j['id']; ?>" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal mapel ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php include "footer.php"; ?>
    </div>

</body>
</html>