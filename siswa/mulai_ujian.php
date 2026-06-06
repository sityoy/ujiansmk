<?php
error_reporting(0); // Sembunyikan error server agar JSON tidak rusak
session_start();
require '../koneksi.php';

// Sekarang kita menangkap $_FILES, bukan lagi $_POST['image']
if (!isset($_SESSION['siswa_id']) || !isset($_FILES['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Foto tidak diterima oleh server hosting.']);
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
$folderPath = dirname(__DIR__) . "/assets/";

if (!file_exists($folderPath)) {
    @mkdir($folderPath, 0755, true); 
}

$fileName = "selfie_" . $siswa_id . "_" . time() . ".jpg";
$file = $folderPath . $fileName;

// Gunakan move_uploaded_file standar (Anti-blokir ModSecurity)
if (move_uploaded_file($_FILES['image']['tmp_name'], $file)) {
    try {
        $pdo->beginTransaction();
        $mapel_aktif = $_SESSION['mapel_aktif'];

        $stmtUjian = $pdo->prepare("
            INSERT INTO ujian_siswa
            (siswa_id, mata_pelajaran, foto_selfie, waktu_mulai, jumlah_pelanggaran)
            VALUES (?, ?, ?, NOW(), 0)
        ");

        $stmtUjian->execute([
            $siswa_id,
            $mapel_aktif,
            $fileName
        ]);

        $ujian_id = $pdo->lastInsertId();
        $_SESSION['ujian_id'] = $ujian_id;

        $pdo->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $pdo->rollBack();
        @unlink($file);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Folder assets terkunci. Mohon ubah permission folder assets menjadi 0755.']);
}
?>