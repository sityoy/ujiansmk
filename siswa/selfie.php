<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../koneksi.php';

// Pastikan siswa sudah login dan jadwal sudah terpilih
if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['jadwal_id'])) {
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
    die("Error Database di Selfie: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Selfie - CBT</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; flex-direction: column;}
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
        video, canvas { width: 100%; border-radius: 8px; background: #000; margin-bottom: 15px; }
        .btn { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; width: 100%; font-weight: bold; }
        .btn:disabled { background: #6c757d; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="card">
        <h3 style="margin-top: 0;">📸 Verifikasi Peserta</h3>
        <p style="font-size: 14px; color: #666;">Silakan posisikan wajah Anda di kamera, lalu klik tombol di bawah untuk mulai ujian.</p>
        
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
        
        <button id="btn-mulai" class="btn" disabled>Memuat Kamera...</button>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const btnMulai = document.getElementById('btn-mulai');

        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => {
            video.srcObject = stream;
            btnMulai.innerText = "Ambil Foto & Mulai Ujian";
            btnMulai.disabled = false;
        })
        .catch(err => {
            alert("Akses kamera ditolak atau kamera tidak ditemukan!");
            btnMulai.innerText = "Kamera Error";
        });

        btnMulai.addEventListener('click', () => {
            btnMulai.disabled = true;
            btnMulai.innerText = "Memproses Ujian...";

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob(function(blob) {
                const formData = new FormData();
                formData.append('image', blob, 'selfie_peserta.jpg');

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
                        btnMulai.disabled = false;
                        btnMulai.innerText = "Coba Lagi";
                    }
                })
                .catch(error => {
                    alert('Gagal terhubung ke server. Pastikan folder assets/ memiliki izin (permission).');
                    btnMulai.disabled = false;
                    btnMulai.innerText = "Coba Lagi";
                });
            }, 'image/jpeg', 0.8);
        });
    </script>
</body>
</html>