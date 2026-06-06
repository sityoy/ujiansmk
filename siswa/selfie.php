<?php
session_start();
require '../koneksi.php';

// Pastikan siswa sudah login dan sudah memilih jadwal mapel
if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['jadwal_id'])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
$mapel_aktif = $_SESSION['mapel_aktif'];

// CEK APAKAH SISWA SUDAH PUNYA SESI UJIAN AKTIF (Belum Submit)
// Fitur ini berguna jika siswa tidak sengaja keluar/refresh, mereka tidak perlu selfie ulang
$stmtCek = $pdo->prepare("SELECT id FROM ujian_siswa WHERE siswa_id = ? AND mata_pelajaran = ? AND waktu_selesai IS NULL");
$stmtCek->execute([$siswa_id, $mapel_aktif]);
$sesi_aktif = $stmtCek->fetch();

if ($sesi_aktif) {
    // Jika sudah ada sesi ujian yang berjalan, langsung arahkan ke ujian.php
    $_SESSION['ujian_id'] = $sesi_aktif['id'];
    header("Location: ujian.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Selfie - CBT</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; background: #f4f7f6; padding: 20px; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .box { background: white; width: 100%; max-width: 400px; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-top: 5px solid #007bff; }
        h3 { margin-top: 0; color: #333; }
        .mapel-info { background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; color: #007bff; }
        video, canvas { width: 100%; max-width: 320px; border-radius: 8px; background: #000; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
        button { background: #007bff; color: white; border: none; padding: 15px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 20px; width: 100%; transition: 0.3s; }
        button:hover { background: #0056b3; }
        button:disabled { background: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="box">
        <h3>Verifikasi Wajah Peserta</h3>
        
        <div class="mapel-info">
            Mapel: <?php echo htmlspecialchars($mapel_aktif); ?>
        </div>
        
        <p style="font-size: 14px; color: #666; margin-bottom: 20px;">Posisikan Wajah dan Kartu Peserta Anda berada di tengah kamera, lalu klik tombol di bawah untuk memulai ujian.</p>
        
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display:none;"></canvas>
        
        <button id="btn-mulai">Ambil Foto & Mulai Ujian</button>
        <?php include 'footer.php'; ?>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const btnMulai = document.getElementById('btn-mulai');

        // Akses kamera depan HP / Laptop
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { 
                alert("Gagal mengakses kamera! Pastikan izin (permission) kamera diizinkan di browser Anda."); 
            });

        btnMulai.addEventListener('click', () => {
            btnMulai.disabled = true;
            btnMulai.innerText = "Memproses...";

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            // UBAH: Konversi foto menjadi File Asli (Blob), bukan Base64
            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('image', blob, 'selfie_peserta.jpg');

                fetch('mulai_ujian.php', {
                    method: 'POST',
                    body: formData // Kirim sebagai form-data (seperti upload file biasa)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location = 'ujian.php';
                    } else {
                        alert(data.message);
                        btnMulai.disabled = false;
                        btnMulai.innerText = "Ambil Foto & Mulai Ujian";
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan koneksi ke server.');
                    btnMulai.disabled = false;
                    btnMulai.innerText = "Ambil Foto & Mulai Ujian";
                });
            }, 'image/jpeg', 0.8);
        });
    </script>
</body>
</html>