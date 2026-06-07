<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');
require '../koneksi.php';

if (!isset($_SESSION['siswa_id'], $_SESSION['mapel_aktif']) || !isset($_FILES['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap atau sesi habis.']);
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
// Path absolut ke folder assets (Pastikan folder 'assets' ada di direktori yang sama dengan koneksi.php)
$folderPath = __DIR__ . "/../assets/"; 

if (!file_exists($folderPath)) {
    @mkdir($folderPath, 0755, true); 
}

$fileName = "selfie_" . $siswa_id . "_" . time() . ".jpg";
$file = $folderPath . $fileName;

if (move_uploaded_file($_FILES['image']['tmp_name'], $file)) {
    try {
        $pdo->beginTransaction();
        $mapel_aktif = $_SESSION['mapel_aktif'];

        // PERBAIKAN: Gunakan nama variabel yang sama ($stmt)
        $stmt = $pdo->prepare("INSERT INTO ujian_siswa (siswa_id, mata_pelajaran, foto_selfie, waktu_mulai, last_ping) 
                               VALUES (?, ?, ?, NOW(), NOW())");

        $stmt->execute([
            $siswa_id,
            $mapel_aktif,
            $fileName
        ]);

        $ujian_id = $pdo->lastInsertId();
        $_SESSION['ujian_id'] = $ujian_id;

        $pdo->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($file); // Hapus foto jika gagal database
        error_log('Gagal memulai ujian siswa ' . $siswa_id . ': ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal memulai ujian. Silakan hubungi administrator.']);
    }
} else {
    // Pesan error lebih informatif
    echo json_encode(['status' => 'error', 'message' => 'Gagal simpan file. Cek permission folder assets.']);
}
?>
