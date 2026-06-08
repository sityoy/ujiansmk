<?php
session_start();
require '../koneksi.php';

if (isset($_POST['ujian_id'], $_POST['jumlah'], $_SESSION['ujian_id']) && (int) $_POST['ujian_id'] === (int) $_SESSION['ujian_id']) {
    $ujian_id = (int) $_POST['ujian_id'];
    $jumlah = (int)$_POST['jumlah'];
    $batas_pelanggaran = 2;

    if ($jumlah >= $batas_pelanggaran) {
        $stmt = $pdo->prepare("UPDATE ujian_siswa SET jumlah_pelanggaran = ?, waktu_selesai = NOW() WHERE id = ?");
        $stmt->execute([$batas_pelanggaran, $ujian_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE ujian_siswa SET jumlah_pelanggaran = ? WHERE id = ?");
        $stmt->execute([$jumlah, $ujian_id]);
    }
}
?>
