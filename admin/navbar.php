<?php
require_once "cek_admin.php";

// Mendapatkan nama file saat ini untuk efek menu 'Aktif'
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];
?>
<style>
    /* CSS Responsif bergaya Modern Dashboard */
    .navbar-container {
        background: #ffffff;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        margin-bottom: 30px;
        border-radius: 12px;
        border-bottom: 4px solid #4361ee;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .navbar-brand {
        font-size: 22px;
        font-weight: 800;
        color: #2b2d42;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.5px;
    }
    
    .navbar-brand span {
        color: #4361ee;
    }

    .nav-links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .nav-links a {
        color: #64748b;
        text-decoration: none;
        font-weight: 600;
        padding: 10px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-size: 14.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Efek Hover (Saat Disentuh Mouse) */
    .nav-links a:hover {
        background: #f8fafc;
        color: #4361ee;
        transform: translateY(-2px);
    }

    /* Efek Aktif (Halaman yang sedang dibuka) */
    .nav-links a.active {
        background: #e0e7ff;
        color: #4361ee;
        box-shadow: 0 2px 8px rgba(67, 97, 238, 0.15);
    }

    /* Tombol Khusus (Backup & Logout) */
    .btn-backup { 
        background: #dcfce7 !important; 
        color: #166534 !important; 
        border: 1px solid #bbf7d0; 
        margin-left: 10px;
    }
    .btn-backup:hover { 
        background: #2ecc71 !important; 
        color: white !important; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 10px rgba(46, 204, 113, 0.2); 
    }
    
    .btn-logout { 
        background: #fee2e2 !important; 
        color: #dc2626 !important; 
        border: 1px solid #fecaca; 
    }
    .btn-logout:hover { 
        background: #e74c3c !important; 
        color: white !important; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 10px rgba(231, 76, 60, 0.2); 
    }
    
    /* Tampilan Responsif (Mobile / HP) */
    @media (max-width: 950px) {
        .navbar-container { flex-direction: column; text-align: center; padding: 20px 15px; }
        .nav-links { justify-content: center; width: 100%; margin-top: 10px;}
        .btn-backup { margin-left: 0; }
    }
    
    @media (max-width: 600px) {
        .nav-links { flex-direction: column; width: 100%; gap: 10px; }
        .nav-links a { width: 100%; justify-content: center; box-sizing: border-box; }
    }
</style>

<div class="navbar-container">
    <a href="index.php" class="navbar-brand">
        ⚡ <span>CBT</span> Panel
    </a>
    
    <div class="nav-links">
        <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">🏠 Dashboard</a>
        <a href="soal.php" class="<?php echo ($current_page == 'soal.php' || strpos($current_uri, 'tambah_soal') !== false || strpos($current_uri, 'edit_soal') !== false) ? 'active' : ''; ?>">📚 Kelola Soal</a>
        <a href="jadwal.php" class="<?php echo ($current_page == 'jadwal.php') ? 'active' : ''; ?>">⏰ Jadwal</a>
        <a href="nilai.php" class="<?php echo ($current_page == 'nilai.php' || strpos($current_uri, 'detail_nilai') !== false) ? 'active' : ''; ?>">📊 Nilai</a>
        <a href="kelola-siswa/index.php" class="<?php echo (strpos($current_uri, 'kelola-siswa') !== false) ? 'active' : ''; ?>">👥 Siswa</a>
        
        <a href="backup_database.php" class="btn-backup">💾 Backup DB</a>
        <a href="logout.php" class="btn-logout" onclick="return confirm('Yakin ingin keluar dari panel admin?')">🚪 Logout</a>
    </div>
</div>