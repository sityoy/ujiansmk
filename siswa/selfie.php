<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
require '../koneksi.php';

// Pastikan siswa sudah login dan jadwal sudah terpilih
if (!isset($_SESSION['siswa_id'], $_SESSION['jadwal_id'], $_SESSION['mapel_aktif'])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
$mapel_aktif = $_SESSION['mapel_aktif'];

try {
    // Cek apakah siswa punya sesi ujian yang belum selesai
    $stmtCek = $pdo->prepare("SELECT id FROM ujian_siswa WHERE siswa_id = ? AND mata_pelajaran = ? AND waktu_selesai IS NULL");
    $stmtCek->execute([$siswa_id, $mapel_aktif]);
    $sesi_aktif = $stmtCek->fetch();

    if ($sesi_aktif) {
        $_SESSION['ujian_id'] = $sesi_aktif['id'];
        header("Location: ujian.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error Database di Selfie: " . $e->getMessage());
    die("Terjadi kesalahan saat membuka verifikasi. Silakan hubungi administrator.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verifikasi Selfie - CBT</title>
    <style>
        body { 
            font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; 
            align-items: center; min-height: 100vh; margin: 0; flex-direction: column;
            user-select: none; -webkit-user-select: none; -moz-user-select: none;
            overscroll-behavior-y: none;
        }

        /* Overlay Fullscreen Pengunci Awal */
        #fullscreen-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.96); color: white; display: flex; 
            flex-direction: column; justify-content: center; align-items: center; 
            z-index: 9999; text-align: center; padding: 20px; box-sizing: border-box; 
        }
        .peringatan-box { 
            background: #dc3545; color: white; padding: 15px; border-radius: 8px; 
            margin: 20px 0; border: 2px solid #ff0000; max-width: 500px; text-align: left; line-height: 1.6;
        }
        #btn-aktifkan-fullscreen { 
            background: #28a745; color: white; padding: 15px 30px; font-size: 18px; 
            border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.3s; 
        }
        #btn-aktifkan-fullscreen:hover { background: #1e7e34; }

        /* Konten Utama Selfie */
        #konten-selfie { display: none; width: 100%; display: flex; justify-content: center; align-items: center; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 420px; width: 90%; box-sizing: border-box; }
        video, canvas { width: 100%; border-radius: 8px; background: #000; margin-bottom: 15px; transform: scaleX(-1); }
        
        .btn { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; width: 100%; font-weight: bold; transition: 0.3s; }
        .btn:disabled { background: #6c757d; cursor: not-allowed; }
        
        .peringatan-keras { 
            background: #fff3cd; color: #856404; border: 1px solid #ffeeba; 
            padding: 12px; border-radius: 6px; text-align: left; margin-bottom: 15px; font-size: 13px; line-height: 1.5;
        }
        .peringatan-danger { background: #dc3545; color: white; border: 1px solid #bd2130; }
        .badge-pelanggaran { background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
    </style>
</head>
<body oncontextmenu="return false;" onselectstart="return false;">

    <div id="fullscreen-overlay">
        <h2 style="color: #ffc107; margin-bottom: 5px;">🔒 KUNCI MODE UJIAN</h2>
        <p style="margin-top: 0; color: #ccc;">Tahap Verifikasi Wajah & Identitas Peserta</p>
        
        <div class="peringatan-box">
            <h4 style="margin-top: 0; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 5px;">⚠️ PERATURAN SISTEM ANTI-CHEAT ⚠️</h4>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li>Wajib masuk ke mode <b>Layar Penuh (Fullscreen)</b> untuk verifikasi.</li>
                <li>Dilarang menekan tombol <b>KEMBALI / HOME / REFRESH</b>.</li>
                <li>Dilarang keluar dari mode fullscreen atau mencoba mengecilkan browser.</li>
                <li>Pelanggaran di halaman ini langsung digabung ke halaman ujian!</li>
            </ul>
        </div>
        <button id="btn-aktifkan-fullscreen">SIAP, MASUK LAYAR PENUH & VERIFIKASI</button>
    </div>

    <div id="konten-selfie" style="display: none;">
        <div class="card">
            <h3 style="margin-top: 0;">📸 Verifikasi Wajah Peserta</h3>
            
            <div id="box-info" class="peringatan-keras">
                <strong>SISTEM PENGAWASAN AKTIF:</strong><br>
                Jangan tarik bilah notifikasi atas, tombol navigasi bawah, atau keluar dari fullscreen.
            </div>

            <div style="margin-bottom: 10px; font-size: 14px; font-weight: bold; color: #333;">
                Status Pelanggaran: <span id="txt-pelanggaran" class="badge-pelanggaran">0</span> / 2
            </div>
            
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            
            <button id="btn-mulai" class="btn" disabled>Memuat Kamera...</button>
        </div>
    </div>

    <script>
        const overlay = document.getElementById('fullscreen-overlay');
        const kontenSelfie = document.getElementById('konten-selfie');
        const btnAktifkanFullscreen = document.getElementById('btn-aktifkan-fullscreen');
        
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const btnMulai = document.getElementById('btn-mulai');
        const txtPelanggaran = document.getElementById('txt-pelanggaran');
        const boxInfo = document.getElementById('box-info');
        const elem = document.documentElement;

        let isCameraReady = false; 
        let isUploading = false;
        let isPengawasanAktif = false;
        let isUjianFullscreen = false;
        let pelanggaranSelfie = 0;
        const maxPelanggaran = 2;

        // FUNGSI MEMAKSA LAYAR PENUH DI AWAL
        btnAktifkanFullscreen.addEventListener('click', () => {
            if (elem.requestFullscreen) { elem.requestFullscreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }
            
            overlay.style.display = 'none';
            kontenSelfie.style.setProperty('display', 'flex', 'important');
            
            // Nyalakan Kamera setelah fullscreen aktif
            aktifkanKamera();
            
            // Beri jeda aman sebelum sensor pengawasan diaktifkan penuh
            setTimeout(() => { 
                isUjianFullscreen = true; 
                isPengawasanAktif = true; 
            }, 1200);
        });

        function aktifkanKamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => {
                video.srcObject = stream;
                btnMulai.innerText = "Ambil Foto & Mulai Ujian";
                btnMulai.disabled = false;
                setTimeout(() => { isCameraReady = true; }, 1000);
            })
            .catch(err => {
                alert("Akses kamera ditolak atau kamera tidak ditemukan!");
                btnMulai.innerText = "Kamera Error";
            });
        }

        // ==========================================
        // 🚨 EKSEKUSI PELANGGARAN KETAT (TANPA AMPUN) 🚨
        // ==========================================
        function prosesPelanggaran(jenis) {
            if (!isPengawasanAktif || isUploading) return;

            pelanggaranSelfie++;
            txtPelanggaran.innerText = pelanggaranSelfie;

            boxInfo.className = "peringatan-keras peringatan-danger";
            boxInfo.innerHTML = `<strong>❌ PELANGGARAN TERDETEKSI!</strong><br>Tindakan manipulasi: <b>${jenis}</b>.`;

            if (pelanggaranSelfie >= maxPelanggaran) {
                isUploading = true; 
                isPengawasanAktif = false;
                btnMulai.disabled = true;
                btnMulai.style.background = "#bd2130";
                btnMulai.innerText = "AKSES DIKUNCI / BLOKIR";
                
                alert("❌ BLOKIR TOTAL!\nAnda melanggar aturan verifikasi sebanyak 2 kali.\nAkses ujian dibatalkan secara otomatis!");
                window.location = 'login.php';
            } else {
                alert(`⚠️ PERINGATAN KETAT (Poin: ${pelanggaranSelfie}/${maxPelanggaran})!\nSistem mendeteksi Anda mencoba berbuat curang (${jenis}).\nSatu kali lagi melanggar, akun Anda langsung DIBLOKIR!`);
            }
        }

        // TAMBAHAN BARU: Deteksi jika siswa memaksa keluar dari Mode Fullscreen (Menekan ESC / Swipe)
        function cekPerubahanLayar() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement && isUjianFullscreen && !isUploading) {
                prosesPelanggaran("Keluar dari Mode Layar Penuh");
                
                // Kembalikan ke overlay pengunci jika belum mencapai batas blokir maksimal
                if (pelanggaranSelfie < maxPelanggaran) {
                    overlay.style.display = 'flex';
                    kontenSelfie.style.display = 'none';
                    isUjianFullscreen = false;
                    isPengawasanAktif = false;
                }
            }
        }
        document.addEventListener('fullscreenchange', cekPerubahanLayar);
        document.addEventListener('webkitfullscreenchange', cekPerubahanLayar);
        document.addEventListener('msfullscreenchange', cekPerubahanLayar);

        // Deteksi ganti aplikasi / meminimalkan browser
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && isPengawasanAktif && !isUploading) {
                prosesPelanggaran("Berpindah Aplikasi / Meminimalkan Layar");
            }
        });

        // Deteksi tarik bilah notifikasi atas / lepas fokus jendela browser
        window.addEventListener('blur', () => {
            if (isPengawasanAktif && !isUploading) {
                prosesPelanggaran("Membuka Bilah Notifikasi / Navigasi / Chat Bubble");
            }
        });

        // Deteksi interupsi sistem gesture HP (Swipe tepi layar)
        document.addEventListener('touchcancel', () => {
            if (isPengawasanAktif && !isUploading) {
                prosesPelanggaran("Interupsi Menu Navigasi HP");
            }
        });

        // Deteksi kursor meninggalkan layar atas browser (Khusus PC/Laptop)
        document.addEventListener('mouseleave', (e) => {
            if (e.clientY <= 0 && isPengawasanAktif && !isUploading) {
                prosesPelanggaran("Kursor Meninggalkan Jendela Ujian");
            }
        });

        // Cegah tombol Refresh / Back tidak sengaja
        window.addEventListener('beforeunload', (e) => {
            if (isPengawasanAktif && !isUploading) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ==========================================
        // PROSES KIRIM DATA FOTO & MODAL PELANGGARAN
        // ==========================================
        btnMulai.addEventListener('click', () => {
            isUploading = true;
            isPengawasanAktif = false; // Matikan pengawasan agar tidak bentrok saat pindah halaman
            btnMulai.disabled = true;
            btnMulai.innerText = "Memproses Sesi Ujian...";

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('image', blob, 'selfie_peserta.jpg');
                formData.append('pelanggaran_awal', pelanggaranSelfie); 

                fetch('mulai_ujian.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location = 'ujian.php';
                    } else {
                        alert(data.message);
                        window.location.reload();
                    }
                })
                .catch(error => {
                    alert('Gagal terhubung ke server.');
                    window.location.reload();
                });
            }, 'image/jpeg', 0.8);
        });
    </script>
</body>
</html>