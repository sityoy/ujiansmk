<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Pastikan admin sudah login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Konfigurasi Database (Sesuaikan jika diperlukan)
$host = 'localhost';
$user = 'root';
$pass = 'Smkpb@#1';
$db   = 'db_ujiansmkbhg';

try {
    // Koneksi menggunakan PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Format Nama File Backup (Contoh: Backup_CBT_2026-06-07_15-30-00.sql)
$nama_file = 'Backup_CBT_' . date('Y-m-d_H-i-s') . '.sql';

// Set Header agar browser langsung mendownload file
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Tulis Header SQL
$sql_dump = "-- Backup Database CBT SMK ISLAM BAHAGIA\n";
$sql_dump .= "-- Waktu Backup: " . date('d F Y H:i:s') . "\n\n";

// Matikan sementara pengecekan Foreign Key agar saat di-import tidak error
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// 1. Ambil daftar semua tabel di database
$queryTables = $pdo->query('SHOW TABLES');
$tables = $queryTables->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // 2. Tambahkan perintah DROP TABLE
    $sql_dump .= "-- Struktur untuk tabel `$table`\n";
    $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";

    // 3. Ambil struktur CREATE TABLE
    $queryCreate = $pdo->query("SHOW CREATE TABLE `$table`");
    $createTable = $queryCreate->fetch(PDO::FETCH_ASSOC);
    $sql_dump .= $createTable['Create Table'] . ";\n\n";

    // 4. Ambil isi data dari tabel (INSERT INTO)
    $queryData = $pdo->query("SELECT * FROM `$table`");
    $rows = $queryData->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 0) {
        $sql_dump .= "-- Dumping data untuk tabel `$table`\n";
        
        foreach ($rows as $row) {
            $keys = array_keys($row);
            $values = array_values($row);
            
            // Format setiap value agar aman dari karakter khusus (Escape String)
            $escaped_values = array_map(function($val) use ($pdo) {
                if (is_null($val)) return "NULL";
                return $pdo->quote($val); // Memberikan tanda kutip tunggal secara otomatis
            }, $values);

            $sql_dump .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
        }
        $sql_dump .= "\n";
    }
}

// Kembalikan pengaturan Foreign Key
$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Keluarkan / Cetak isi variabel agar terdownload oleh browser
echo $sql_dump;
exit;
?>