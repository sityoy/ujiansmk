<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Menggunakan koneksi langsung agar tidak terjadi infinite loop (berputar-putar) dengan cek_admin.php
require '../koneksi.php'; 

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

// Proses jika tombol login ditekan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    $password_valid = false;
    $rehash_password = false;

    // Verifikasi Password (Support hash modern & MD5 lama)
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
        
        // 1. CEK APAKAH SEDANG LOGIN DI TEMPAT LAIN
        if ($admin['is_login'] == 1) {
            $error = "Akun ini sedang digunakan di perangkat lain! Harap logout terlebih dahulu.";
        } else {
            // 2. REHASH PASSWORD JIKA MASIH MD5
            if ($rehash_password) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdateHash = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmtUpdateHash->execute([$new_hash, $admin['id']]);
            }

            // 3. KUNCI AKUN (SET is_login = 1)
            $stmtUpdateLogin = $pdo->prepare("UPDATE admin SET is_login = 1 WHERE id = ?");
            $stmtUpdateLogin->execute([$admin['id']]);

            // 4. BUAT SESSION
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['last_activity'] = time();

            // 5. CATAT LOG AKTIVITAS
            $stmtLog = $pdo->prepare("INSERT INTO log_aktivitas (admin_id, aktivitas, created_at) VALUES (?, 'Login ke sistem CBT', NOW())");
            $stmtLog->execute([$admin['id']]);

            header("Location: index.php");
            exit;
        }
    } else {
        $error = "Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - CBT Panel</title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a0ca3;
            --bg: #f8fafc;
            --text: #2b2d42;
            --border: #e2e8f0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f4f7f6 0%, #e0e7ff 100%);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 360px;
            border-top: 6px solid var(--primary);
            position: relative;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon-admin {
            background: #e0e7ff;
            color: var(--primary);
            width: 65px;
            height: 65px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
        }

        .login-header h2 {
            margin: 0;
            color: var(--text);
            font-size: 24px;
            font-weight: 800;
        }

        .login-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s;
            background: #f8fafc;
            color: #333;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background: white;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }

        .error-box {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            font-size: 13.5px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 36px;
            cursor: pointer;
            color: #94a3b8;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            width: 24px;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <div class="icon-admin">🛡️</div>
            <h2>CBT Admin Panel</h2>
            <p>Masukkan kredensial Anda untuk masuk</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-box">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username Admin</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Ketik username..." required autocomplete="off" autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Ketik password..." required>
                <div class="toggle-password" onclick="togglePassword()" title="Tampilkan/Sembunyikan Password">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                Masuk Sistem 🚀
                
            </button>
            <button type="submit" class="btn-login">
             <a href="../index.php">Kembali </a>
             </button>
        </form>
       

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> SMK Islam Bahagia
        </div>
    </div>

    <script>
        // Fungsi untuk Show/Hide Password
        function togglePassword() {
            const pwdInput = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                // Ubah SVG menjadi ikon mata disilang (Eye-off)
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                pwdInput.type = 'password';
                // Kembalikan SVG menjadi ikon mata normal (Eye)
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
    </script>
</body>
</html>