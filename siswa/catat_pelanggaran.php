<?php
session_start();
require '../koneksi.php';

if (isset($_POST['ujian_id']) && isset($_POST['jumlah'])) {
    $ujian_id = $_POST['ujian_id'];
    $jumlah = (int)$_POST['jumlah'];

    // Jika jumlah di atas 5, paksa jadi 5 dan set waktu_selesai
    if ($jumlah >= 5) {
        $stmt = $pdo->prepare("UPDATE ujian_siswa SET jumlah_pelanggaran = 5, waktu_selesai = NOW() WHERE id = ?");
        $stmt->execute([$ujian_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE ujian_siswa SET jumlah_pelanggaran = ? WHERE id = ?");
        $stmt->execute([$jumlah, $ujian_id]);
    }
}
?>