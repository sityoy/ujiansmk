<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id_ujian']) && isset($_GET['id_siswa'])) {
    $id_ujian = $_GET['id_ujian'];
    $id_siswa = $_GET['id_siswa'];

    try {
        $pdo->beginTransaction();

        // 1. Ambil nama file foto selfie untuk dihapus dari folder assets agar tidak menuh-menuhin hosting
        $stmtFoto = $pdo->prepare("SELECT foto_selfie FROM ujian_siswa WHERE id = ?");
        $stmtFoto->execute([$id_ujian]);
        $foto = $stmtFoto->fetchColumn();

        if ($foto && file_exists("../assets/" . $foto)) {
            unlink("../assets/" . $foto);
        }

        // 2. Hapus data ujian siswa (karena ada ON DELETE CASCADE di database, data di tabel jawaban_siswa juga otomatis terhapus)
        $stmtHapus = $pdo->prepare("DELETE FROM ujian_siswa WHERE id = ?");
        $stmtHapus->execute([$id_ujian]);

        // 3. Kembalikan status siswa menjadi 'belum'
        $stmtUpdateSiswa = $pdo->prepare("UPDATE siswa SET status_ujian = 'belum' WHERE id = ?");
        $stmtUpdateSiswa->execute([$id_siswa]);

        $pdo->commit();
        echo "<script>alert('Ujian berhasil direset! Siswa kini dapat login dan mengerjakan ujian kembali.'); window.location='nilai.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Terjadi kesalahan saat mereset ujian: " . $e->getMessage());
    }
} else {
    header("Location: nilai.php");
}
?>