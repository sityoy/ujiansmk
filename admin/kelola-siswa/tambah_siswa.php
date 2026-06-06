<?php
require 'cek_admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Tambahkan pengaman null coalescing (?? '') agar tidak ada error undefined key
    $kartu_peserta = trim($_POST['kartu_peserta'] ?? '');
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if($kartu_peserta != '' && $nama_siswa != '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO siswa (kartu_peserta, nama_siswa, kelas, password, status_ujian) VALUES (?, ?, ?, ?, 'belum')");
            $stmt->execute([$kartu_peserta, $nama_siswa, $kelas, $password]);
            echo "<script>alert('Siswa berhasil ditambahkan!'); window.location='index.php';</script>";
        } catch (Exception $e) {
            // Tangkap jika No Peserta Duplikat
            if($e->getCode() == 23000) {
                echo "<script>alert('Gagal: Nomor Peserta sudah digunakan!');</script>";
            } else {
                die("Gagal menyimpan data: " . $e->getMessage());
            }
        }
    }
}
?>