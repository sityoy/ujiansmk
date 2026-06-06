<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

// 1. Jika tidak ada session, tendang ke login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$timeout_duration = 3600; // 3600 detik = 1 jam

// 2. SISTEM AUTO-LOGOUT JIKA TIDAK ADA AKTIVITAS
if (isset($_SESSION['last_activity'])) {
    $selisih_waktu = time() - $_SESSION['last_activity'];

    if ($selisih_waktu > $timeout_duration) {
        // Rekam ke CCTV
        $aktivitas = "Auto-Logout (Sesi habis karena tidak ada aktivitas 1 jam)";
        $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, ?, NOW())");
        $stmtLog->execute([$admin_id, $aktivitas]);

        // Buka Kunci Login
        $stmtUpdate = $pdo->prepare("UPDATE admin SET is_login = 0 WHERE id = ?");
        $stmtUpdate->execute([$admin_id]);

        // Bersihkan memori dan tendang ke login
        session_unset();
        session_destroy();
        
        session_start();
        $_SESSION['error_login'] = "⚠️ Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 Jam.";
        header("Location: login.php");
        exit;
    }
}

// 3. PERBARUI WAKTU AKTIVITAS DI SESSION & DATABASE
$_SESSION['last_activity'] = time();

$stmtUpdateActive = $pdo->prepare("UPDATE admin SET last_active = NOW() WHERE id = ?");
$stmtUpdateActive->execute([$admin_id]);
?>