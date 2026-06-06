<?php
session_start();
require '../koneksi.php';

// Pastikan ada ujian_id di sesi
if (!isset($_SESSION['ujian_id'])) {
    header("Location: ../index.php");
    exit;
}

$ujian_id = $_SESSION['ujian_id'];

// Ambil nilai akhir dari database
$stmt = $pdo->prepare("SELECT nilai, jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmt->execute([$ujian_id]);
$hasil = $stmt->fetch();

// JANGAN DESTROY DULU, biarkan nilai ditampilkan dulu.
// Kita hanya akan menghapus session_id ujian agar tidak bisa balik ke soal
unset($_SESSION['ujian_id']); 
// Jika ingin logout total, lakukan nanti saja saat siswa menekan tombol "Kembali ke Utama"
?>
<!DOCTYPE html>
<html lang="id">
<body>
    <div class="box">
        <h2>Ujian Telah Selesai!</h2>
        <?php if ($hasil): ?>
            <div class="nilai"><?php echo number_format($hasil['nilai'] ?? 0, 2); ?></div>
            <?php if ($hasil['jumlah_pelanggaran'] > 0): ?>
                <div class="warning">
                    <strong>Catatan Sistem:</strong> Anda tercatat melakukan pelanggaran sebanyak <strong><?php echo $hasil['jumlah_pelanggaran']; ?> kali</strong>.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:red;">Data nilai tidak ditemukan.</p>
        <?php endif; ?>
        
        <a href="logout.php" class="btn">Keluar & Kembali ke Utama</a>
    </div>
</body>
</html>