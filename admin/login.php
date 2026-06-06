<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require 'cek_admin.php';

// Jika sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Tangkap pesan error jika ada
$error = '';
if (isset($_SESSION['error_login'])) {
    $error = $_SESSION['error_login'];
    unset($_SESSION['error_login']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    $password_valid = false;
    $rehash_password = false;

    if ($admin) {
        if (password_verify($password, $admin['password'])) {
            $password_valid = true;
            $rehash_password = password_needs_rehash($admin['password'], PASSWORD_DEFAULT);
        } elseif (hash_equals($admin['password'], md5($password))) {
            $password_valid = true;
            $rehash_password = true;
        }
    }

    if ($admin && $password_valid) {
        
        // 1. CEK APAKAH AKUN SEDANG DIPAKAI LOGIN DI TEMPAT LAIN
        // (Pastikan sudah menambah kolom is_login di tabel admin)
        if (isset($admin['is_login']) && $admin['is_login'] == 1) {
            $_SESSION['error_login'] = "⚠️ Akses ditolak: User sedang login di perangkat/browser lain!";
            header("Location: login.php");
            exit;
        }

        // 2. KUNCI AKUN (Tandai sedang login) dan upgrade hash lama jika perlu
        if ($rehash_password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE admin SET password = ?, is_login = 1 WHERE id = ?");
            $stmtUpdate->execute([$password_hash, $admin['id']]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE admin SET is_login = 1 WHERE id = ?");
            $stmtUpdate->execute([$admin['id']]);
        }

        // 3. REKAM KE LOG AKTIVITAS (CCTV Sistem)
        $aktivitas = "Login ke Portal Admin CBT";
        $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, ?, NOW())");
        $stmtLog->execute([$admin['id'], $aktivitas]);

        // Set Session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['nama_lengkap'] = $admin['nama_lengkap'];
        
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['error_login'] = "Username atau Password salah!";
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin Guru</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; flex-direction: column; }
        .login-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 300px; text-align: center; }
        input { width: 90%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 15px; margin-top: 10px; }
        button:hover { background: #0056b3; }
        .error { color: #dc3545; font-size: 14px; margin-bottom: 15px; background: #f8d7da; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; font-weight: bold; line-height: 1.4;}
    </style>
</head>
<body>
    <div class="login-box">
        <h3 style="color: #333; margin-top: 0;">Login Admin Guru</h3>
        <h2>SMK ISLAM BAHAGIA</h2>
        
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Masuk</button>
        </form>
        
        <br>
        <a href="../index.php" style="font-size: 13px; text-decoration: none; color: #6c757d; display: inline-block; margin-top: 10px;">&larr; Kembali ke Beranda Siswa</a>
    </div>

    <div style="margin-top: 20px;">
        <?php include "footer.php"; ?>
    </div>
</body>
</html>
