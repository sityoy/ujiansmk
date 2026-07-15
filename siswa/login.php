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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kartu = trim($_POST['kartu_peserta']);
    $password = trim($_POST['password']);
    $jadwal_id = $_POST['jadwal_id'] ?? '';

    if (empty($jadwal_id)) {
        $_SESSION['error_login'] = "Pilih Jadwal Ujian terlebih dahulu!";
        header("Location: login.php");
        exit;
    }

    // 1. Ambil data siswa terlebih dahulu untuk verifikasi & cek kelas
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kartu_peserta = ?");
    $stmt->execute([$kartu]);
    $siswa = $stmt->fetch();
    
    // Pencocokan password menggunakan teks biasa
    if ($siswa && $password === $siswa['password']) {
        
        // Bersihkan data kelas siswa agar formatnya sama (X, XI, XII)
        $kelas_siswa = strtoupper(trim($siswa['kelas']));
        if (strpos($kelas_siswa, 'XII') === 0) { $kelas_siswa = 'XII'; } 
        elseif (strpos($kelas_siswa, 'XI') === 0) { $kelas_siswa = 'XI'; } 
        else { $kelas_siswa = 'X'; }

        // 2. Ambil data jadwal yang dipilih dan pastikan KELAS-nya COCOK
        $stmtJadwal = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ? AND kelas = ?");
        $stmtJadwal->execute([$jadwal_id, $kelas_siswa]);
        $jadwal = $stmtJadwal->fetch();

        $waktu_sekarang = date('Y-m-d H:i:s');
        
        if (!$jadwal) {
            $_SESSION['error_login'] = "Mata pelajaran ini bukan untuk tingkatan kelas Anda!";
        } elseif ($waktu_sekarang < $jadwal['waktu_mulai']) {
            $_SESSION['error_login'] = "Ujian belum dimulai!";
        } elseif ($waktu_sekarang > $jadwal['waktu_selesai']) {
            $_SESSION['error_login'] = "Ujian sudah ditutup!";
        } else {
            
            // --- PERBAIKAN LOGIKA: CEK APAKAH SUDAH MENGERJAKAN JADWAL SPESIFIK INI ---
            $stmtCek = $pdo->prepare("SELECT id FROM ujian_siswa 
                                      WHERE siswa_id = ? 
                                      AND TRIM(mata_pelajaran) = TRIM(?) 
                                      AND waktu_selesai IS NOT NULL
                                      AND DATE(waktu_selesai) BETWEEN DATE(?) AND DATE(?)");
            $stmtCek->execute([
                $siswa['id'], 
                trim($jadwal['mata_pelajaran']),
                $jadwal['waktu_mulai'],
                $jadwal['waktu_selesai']
            ]);
            
            if ($stmtCek->rowCount() > 0) {
                $_SESSION['error_login'] = "Anda sudah menyelesaikan ujian <strong>" . htmlspecialchars($jadwal['mata_pelajaran']) . "</strong> untuk jadwal ini. Tidak bisa mengerjakan ulang.";
            } else {
                // Set Session untuk siswa jika lolos verifikasi
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
        }
    } else {
        $_SESSION['error_login'] = "Kartu Peserta atau Password salah!";
    }

    header("Location: login.php");
    exit;
}

// Ambil semua daftar jadwal untuk dilempar ke JavaScript
$stmtListJadwal = $pdo->query("SELECT * FROM pengaturan_ujian ORDER BY kelas ASC, waktu_mulai ASC");
$semua_jadwal = $stmtListJadwal->fetchAll(PDO::FETCH_ASSOC);

// Ambil nama ujian secara unik (DISTINCT)
$stmtNamaUjian = $pdo->query("SELECT DISTINCT nama_ujian FROM pengaturan_ujian WHERE nama_ujian IS NOT NULL AND nama_ujian != '' ORDER BY nama_ujian ASC");
$list_nama_ujian = $stmtNamaUjian->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Portal Ujian CBT - Siswa</title>
    <style>
        :root {
            --primary: #10b981;
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
            max-width: 440px; 
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

        select optgroup {
            font-weight: bold;
            color: #3730a3;
            background: #f1f5f9;
        }
        select option {
            color: #334155;
            background: #fff;
            padding: 6px;
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
                    <label>Pilih Nama Ujian</label>
                    <select name="nama_ujian" id="pilih_nama_ujian" class="form-control" required onchange="filterJadwal()">
                        <option value="" disabled selected>-- Pilih Nama Ujian --</option>
                        <?php foreach($list_nama_ujian as $nu): ?>
                            <option value="<?php echo htmlspecialchars($nu); ?>">
                                <?php echo htmlspecialchars($nu); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Pilih Jadwal Ujian</label>
                    <select name="jadwal_id" id="pilih_jadwal_ujian" class="form-control" required>
                        <option value="" disabled selected>-- Pilih Jadwal --</option>
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
        const semuaJadwal = <?php echo json_encode($semua_jadwal); ?>;
        
        const bulanIndo = {
            '01': 'Jan', '02': 'Feb', '03': 'Mar',
            '04': 'Apr', '05': 'Mei', '06': 'Jun',
            '07': 'Jul', '08': 'Ags', '09': 'Sep',
            '10': 'Okt', '11': 'Nov', '12': 'Des'
        };

        function padZero(num) {
            return num < 10 ? '0' + num : num;
        }

        function formatTanggal(tanggalString) {
            if (!tanggalString) return "-";
            const dateObj = new Date(tanggalString);
            if (isNaN(dateObj.getTime())) return tanggalString;

            const tgl = padZero(dateObj.getDate());
            const bulanStr = padZero(dateObj.getMonth() + 1); 
            const bln = bulanIndo[bulanStr] || bulanStr;
            const jam = padZero(dateObj.getHours());
            const menit = padZero(dateObj.getMinutes());

            return `${tgl} ${bln} ${jam}:${menit}`;
        }

        function filterJadwal() {
            const selectNamaUjian = document.getElementById('pilih_nama_ujian');
            const namaUjianDipilih = selectNamaUjian.value;
            const jadwalDropdown = document.getElementById('pilih_jadwal_ujian');
            
            // 1. Reset dropdown
            jadwalDropdown.innerHTML = '<option value="" disabled selected>-- Pilih Jadwal --</option>';

            if (!namaUjianDipilih) return;

            // 2. Filter jadwal
            const jadwalFiltered = semuaJadwal.filter(j => {
                const namaDB = j.nama_ujian ? j.nama_ujian.toString().trim() : '';
                return namaDB === namaUjianDipilih.trim();
            });

            // 3. Kelompokkan berdasarkan kelas
            const grouped = { 'X': [], 'XI': [], 'XII': [] };
            jadwalFiltered.forEach(j => {
                let k = (j.kelas || 'X').trim().toUpperCase();
                if (k.startsWith('XII')) { grouped['XII'].push(j); }
                else if (k.startsWith('XI')) { grouped['XI'].push(j); }
                else { grouped['X'].push(j); }
            });

            // 4. Masukkan ke dropdown
            const labels = { 'X': 'Tingkat Kelas X', 'XI': 'Tingkat Kelas XI', 'XII': 'Tingkat Kelas XII' };
            
            for (let key in labels) {
                if (grouped[key].length > 0) {
                    let optgroup = document.createElement('optgroup');
                    optgroup.label = "📂 " + labels[key];
                    
                    grouped[key].forEach(j => {
                        let mulai = formatTanggal(j.waktu_mulai);
                        let selesai = formatTanggal(j.waktu_selesai);
                        let mapel = j.mata_pelajaran || 'Mapel Tidak Diketahui';

                        let textTampil = `${mapel} | Mulai: ${mulai} - Selesai: ${selesai}`;

                        let option = document.createElement('option');
                        option.value = j.id;
                        option.textContent = textTampil;
                        
                        optgroup.appendChild(option);
                    });
                    
                    jadwalDropdown.appendChild(optgroup);
                }
            }
        }
        
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        function togglePassword() {
            const pwdInput = document.getElementById('password');
            const iconWrapper = document.getElementById('eye-icon');
            
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                iconWrapper.innerHTML = '<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24\"></path><line x1=\"1\" y1=\"1\" x2=\"23\" y2=\"23\"></line></svg>';
            } else {
                pwdInput.type = 'password';
                iconWrapper.innerHTML = '<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\"></path><circle cx=\"12\" cy=\"12\" r=\"3\"></circle></svg>';
            }
        }
    </script>
</body>
</html>