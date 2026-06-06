<?php
require 'koneksi.php'; // Sesuaikan lokasi koneksi Bapak

// Ganti dengan username admin yang sudah ada di database Bapak
$username = 'AdminBHG'; 

// Ganti dengan password baru yang Bapak inginkan (teks biasa)
$password_baru = 'RahasiaBHG@!1'; 

$nama_lengkap = 'SMK ISLAM BAHAGIA'; 

// Proses enkripsi/hashing (Bcrypt)
$password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

// Update ke database
$stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE username = ?; nama_lengkap = ?");
$hasil = $stmt->execute([$password_hash, $username, $nama_lengkap]);

if ($hasil) {
    echo "<h3>Berhasil!</h3>";
    echo "Password untuk <strong>{$username}</strong> berhasil di-hash.<br><br>";
    echo "Hash yang tersimpan: <br><code style='background:#eee; padding:5px;'>{$password_hash}</code><br><br>";
    echo "<strong>PENTING:</strong> Segera hapus file <code>buat_admin.php</code> ini demi keamanan!";
} else {
    echo "Gagal mengupdate password.";
}
?>