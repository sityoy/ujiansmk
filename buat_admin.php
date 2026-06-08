<?php
require 'koneksi.php'; // Sesuaikan lokasi koneksi Bapak

// 1. Siapkan Data Admin Baru
$username = 'AdminSMK'; 
$password_baru = 'SMKBHG@!1'; 
$nama_lengkap = 'Administrator SMK ISLAM BAHAGIA'; // <-- Ini dia nama_lengkap nya!

// 2. Proses enkripsi/hashing (Bcrypt)
$password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

// 3. Insert ke database (Membuat Baris Baru)
$stmt = $pdo->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES (?, ?, ?)");
$hasil = $stmt->execute([$username, $password_hash, $nama_lengkap]);

if ($hasil) {
    echo "<h3>Berhasil Membuat Admin Baru!</h3>";
    echo "Nama Lengkap: <strong>{$nama_lengkap}</strong><br>";
    echo "Username: <strong>{$username}</strong><br>";
    echo "Password Asli: {$password_baru}<br>";
    echo "Hash Tersimpan: <br><code style='background:#eee; padding:5px; display:block; margin-top:5px;'>{$password_hash}</code><br><br>";
    echo "<strong style='color:red;'>PENTING:</strong> Segera hapus file <code>buat_admin.php</code> ini demi keamanan server!";
} else {
    echo "Gagal membuat admin baru.";
}
?>