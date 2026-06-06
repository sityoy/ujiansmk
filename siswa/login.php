<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

// Jika sudah login, langsung ke halaman selfie
if (isset($_SESSION['siswa_id'])) {
    header("Location: selfie.php");
    exit;
}

// --- TANGKAP ERROR DARI SESSION (Teknik PRG) ---
$error = '';
if (isset($_SESSION['error_login'])) {
    $error = $_SESSION['error_login'];
    unset($_SESSION['error_login']); // Hapus error setelah ditampilkan agar hilang saat di-refresh
}

// Ambil daftar jadwal untuk opsi dropdown
$stmtListJadwal = $pdo->query("SELECT * FROM pengaturan_ujian ORDER BY waktu_mulai ASC");
$semua_jadwal = $stmtListJadwal->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kartu = trim($_POST['kartu_peserta']);
    $password = trim($_POST['password']);
    $jadwal_id = $_POST['jadwal_id'];

    $stmtJadwal = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
    $stmtJadwal->execute([$jadwal_id]);
    $jadwal = $stmtJadwal->fetch();

    $waktu_sekarang = date('Y-m-d H:i:s');
    
    if (!$jadwal) {
        $_SESSION['error_login'] = "Pilih Mata Pelajaran terlebih dahulu!";
    } elseif ($waktu_sekarang < $jadwal['waktu_mulai']) {
        $_SESSION['error_login'] = "Ujian belum dimulai!";
    } elseif ($waktu_sekarang > $jadwal['waktu_selesai']) {
        $_SESSION['error_login'] = "Ujian sudah ditutup!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kartu_peserta = ?");
        $stmt->execute([$kartu]);
        $siswa = $stmt->fetch();
        
        // Pencocokan password menggunakan teks biasa
        if ($siswa && $password === $siswa['password']) {
            
            // --- LOGIKA CEK MAPEL ---
            // Cek apakah siswa ini sudah punya record 'selesai' untuk MAPEL INI
            $stmtCek = $pdo->prepare("SELECT id FROM ujian_siswa 
                                      WHERE siswa_id = ? 
                                      AND TRIM(mata_pelajaran) = TRIM(?) 
                                      AND waktu_selesai IS NOT NULL");
            $stmtCek->execute([$siswa['id'], trim($jadwal['mata_pelajaran'])]);
            
            if ($stmtCek->rowCount() > 0) {
                $_SESSION['error_login'] = "Anda sudah menyelesaikan ujian <strong>" . htmlspecialchars($jadwal['mata_pelajaran']) . "</strong>. Tidak bisa mengerjakan ulang.";
            } else {
                // Set Session untuk siswa
                $_SESSION['siswa_id'] = $siswa['id'];
                $_SESSION['nama_siswa'] = $siswa['nama_siswa'];
                $_SESSION['kartu_peserta'] = $siswa['kartu_peserta'];
                $_SESSION['kelas'] = $siswa['kelas'];
                $_SESSION['jadwal_id'] = $jadwal['id']; 
                $_SESSION['mapel_aktif'] = $jadwal['mata_pelajaran'];
                unset($_SESSION['urutan_soal']);
                
                header("Location: selfie.php");
                exit;
            }
        } else {
            $_SESSION['error_login'] = "Kartu Peserta atau Password salah!";
        }
    }

    // Redirect kembali ke halaman ini (login.php) agar POST data terhapus dari memori browser
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Portal Ujian CBT - Siswa</title>
    <style>
        :root {
            --primary: #10b981; /* Emerald Green */
            --primary-hover: #059669;
            --bg-gradient: linear-gradient(135deg, #dcfce7 0%, #f1f5f9 100%);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #cbd5e1;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-gradient); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            flex-direction: column; 
        }

        .login-wrapper {
            width: 100%;
            max-width: 380px;
            padding: 20px;
            box-sizing: border-box;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card { 
            background: white; 
            padding: 35px 30px; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.15); 
            text-align: center; 
            border-top: 6px solid var(--primary); 
            position: relative;
        }

        .icon-student {
            background: #d1fae5;
            color: var(--primary);
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .login-card h2 { margin: 0; color: var(--text-main); font-size: 22px; font-weight: 800; letter-spacing: 0.5px;}
        .login-card h3 { margin: 5px 0 15px; color: var(--primary); font-size: 16px; font-weight: 700; text-transform: uppercase;}
        .login-card p { font-size: 14px; color: var(--text-muted); margin-bottom: 25px; line-height: 1.5; }
        
        .form-group {
            margin-bottom: 18px;
            position: relative;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-control { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 14.5px;
            transition: all 0.3s;
            background: #f8fafc;
            color: #334155;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            background: white;
        }
        
        .btn-submit { 
            width: 100%; 
            padding: 14px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 16px; 
            margin-top: 10px;
            transition: all 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover { 
            background: var(--primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.25);
        }

        .error-box { 
            color: #dc2626; 
            font-size: 13.5px; 
            margin-bottom: 20px; 
            font-weight: 600; 
            background: #fef2f2; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid #fecaca;
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
        
        /* === STYLE PASSWORD MATA === */
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { padding-right: 45px; } 
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            height: 100%;
            padding: 0 5px;
        }
        .toggle-password:hover { color: var(--primary); }
        .toggle-password svg { width: 20px; height: 20px; }

        .footer-container {
            margin-top: 20px;
            text-align: center;
            width: 100%;
        }
    </style>
</head>
<body>
    
    <div class="login-wrapper">
        <div class="login-card">
            <div class="icon-student">🎓</div>
            <h2>SMK ISLAM BAHAGIA</h2>
            <h3>Portal Ujian CBT</h3>
            <p>Silakan pilih jadwal mata pelajaran dan masukkan kredensial Anda untuk memulai ujian.</p>
            
            <?php if($error): ?>
                <div class="error-box">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Pilih Jadwal Ujian</label>
                    <select name="jadwal_id" class="form-control" required>
                        <option value="" disabled selected>-- Pilih Mata Pelajaran --</option>
                        <?php 
                        $bulanIndo = [
                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                        ];
                    
                        foreach($semua_jadwal as $j): 
                            $waktu_mulai = strtotime($j['waktu_mulai']);
                            $tgl = date('d', $waktu_mulai);
                            $bln = $bulanIndo[date('m', $waktu_mulai)];
                            $thn = date('Y', $waktu_mulai);
                            $jam_mulai = date('H:i', $waktu_mulai);
                            $jam_selesai = date('H:i', strtotime($j['waktu_selesai']));
                            
                            $waktu_tampil = "$tgl $bln $thn ($jam_mulai - $jam_selesai)";
                        ?>
                            <option value="<?php echo $j['id']; ?>">
                                <?php echo htmlspecialchars($j['mata_pelajaran']) . " | " . $waktu_tampil; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nomor Kartu Peserta</label>
                    <input type="text" name="kartu_peserta" class="form-control" placeholder="Cth: 12345678" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Ketik password..." required>
                        <span class="toggle-password" onclick="togglePassword()" id="eye-icon" title="Tampilkan/Sembunyikan Password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    Masuk Ujian 📝
                </button>
            </form>
        </div>
        
        <div class="footer-container">
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        // Mencegah browser menampilkan notifikasi "Confirm Form Resubmission"
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // FUNGSI UNTUK MENGUBAH TIPE PASSWORD & IKON MATA
        function togglePassword() {
            const pwdInput = document.getElementById('password');
            const iconWrapper = document.getElementById('eye-icon');
            
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                iconWrapper.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                pwdInput.type = 'password';
                iconWrapper.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    </script>
</body>
</html>