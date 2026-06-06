<?php
require 'koneksi.php';

try {
    $pdo->beginTransaction();

    // 1. Buat Data Admin Guru
    $username_admin = 'admin';
    $password_admin = 'admin123';
    $nama_admin = 'Tio Irfan Antoni';
    $hash_admin = password_hash($password_admin, PASSWORD_DEFAULT);

    // Cek apakah admin sudah ada agar tidak duplikat
    $stmtCek = $pdo->prepare("SELECT id FROM admin WHERE username = ?");
    $stmtCek->execute([$username_admin]);
    if ($stmtCek->rowCount() == 0) {
        $stmtAdmin = $pdo->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES (?, ?, ?)");
        $stmtAdmin->execute([$username_admin, $hash_admin, $nama_admin]);
        echo "<p>✅ Admin Guru berhasil ditambahkan.</p>";
    } else {
        echo "<p>⚠️ Admin Guru sudah ada di database.</p>";
    }

    // 2. Buat Data 5 Siswa
    $password_siswa = 'siswa123';
    $hash_siswa = password_hash($password_siswa, PASSWORD_DEFAULT);

    $data_siswa = [
        ['12001', 'Andi Saputra', 'XII AK'],
        ['12002', 'Budi Santoso', 'XII AK'],
        ['12003', 'Citra Lestari', 'XII AK'],
        ['12004', 'Dewi Anggraini', 'XII AK'],
        ['12005', 'Eko Prasetyo', 'XII AK']
    ];

    $stmtSiswa = $pdo->prepare("INSERT INTO siswa (kartu_peserta, password, nama_siswa, kelas, status_ujian) VALUES (?, ?, ?, ?, 'belum')");
    
    $siswa_berhasil = 0;
    foreach ($data_siswa as $s) {
        // Cek agar tidak error duplikat No Peserta
        $stmtCekSiswa = $pdo->prepare("SELECT id FROM siswa WHERE kartu_peserta = ?");
        $stmtCekSiswa->execute([$s[0]]);
        if ($stmtCekSiswa->rowCount() == 0) {
            $stmtSiswa->execute([$s[0], $hash_siswa, $s[1], $s[2]]);
            $siswa_berhasil++;
        }
    }

    $pdo->commit();
    
    if ($siswa_berhasil > 0) {
        echo "<p>✅ $siswa_berhasil Data Siswa berhasil ditambahkan.</p>";
    } else {
        echo "<p>⚠️ Data Siswa sudah ada di database atau tidak ada yang ditambahkan.</p>";
    }

    echo "<h3>Daftar Akun untuk Uji Coba:</h3>";
    echo "<strong>Admin Guru:</strong> Username: <code>admin</code> | Password: <code>admin123</code><br><br>";
    echo "<strong>Siswa:</strong><br>";
    echo "No. Peserta: <code>12001</code> s/d <code>12005</code><br>";
    echo "Password semua siswa: <code>siswa123</code><br><br>";
    echo "<p style='color:red;'><em>Pastikan menghapus file buat_dummy.php ini setelah selesai uji coba!</em></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Terjadi kesalahan: " . $e->getMessage());
}
?>