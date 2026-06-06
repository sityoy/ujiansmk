<?php
$host = 'localhost';
$dbname = 'db_ujiansmkbhg';
$username = 'root'; // Sesuaikan jika Anda menggunakan username MySQL lain
$password = 'Smkpb@#1'; // Kosongkan jika menggunakan XAMPP default

try {
    // CONTOH KONEKSI YANG BENAR (Tambahkan ;charset=utf8mb4)

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Atur mode error ke exception agar masalah mudah dilacak
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>