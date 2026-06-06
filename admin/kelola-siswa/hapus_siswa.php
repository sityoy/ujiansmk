<?php
session_start();
require '../../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
        $stmt->execute([$id]);
        
        echo "<script>alert('Data siswa berhasil dihapus!'); window.location='index.php';</script>";
    } catch (Exception $e) {
        die("Gagal menghapus siswa: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
}
?>