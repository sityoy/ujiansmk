<?php
// Pengaturan Database
$config = [
    'host' => 'localhost',
    'dbname' => 'db_ujiansmkbhg',
    'username' => 'root',
    'password' => 'Smkpb@#1',
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

$host = getenv('DB_HOST') ?: $config['host'];
$dbname = getenv('DB_NAME') ?: $config['dbname'];
$username = getenv('DB_USER') ?: $config['username'];
$password = getenv('DB_PASS') ?: $config['password'];

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
