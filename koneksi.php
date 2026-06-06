<?php
// Pengaturan Database
$host     = 'localhost'; // Gunakan 127.0.0.1 atau localhost jika DB di VPS yang sama
$dbname   = 'db_ujiansmkbhg'; // Ganti dengan nama database di MariaDB
$username = 'root'; // Ganti dengan username database
$password = 'Smkpb@#1'; // Ganti dengan password database

// Pengaturan tambahan PDO untuk keamanan dan performa
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Mode pelaporan error yang ketat
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Menarik data dalam bentuk Array Associative
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Mematikan emulasi prepare statement (lebih aman dari SQL Injection)
];

try {
    // Membuat koneksi ke MariaDB
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    
    // (Opsional) Jika ingin memastikan zona waktu database sinkron dengan aplikasi
    $pdo->exec("SET time_zone = '+07:00'"); // Waktu Indonesia Barat (WIB)
    
} catch (PDOException $e) {
    // Tangkap error jika koneksi gagal
    // CATATAN: Di tahap produksi/live, jangan tampilkan $e->getMessage() ke publik agar tidak membocorkan info server.
    die("Koneksi database gagal. Silakan hubungi Administrator.");
}
?>