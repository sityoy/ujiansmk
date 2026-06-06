<?php
require 'cek_admin.php';

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) { die("Siswa tidak ditemukan!"); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // PERBAIKAN: Sesuaikan dengan 'name' yang ada di form bawah
    $kartu_peserta = trim($_POST['kartu_peserta']);
    $nama_siswa = trim($_POST['nama_siswa']);
    $kelas = trim($_POST['kelas']);
    $password = trim($_POST['password']);

    try {
        // PERBAIKAN: Sesuaikan dengan nama kolom di database (kartu_peserta, nama_siswa)
        $stmtUpdate = $pdo->prepare("UPDATE siswa SET kartu_peserta=?, nama_siswa=?, kelas=?, password=? WHERE id=?");
        $stmtUpdate->execute([$kartu_peserta, $nama_siswa, $kelas, $password, $id]);
        echo "<script>alert('Data siswa berhasil diupdate!'); window.location='index.php';</script>";
    } catch (Exception $e) {
        die("Gagal mengupdate data: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Siswa</title>
    <style>
        body { font-family: Arial; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 5px; max-width: 500px; margin: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-top: 4px solid #17a2b8; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #17a2b8; color: white; padding: 12px; border: none; width: 100%; cursor: pointer; font-weight: bold; border-radius: 4px; }
        .btn:hover { background: #138496; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; padding: 10px; display: block; text-align: center; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h3 style="color: #17a2b8; margin-top: 0;">✏️ Edit Data Siswa</h3>
        <form method="POST">
            <label>No Peserta (Kartu):</label>
            <input type="text" name="kartu_peserta" value="<?php echo htmlspecialchars($siswa['kartu_peserta']); ?>" required>

            <label>Nama Lengkap:</label>
            <input type="text" name="nama_siswa" value="<?php echo htmlspecialchars($siswa['nama_siswa']); ?>" required>

            <label>Kelas / Jurusan:</label>
            <input type="text" name="kelas" value="<?php echo htmlspecialchars($siswa['kelas']); ?>" required>

            <label>Password Ujian:</label>
            <input type="text" name="password" value="<?php echo htmlspecialchars($siswa['password']); ?>" required>

            <button type="submit" class="btn">💾 Simpan Perubahan</button>
            <a href="index.php" class="btn-batal">❌ Batal</a>
        </form>
    </div>
</body>
</html>