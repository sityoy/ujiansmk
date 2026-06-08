<?php
require 'cek_admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Tambahkan pengaman null coalescing (?? '') agar tidak ada error undefined key
    $kartu_peserta = trim($_POST['kartu_peserta'] ?? '');
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if($kartu_peserta != '' && $nama_siswa != '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO siswa (kartu_peserta, nama_siswa, kelas, password, status_ujian) VALUES (?, ?, ?, ?, 'belum')");
            $stmt->execute([$kartu_peserta, $nama_siswa, $kelas, $password]);
            echo "<script>alert('Siswa berhasil ditambahkan!'); window.location='index.php';</script>";
        } catch (Exception $e) {
            // Tangkap jika No Peserta Duplikat
            if($e->getCode() == 23000) {
                echo "<script>alert('Gagal: Nomor Peserta sudah digunakan!');</script>";
            } else {
                die("Gagal menyimpan data: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Siswa</title>
    <style>
        body { font-family: Arial; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 5px; max-width: 500px; margin: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-top: 4px solid #28a745; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #28a745; color: white; padding: 12px; border: none; width: 100%; cursor: pointer; font-weight: bold; border-radius: 4px; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; padding: 10px; display: block; text-align: center; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h3>Tambah Siswa Baru</h3>
        <form method="POST">
            <label>kartu_peserta:</label>
            <input type="text" name="kartu_peserta" required placeholder="Contoh: 100123">

            <label>Nama Siswa Lengkap:</label>
            <input type="text" name="nama_siswa" required placeholder="Masukkan nama_siswa siswa">

            <label>Kelas / Jurusan:</label>
            <input type="text" name="kelas" required placeholder="Contoh: XII AK 1">

            <label>Password Ujian:</label>
            <input type="text" name="password" required placeholder="Minimal 6 karakter">

            <button type="submit" class="btn">Simpan Data Siswa</button>
            <a href="index.php" class="btn-batal">Batal</a>
        </form>
        <?php include "footer.php"; ?>
    </div>
</body>
</html>