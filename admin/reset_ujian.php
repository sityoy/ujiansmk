<?php
// Cukup panggil cek_admin.php untuk keamanan dan koneksi
require 'cek_admin.php';

if (isset($_GET['id_ujian']) && isset($_GET['id_siswa'])) {
    $id_ujian = $_GET['id_ujian'];
    $id_siswa = $_GET['id_siswa'];

    try {
        // Mulai transaksi database agar jika ada yang gagal, semuanya dibatalkan (aman)
        $pdo->beginTransaction();

        // 1. HAPUS FOTO SELFIE DARI HOSTING (Biar kapasitas VPS Bapak tidak cepat penuh)
        $stmtFoto = $pdo->prepare("SELECT foto_selfie FROM ujian_siswa WHERE id = ?");
        $stmtFoto->execute([$id_ujian]);
        $foto = $stmtFoto->fetchColumn();

        if ($foto && file_exists("../assets/" . $foto)) {
            unlink("../assets/" . $foto);
        }

        // 2. HAPUS JAWABAN SISWA (Wajib agar tidak nyangkut/error saat siswa ujian lagi)
        $stmtHapusJawaban = $pdo->prepare("DELETE FROM jawaban_siswa WHERE ujian_id = ?");
        $stmtHapusJawaban->execute([$id_ujian]);

        // 3. HAPUS RIWAYAT UJIAN & PELANGGARAN
        $stmtHapusUjian = $pdo->prepare("DELETE FROM ujian_siswa WHERE id = ?");
        $stmtHapusUjian->execute([$id_ujian]);

        // 4. BUKA KUNCI AKUN SISWA (Ubah status dari 'selesai' menjadi 'belum')
        $stmtUpdateSiswa = $pdo->prepare("UPDATE siswa SET status_ujian = 'belum' WHERE id = ?");
        $stmtUpdateSiswa->execute([$id_siswa]);

        $pdo->commit();
        
        // Kembalikan ke halaman nilai setelah sukses
        echo "<script>alert('Ujian berhasil direset! Data pelanggaran dan jawaban lama telah dihapus. Siswa dapat login kembali.'); window.location='nilai.php';</script>";
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Gagal mereset ujian: " . $e->getMessage());
    }
} else {
    // Jika tidak ada ID yang dikirim, tendang balik ke halaman nilai
    header("Location: nilai.php");
    exit;
}
?>