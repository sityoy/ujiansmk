<?php
session_start();
require 'cek_admin.php';

if (!isset($_POST['ujian_id'])) {
    echo json_encode(['status' => 'error']);
    exit;
}

$ujian_id = $_POST['ujian_id'];

// 1. Update waktu terakhir siswa aktif (Detak Jantung / Ping)
$stmt = $pdo->prepare("UPDATE ujian_siswa SET last_ping = NOW() WHERE id = ?");
$stmt->execute([$ujian_id]);

// 2. Cek apakah admin atau sistem sudah mematikan sesi ini (pelanggaran >= 5)
$stmtCek = $pdo->prepare("SELECT jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmtCek->execute([$ujian_id]);
$data = $stmtCek->fetch();

if ($data && $data['jumlah_pelanggaran'] >= 5) {
    // Sinyal ke JavaScript untuk mematikan halaman ujian
    echo json_encode(['status' => 'stop']);
} else {
    echo json_encode(['status' => 'ok']);
}
?>