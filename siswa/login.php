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
    <title>Login Ujian CBT</title>
    <style>
        body { font-family: Arial, sans-serif; background: #e9f5ee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; flex-direction: column; }
        .login-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 320px; text-align: center; border-top: 5px solid #28a745; margin-bottom: 20px;}
        input, select { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        button { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 15px; margin-top: 5px;}
        button:hover { background: #1e7e34; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 15px; font-weight: bold; background: #f8d7da; padding: 10px; border-radius: 5px; border: 1px solid #f5c6cb;}
        
        /* === STYLE TAMBAHAN UNTUK PASSWORD MATA === */
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { padding-right: 45px; } /* Beri ruang agar teks tidak tertimpa ikon mata */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .toggle-password:hover { color: #28a745; }
        .toggle-password svg { width: 20px; height: 20px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h3 style="margin-top: 0; margin-bottom: 5px; color: #333;">Portal Ujian CBT</h3>
        <h2>SMK ISLAM BAHAGIA</h2>
        <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Silakan pilih jadwal dan login.</p>
        
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
           <select name="jadwal_id" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                <?php 
                // 1. Buat kamus bulan Indonesia
                $bulanIndo = [
                    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                ];
            
                foreach($semua_jadwal as $j): 
                    // 2. Pecah waktu_mulai menjadi Tanggal, Bulan, Tahun, dan Jam
                    $waktu_mulai = strtotime($j['waktu_mulai']);
                    $tgl = date('d', $waktu_mulai);
                    $bln = $bulanIndo[date('m', $waktu_mulai)]; // Terjemahkan angka bulan ke teks Indonesia
                    $thn = date('Y', $waktu_mulai);
                    $jam_mulai = date('H:i', $waktu_mulai);
                    $pukul = 'Pukul';
                    
                    $jam_selesai = date('H:i', strtotime($j['waktu_selesai']));
                    
                    // 3. Gabungkan menjadi format yang cantik
                    $waktu_tampil = "$tgl $bln $thn $pukul $jam_mulai";
                ?>
                    <option value="<?php echo $j['id']; ?>">
                        <?php echo htmlspecialchars($j['nama_ujian']) . " | " . htmlspecialchars($j['mata_pelajaran']) . " (" . $waktu_tampil . " - " . $jam_selesai . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" name="kartu_peserta" placeholder="No. Kartu Peserta" required autocomplete="off">
            
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <span class="toggle-password" onclick="togglePassword()" id="eye-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </span>
            </div>
            
            <button type="submit">Masuk Ujian</button>
        </form>
    </div>

    <?php include 'footer.php'; ?>

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
                // Ubah ke teks (Bisa dilihat) & Ganti jadi ikon mata disilang
                pwdInput.type = 'text';
                iconWrapper.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                // Kembalikan ke password (Titik-titik) & Ganti jadi ikon mata terbuka
                pwdInput.type = 'password';
                iconWrapper.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    </script>
</body>
</html>