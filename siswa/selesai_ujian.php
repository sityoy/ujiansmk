<?php
session_start();
require 'cek_admin.php';

if (!isset($_SESSION['ujian_id'])) {
    header("Location: ../index.php");
    exit;
}

$ujian_id = $_SESSION['ujian_id'];

// Ambil nilai akhir dari database
$stmt = $pdo->prepare("SELECT nilai, jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmt->execute([$ujian_id]);
$hasil = $stmt->fetch();

// Hapus sesi login agar siswa tidak bisa menekan tombol "Back" ke lembar ujian
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian Selesai - CBT</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; background: #e9f5ee; padding: 50px 20px; margin: 0; }
        .box { background: white; max-width: 500px; margin: auto; padding: 40px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-top: 5px solid #28a745; }
        h2 { color: #333; }
        .nilai { font-size: 56px; font-weight: bold; color: #28a745; margin: 20px 0; }
        .warning { color: #d9534f; font-size: 14px; margin-top: 15px; padding: 10px; background: #fdf2f2; border-radius: 5px; }
        a.btn { display: inline-block; background: #007bff; color: white; text-decoration: none; padding: 12px 25px; border-radius: 5px; margin-top: 30px; font-weight: bold; transition: 0.3s; }
        a.btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Ujian Telah Selesai!</h2>
        <p>Terima kasih telah mengerjakan ujian ini. Jawaban Anda telah tersimpan dengan aman di server.</p>
        
        <p style="margin-top: 30px; color: #555;">Nilai Akhir Anda:</p>
        <div class="nilai"><?php echo number_format($hasil['nilai'], 2); ?></div>
        
        <?php if ($hasil['jumlah_pelanggaran'] > 0): ?>
            <div class="warning">
                <strong>Catatan Sistem:</strong> Anda tercatat melakukan pelanggaran (Keluar jendela/Split Screen) sebanyak <strong><?php echo $hasil['jumlah_pelanggaran']; ?> kali</strong>. Hal ini telah dilaporkan kepada Admin Guru.
            </div>
        <?php endif; ?>
        
        <a href="../index.php" class="btn">Kembali ke Halaman Utama</a>
    </div>
</body>
</html>