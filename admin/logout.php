<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

// Pastikan ada session admin yang aktif sebelum memproses logout
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];

    // 1. REKAM KE LOG AKTIVITAS (Biar ketahuan jam berapa dia keluar)
    $aktivitas = "Logout dari Portal Admin CBT";
    $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, ?, NOW())");
    $stmtLog->execute([$admin_id, $aktivitas]);

    // 2. BUKA KUNCI LOGIN (Ini yang bikin Bapak gak perlu reset manual lagi!)
    $stmtUpdate = $pdo->prepare("UPDATE admin SET is_login = 0 WHERE id = ?");
    $stmtUpdate->execute([$admin_id]);
}

// 3. Hapus semua data memori session
session_unset();
session_destroy();

// 4. Arahkan kembali ke halaman login
header("Location: login.php");
exit;
?>