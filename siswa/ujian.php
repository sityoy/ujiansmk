<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

if (!isset($_SESSION['siswa_id'], $_SESSION['ujian_id'], $_SESSION['jadwal_id'], $_SESSION['mapel_aktif'], $_SESSION['kelas'])) {
    header("Location: login.php");
    exit;
}

$ujian_id = $_SESSION['ujian_id'];
$stmtCek = $pdo->prepare("SELECT jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmtCek->execute([$ujian_id]);
$dataUjian = $stmtCek->fetch();

if (!$dataUjian) {
    unset($_SESSION['ujian_id']);
    header("Location: selfie.php");
    exit;
}

// Jika sudah melebihi atau sama dengan 2 pelanggaran, langsung tendang ke halaman selesai
if ($dataUjian['jumlah_pelanggaran'] >= 2) {
    header("Location: selesai_ujian.php");
    exit;
}

$jadwal_id = $_SESSION['jadwal_id'];
$stmtJadwal = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
$stmtJadwal->execute([$jadwal_id]);
$jadwal = $stmtJadwal->fetch();

if (!$jadwal) {
    unset($_SESSION['jadwal_id'], $_SESSION['mapel_aktif'], $_SESSION['urutan_soal']);
    $_SESSION['error_login'] = "Jadwal ujian tidak ditemukan. Silakan pilih jadwal ulang.";
    header("Location: login.php");
    exit;
}

$sisa_detik = strtotime($jadwal['waktu_selesai']) - time();
$mapel_aktif = $_SESSION['mapel_aktif'];

if ($sisa_detik <= 0) {
    header("Location: selesai_ujian.php");
    exit;
}

// LOGIKA KELAS
$kelas = strtoupper(trim($_SESSION['kelas']));
if (strpos($kelas, 'XII') === 0) { $kelas = 'XII'; } 
elseif (strpos($kelas, 'XI') === 0) { $kelas = 'XI'; } 
else { $kelas = 'X'; }

// =============================================================
// MODIFIKASI LOGIKA PENGACAKAN NOMOR SOAL BERDASARKAN MAPEL
// =============================================================
if (!isset($_SESSION['urutan_soal'])) {
    // Kita tambahkan ORDER BY id ASC agar defaultnya urut dari database
    $stmtSoal = $pdo->prepare("SELECT * FROM soal WHERE mata_pelajaran = ? AND kelas = ? ORDER BY id ASC");
    $stmtSoal->execute([$mapel_aktif, $kelas]);
    $daftar_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalisasi teks mapel ke huruf besar tanpa spasi berlebih untuk pengecekan
    $mapel_cek = strtoupper(trim($mapel_aktif));
    
    // Jika BUKAN Bahasa Inggris dan BUKAN Bahasa Indonesia, baru nomor soalnya diacak (shuffle)
    if ($mapel_cek !== 'BAHASA INGGRIS' && $mapel_cek !== 'BAHASA INDONESIA') {
        shuffle($daftar_soal);
    }
    
    $_SESSION['urutan_soal'] = $daftar_soal;
} else {
    $daftar_soal = $_SESSION['urutan_soal'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lembar Ujian - <?php echo htmlspecialchars($mapel_aktif); ?></title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a0ca3;
            --success: #10b981;
            --success-hover: #059669;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --warning: #f59e0b;
            --bg-body: #f1f5f9;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-body); 
            margin: 0; 
            padding: 0; 
            color: var(--text-main);
            user-select: none; -moz-user-select: none; -webkit-user-select: none; -ms-user-select: none;
            -webkit-touch-callout: none; overscroll-behavior-y: none; 
        }
        
        /* OVERLAY AWAL & PERINGATAN */
        #fullscreen-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(15, 23, 42, 0.98); color: white; display: flex; 
            flex-direction: column; justify-content: center; align-items: center; 
            z-index: 9999; text-align: center; padding: 20px; box-sizing: border-box; 
            backdrop-filter: blur(8px);
        }
        .peringatan-keras { 
            background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 25px; 
            border-radius: 12px; margin: 25px 0; border: 1px solid rgba(239, 68, 68, 0.3); 
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.15); max-width: 600px; text-align: left; 
        }
        #btn-fullscreen { 
            background: var(--success); color: white; padding: 16px 35px; font-size: 18px; 
            border: none; border-radius: 8px; cursor: pointer; font-weight: 700; 
            margin-top: 15px; transition: 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); 
            letter-spacing: 0.5px;
        }
        #btn-fullscreen:hover { background: var(--success-hover); transform: translateY(-2px); }
        
        #layar-hitam { 
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
            background-color: #7f1d1d; z-index: 100000; color: white; flex-direction: column; 
            justify-content: center; align-items: center; text-align: center; padding: 20px;
        }
        
        /* TOAST & MODAL CONFIRM */
        #custom-alert { 
            display: none; position: fixed; top: 90px; left: 50%; transform: translateX(-50%); 
            background: #1e293b; color: white; padding: 14px 24px; border-radius: 30px; 
            z-index: 100001; box-shadow: 0 10px 25px rgba(0,0,0,0.2); font-weight: 600; 
            text-align: center; max-width: 90%; font-size: 14px; border-left: 4px solid var(--danger);
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown { from { top: 50px; opacity: 0; } to { top: 90px; opacity: 1; } }
        
        #custom-confirm { 
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
            background: rgba(15, 23, 42, 0.85); z-index: 100002; flex-direction: column; 
            justify-content: center; align-items: center; backdrop-filter: blur(5px);
        }
        .confirm-box { 
            background: white; padding: 35px 30px; border-radius: 16px; text-align: center; 
            max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); 
            animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .btn-ya { background: var(--success); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 15px; cursor: pointer; margin-right: 10px; font-weight: 700; transition: 0.2s; }
        .btn-ya:hover { background: var(--success-hover); }
        .btn-batal { background: var(--text-muted); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 15px; cursor: pointer; font-weight: 700; transition: 0.2s; }
        .btn-batal:hover { background: #475569; }

        /* HEADER NAVIGASI */
        #konten-ujian { display: none; } 
        .header-info { 
            background: white; padding: 15px 30px; position: fixed; top: 0; left: 0; right: 0; 
            display: flex; justify-content: space-between; align-items: center; z-index: 1000; 
            box-shadow: 0 2px 15px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border);
        }
        .user-profile { display: flex; flex-direction: column; gap: 4px; }
        .user-name { font-weight: 800; font-size: 15px; color: var(--text-main); }
        .user-id { font-size: 13px; color: var(--text-muted); font-weight: 600; }
        
        .timer-badge { 
            background: #eff6ff; color: var(--primary); padding: 8px 18px; 
            border-radius: 30px; font-weight: 800; font-size: 18px; 
            border: 1px solid #bfdbfe; font-variant-numeric: tabular-nums;
        }
        .stat-badge { 
            display: flex; flex-direction: column; align-items: flex-end; gap: 4px;
        }
        .mapel-badge { background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .pelanggaran-text { font-size: 13px; color: var(--danger); font-weight: 700; background: #fee2e2; padding: 4px 10px; border-radius: 6px; }

        /* KONTEN SOAL */
        .container { 
            margin-top: 100px; max-width: 850px; margin-left: auto; margin-right: auto; 
            padding: 0 20px 40px 20px; 
        }
        .soal-box { 
            background: white; border-radius: 16px; padding: 30px; margin-bottom: 25px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
            border: 1px solid var(--border);
        }
        .nomor-soal {
            display: inline-block; background: var(--primary); color: white; 
            padding: 6px 14px; border-radius: 8px; font-weight: 800; font-size: 14px; 
            margin-bottom: 20px; letter-spacing: 0.5px;
        }
        .soal-teks { font-size: 16px; line-height: 1.7; color: var(--text-main); margin-bottom: 25px; }
        
        /* OPSI JAWABAN (RADIO CUSTOM) */
        .opsi { 
            display: flex; align-items: flex-start; padding: 16px 20px; 
            border: 2px solid var(--border); border-radius: 12px; margin-bottom: 12px; 
            cursor: pointer; transition: all 0.2s ease; background: white; 
        }
        .opsi:hover { border-color: #cbd5e1; background: #f8fafc; transform: translateX(4px); }
        
        .opsi input[type="radio"]:checked + .opsi-teks {
            border-color: var(--primary);
            background: #eff6ff;
        }

        .opsi-selected { border-color: var(--primary) !important; background: #eff6ff !important; }

        .opsi input[type="radio"] { 
            margin-top: 4px; margin-right: 15px; transform: scale(1.3); cursor: pointer; 
            accent-color: var(--primary);
        }
        .opsi-teks { font-size: 15.5px; line-height: 1.5; font-weight: 500; color: #334155; }
        
        .btn-submit { 
            background: var(--primary); color: white; border: none; padding: 18px; 
            width: 100%; font-size: 18px; font-weight: 800; border-radius: 12px; 
            cursor: pointer; margin-top: 10px; transition: 0.3s; box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3); 
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); }
        
        /* MODAL ZOOM GAMBAR */
        .modal-zoom { display: none; position: fixed; z-index: 200000; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.9); justify-content: center; align-items: center; cursor: zoom-out; backdrop-filter: blur(5px);}
        .modal-zoom img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .img-clickable { border-radius: 8px; border: 1px solid var(--border); transition: 0.2s; }
        .img-clickable:hover { border-color: var(--primary); transform: scale(1.02); }

        @media (max-width: 768px) {
            .header-info { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
            .timer-badge { order: 3; width: 100%; text-align: center; margin-top: 5px; font-size: 20px;}
            .container { margin-top: 130px; padding: 0 15px 30px 15px; }
            .soal-box { padding: 20px; }
        }
    </style>
</head>
<body oncontextmenu="return false;" oncopy="return false;" onpaste="return false;" onkeydown="return mencegahAksi(event);">

    <div id="previewModal" class="modal-zoom" onclick="tutupPreview()">
        <img id="imgPreview" src="" alt="Pratinjau Gambar">
    </div>

    <div id="layar-hitam">
        <h2 style="font-size: 34px; margin-bottom: 10px;">❌ PELANGGARAN FATAL! ❌</h2>
        <p style="font-size: 18px; color: #fca5a5; line-height: 1.6;">Sistem mendeteksi Anda mencoba berbuat curang.<br>Sesi Ujian Anda DIBEKUKAN secara permanen!</p>
    </div>

    <div id="custom-alert"><span id="custom-alert-text">Pesan</span></div>
    
    <div id="custom-confirm">
        <div class="confirm-box">
            <h2 style="color: var(--text-main); margin-top: 0; font-size: 22px;">Konfirmasi Selesai</h2>
            <p style="color: var(--text-muted); margin-bottom: 25px; line-height: 1.5;">Apakah Anda yakin ingin mengakhiri ujian dan mengirimkan semua jawaban sekarang?</p>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button class="btn-ya" onclick="submitFormFinal()">Ya, Kirim</button>
                <button class="btn-batal" onclick="tutupConfirm()">Batal</button>
            </div>
        </div>
    </div>
    
    <div id="fullscreen-overlay">
        <h2 style="color: #60a5fa; font-size: 28px; margin-bottom: 5px;">Persiapan Ujian Berbasis Komputer</h2>
        <p style="color: #cbd5e1; font-size: 18px; margin-top: 0;">Mata Pelajaran: <strong><?php echo htmlspecialchars($mapel_aktif); ?></strong></p>
        
        <div class="peringatan-keras">
            <h3 style="margin-top:0; font-size: 16px; color: #ef4444; border-bottom: 1px solid rgba(239, 68, 68, 0.3); padding-bottom: 10px;">⚠️ ATURAN SANGAT KETAT (TANPA AMPUN) ⚠️</h3>
            <ul style="margin-bottom:0; padding-left: 20px; line-height: 1.8; font-size: 14.5px;">
                <li><b>WAJIB:</b> Tutup/Keluarkan semua aplikasi lain sebelum memulai ujian ini.</li>
                <li>Dilarang keras menarik <b>Bilah Notifikasi (Notif WA, dll)</b> dari atas layar.</li>
                <li>Dilarang keras menyentuh <b>Bilah Navigasi</b> dari bawah layar.</li>
                <li>Dilarang membuka tab baru, keluar dari layar penuh, atau split screen.</li>
                <li>Sistem mengawasi pergerakan kursor dan sentuhan layar secara *Real-Time*.</li>
            </ul>
        </div>
        <p style="color: #94a3b8; font-size: 15px; margin-bottom: 25px;">2 kali pelanggaran = <b>NILAI LANGSUNG DISIMPAN OTOMATIS!</b></p>
        <button id="btn-fullscreen">SAYA PAHAM, MULAI UJIAN SEKARANG!</button>
    </div>

    <div id="konten-ujian">
        <div class="header-info">
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nama_siswa']); ?></span>
                <span class="user-id">No: <?php echo htmlspecialchars($_SESSION['kartu_peserta']); ?> | Kls: <?php echo htmlspecialchars($_SESSION['kelas']); ?></span>
            </div>
            
            <div class="timer-badge" id="timer">--:--:--</div>
            
            <div class="stat-badge">
                <span class="mapel-badge"><?php echo htmlspecialchars($mapel_aktif); ?></span>
                <span class="pelanggaran-text">⚠️ Pelanggaran: <span id="pelanggaran-count"><?php echo $dataUjian['jumlah_pelanggaran']; ?></span>/2</span>
            </div>
        </div>

        <div class="container">
            <form id="form-ujian" action="simpan_jawaban.php" method="POST">
                <?php if (empty($daftar_soal)): ?>
                    <div class="soal-box" style="text-align: center; color: var(--text-muted); font-weight: bold;">
                        <p>Belum ada soal tersedia untuk mata pelajaran ini.</p>
                    </div>
                <?php else: ?>
                    <?php $no = 1; foreach ($daftar_soal as $s): ?>
                        <div class="soal-box">
                            <div class="nomor-soal">Soal No. <?php echo $no++; ?></div>
                            
                            <?php if(!empty($s['deskripsi'])): ?>
                                <div style="background: #f8fafc; padding: 15px 20px; border-left: 4px solid var(--success); margin-bottom: 20px; border-radius: 4px; font-size: 15px; color: #475569;">
                                    <?php echo $s['deskripsi']; ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($s['gambar'])): ?>
                                <div style="text-align: left; margin-bottom: 20px;">
                                    <img src="../uploads/<?php echo htmlspecialchars($s['gambar']); ?>" class="img-clickable" style="max-width:100%; cursor:zoom-in;" onclick="bukaPreview(this.src)">
                                </div>
                            <?php endif; ?>

                            <div class="soal-teks">
                                <?php echo $s['pertanyaan']; ?>
                            </div>
                            
                            <?php 
                                $opsi = ['A' => ['teks' => $s['opsi_a'], 'gbr' => $s['gambar_a']], 'B' => ['teks' => $s['opsi_b'], 'gbr' => $s['gambar_b']], 'C' => ['teks' => $s['opsi_c'], 'gbr' => $s['gambar_c']], 'D' => ['teks' => $s['opsi_d'], 'gbr' => $s['gambar_d']], 'E' => ['teks' => $s['opsi_e'], 'gbr' => $s['gambar_e']]];
                                $keys = array_keys($opsi);
                                
                                // Opsi jawaban (PG) tetap diacak untuk semua mapel termasuk B.Inggris & B.Indo
                                shuffle($keys); 
                                $huruf_tampil = ['A', 'B', 'C', 'D', 'E'];
                                
                                foreach($keys as $index => $k): 
                            ?>
                                <label class="opsi">
                                    <input type="radio" name="jawaban[<?php echo $s['id']; ?>]" value="<?php echo $k; ?>"> 
                                    <div class="opsi-teks">
                                        <span style="font-weight: 800; color: var(--primary); margin-right: 5px;"><?php echo $huruf_tampil[$index]; ?>.</span> 
                                        <?php echo $opsi[$k]['teks']; ?>
                                        
                                        <?php if(!empty($opsi[$k]['gbr'])): ?>
                                            <div style="margin-top: 12px; display: block;">
                                                <img src="../uploads/<?php echo htmlspecialchars($opsi[$k]['gbr']); ?>" class="img-clickable" style="max-height: 120px; cursor: zoom-in;" onclick="bukaPreview(this.src)">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-submit" onclick="bukaConfirm()">SELESAI & KIRIM JAWABAN</button>
                <?php endif; ?>
            </form>
            <div style="margin-top: 40px; text-align: center;">
                <?php include 'footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
        let pelanggaran = <?php echo $dataUjian['jumlah_pelanggaran']; ?>;
        const maxPelanggaran = 2; 

        let isPengawasanAktif = false; 
        let isUjianFullscreen = false; 
        let isSubmitting = false; 

        const overlay = document.getElementById('fullscreen-overlay');
        const kontenUjian = document.getElementById('konten-ujian');
        const elem = document.documentElement;

        function mencegahAksi(e) {
            if(e.keyCode === 123 || (e.ctrlKey && e.shiftKey && e.keyCode === 73) || (e.ctrlKey && e.keyCode === 83) || (e.ctrlKey && e.keyCode === 80) || e.keyCode === 116 || (e.ctrlKey && e.keyCode === 82)) {
                e.preventDefault(); return false;
            }
        }

        function tampilkanAlert(pesan) {
            const alertBox = document.getElementById('custom-alert');
            document.getElementById('custom-alert-text').innerHTML = pesan;
            alertBox.style.display = 'block';
            setTimeout(() => { alertBox.style.display = 'none'; }, 4500);
        }

        function bukaConfirm() {
            const totalSoal = <?php echo count($daftar_soal); ?>;
            const jawabanTerisi = document.querySelectorAll('input[type="radio"]:checked').length;

            if (jawabanTerisi < totalSoal) {
                let belumDijawab = totalSoal - jawabanTerisi;
                tampilkanAlert("⚠️ MASIH ADA " + belumDijawab + " SOAL KOSONG!<br>Silakan periksa dan jawab semua soal sebelum mengirim.");
                return;
            }

            document.getElementById('custom-confirm').style.display = 'flex';
        }

        function tutupConfirm() {
            document.getElementById('custom-confirm').style.display = 'none';
        }

        function submitFormFinal() {
            isSubmitting = true; 
            document.getElementById('custom-confirm').style.display = 'none';
            document.getElementById('form-ujian').submit();
        }

        document.getElementById('btn-fullscreen').addEventListener('click', () => {
            if (elem.requestFullscreen) { elem.requestFullscreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }
            
            overlay.style.display = 'none';
            kontenUjian.style.display = 'block';
            setTimeout(() => { isUjianFullscreen = true; isPengawasanAktif = true; }, 1000);
        });

        function catatPelanggaran(jenis) {
            if(!isPengawasanAktif || isSubmitting) return; 
            
            pelanggaran++;
            document.getElementById('pelanggaran-count').innerText = pelanggaran;

            let formData = new FormData();
            formData.append('ujian_id', <?php echo $ujian_id; ?>);
            formData.append('jumlah', pelanggaran);
            navigator.sendBeacon('catat_pelanggaran.php', formData);

            if (pelanggaran >= maxPelanggaran) {
                isSubmitting = true; 
                document.getElementById('layar-hitam').style.display = 'flex';
                setTimeout(() => { document.getElementById('form-ujian').submit(); }, 2000);
            } else {
                tampilkanAlert("⚠️ PELANGGARAN FATAL: " + jenis + " (" + pelanggaran + "/" + maxPelanggaran + ")");
            }
        }

        document.addEventListener('visibilitychange', () => { 
            if (document.hidden && isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Membuka Aplikasi Lain / Keluar Browser');
            }
        });

        window.addEventListener('blur', () => {
            if(isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Membuka Bilah Notifikasi / Navigasi / Hilang Fokus');
            }
        });

        document.addEventListener('touchcancel', () => {
            if(isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Membuka Bilah Sistem Android/iOS (Notifikasi/Navigasi)');
            }
        });

        document.addEventListener('mouseleave', (e) => {
            if(e.clientY <= 0 && isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Kursor Keluar Area Ujian (Mencoba Pindah Tab)');
            }
        });

        function cekLayarPenuh() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement && isUjianFullscreen && !isSubmitting) {
                catatPelanggaran("Keluar dari Mode Layar Penuh");
                overlay.style.display = 'flex'; 
                kontenUjian.style.display = 'none';
                isUjianFullscreen = false; 
                isPengawasanAktif = false; 
            }
        }
        document.addEventListener('fullscreenchange', cekLayarPenuh);
        document.addEventListener('webkitfullscreenchange', cekLayarPenuh);
        document.addEventListener('msfullscreenchange', cekLayarPenuh);

        window.addEventListener('beforeunload', (e) => {
            if (isPengawasanAktif && !isSubmitting) {
                e.preventDefault();
                e.returnValue = ''; 
            }
        });

        setInterval(() => {
            if(!isPengawasanAktif || isSubmitting) return;
            let formPing = new FormData();
            formPing.append('ujian_id', <?php echo $ujian_id; ?>);
            fetch('ping_ujian.php', { method: 'POST', body: formPing })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'stop') {
                    isSubmitting = true;
                    window.location.href = 'selesai_ujian.php';
                }
            }).catch(() => {});
        }, 5000); 

        let sisaDetik = <?php echo $sisa_detik; ?>;
        const timerElement = document.getElementById('timer');
        const formUjian = document.getElementById('form-ujian');

        const hitungMundur = setInterval(() => {
            if (sisaDetik <= 0) {
                clearInterval(hitungMundur);
                isSubmitting = true;
                tampilkanAlert("Waktu habis! Mengirim otomatis...");
                setTimeout(() => { formUjian.submit(); }, 2000);
            } else {
                let jam = Math.floor(sisaDetik / 3600);
                let menit = Math.floor((sisaDetik % 3600) / 60);
                let detik = (sisaDetik % 3600) % 60;
                timerElement.innerText = (jam < 10 ? "0" + jam : jam) + ":" + (menit < 10 ? "0" + menit : menit) + ":" + (detik < 10 ? "0" + detik : detik);
                sisaDetik--;
            }
        }, 1000);

        function bukaPreview(srcGambar) {
            isPengawasanAktif = false; // Matikan sementara sensor saat zoom
            document.getElementById('imgPreview').src = srcGambar;
            document.getElementById('previewModal').style.display = 'flex';
        }

        function tutupPreview() {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('imgPreview').src = '';
            setTimeout(() => { isPengawasanAktif = true; }, 500); // Nyalakan lagi setelah sedikit jeda
        }

        // Tambahkan di dalam <script>
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.opsi').forEach(el => el.classList.remove('opsi-selected'));
                this.closest('.opsi').classList.add('opsi-selected');
            });
        });
    </script>
</body>
</html>