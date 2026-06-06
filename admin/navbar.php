<?php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
?>
<style>
    /* CSS Responsif khusus untuk Navbar */
    .navbar { background: #007bff; padding: 15px; color: white; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .nav-links { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .navbar a { color: white; text-decoration: none; font-weight: bold; padding: 8px 12px; border-radius: 4px; transition: 0.3s; font-size: 14px; }
    .navbar a:hover { background: rgba(255,255,255,0.2); }
    .btn-backup { background: #28a745 !important; }
    .btn-logout { background: #dc3545 !important; }
    
    @media (max-width: 768px) {
        .navbar { flex-direction: column; align-items: stretch; text-align: center; }
        .nav-links { flex-direction: column; width: 100%; }
        .navbar a { display: block; width: 100%; box-sizing: border-box; }
    }
</style>

<div class="navbar">
    <div class="nav-links">
        <strong style="font-size: 18px; margin-right: 10px;">CBT Panel</strong>
        <a href="index.php">Dashboard</a>
        <a href="soal.php">Kelola Soal</a>
        <a href="jadwal.php">Jadwal Ujian</a>
        <a href="nilai.php">Nilai & Siswa</a>
        <a href="kelola-siswa/index.php">Kelola Siswa</a>
        <a href="backup_database.php" class="btn-backup">💾 Backup Database</a>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
</div>