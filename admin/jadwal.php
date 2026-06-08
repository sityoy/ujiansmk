<?php
// Tampilkan pesan error jika ada masalah agar kita tahu penyebabnya
error_reporting(E_ALL);
// ini_set('display_errors', 1);

require 'cek_admin.php';

// 1. Ambil daftar mapel otomatis dari Bank Soal
try {
    $stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != ''");
    $list_mapel = $stmtMapel->fetchAll();
} catch (Exception $e) {
    die("Error Database (Tabel Soal): " . $e->getMessage());
}

// 2. Proses Tambah atau Update Jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_ujian     = trim($_POST['nama_ujian']);
    $mata_pelajaran = trim($_POST['mata_pelajaran']);
    $kelas          = trim($_POST['kelas']); // <-- TAMBAHAN KELAS
    $waktu_mulai    = $_POST['waktu_mulai'];
    $waktu_selesai  = $_POST['waktu_selesai'];

    // Cegah waktu selesai lebih awal dari waktu mulai
    if (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        echo "<script>alert('ERROR: Waktu Selesai tidak boleh lebih awal atau sama dengan Waktu Mulai!'); window.history.back();</script>";
        exit;
    }

    if (isset($_POST['tambah'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pengaturan_ujian (nama_ujian, mata_pelajaran, kelas, waktu_mulai, waktu_selesai) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama_ujian, $mata_pelajaran, $kelas, $waktu_mulai, $waktu_selesai]);
            echo "<script>alert('Jadwal Ujian berhasil ditambahkan!'); window.location='jadwal.php';</script>";
            exit;
        } catch (Exception $e) {
            die("Gagal menambah jadwal: " . $e->getMessage());
        }
    } elseif (isset($_POST['edit'])) {
        $id_jadwal = $_POST['id_jadwal'];
        try {
            $stmt = $pdo->prepare("UPDATE pengaturan_ujian SET nama_ujian=?, mata_pelajaran=?, kelas=?, waktu_mulai=?, waktu_selesai=? WHERE id=?");
            $stmt->execute([$nama_ujian, $mata_pelajaran, $kelas, $waktu_mulai, $waktu_selesai, $id_jadwal]);
            echo "<script>alert('Jadwal Ujian berhasil diupdate!'); window.location='jadwal.php';</script>";
            exit;
        } catch (Exception $e) {
            die("Gagal mengupdate jadwal: " . $e->getMessage());
        }
    }
}

// 3. Proses Hapus Jadwal
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    try {
        $stmtHapus = $pdo->prepare("DELETE FROM pengaturan_ujian WHERE id = ?");
        $stmtHapus->execute([$id_hapus]);
        echo "<script>alert('Jadwal berhasil dihapus!'); window.location='jadwal.php';</script>";
        exit;
    } catch (Exception $e) {
        die("Gagal menghapus jadwal: " . $e->getMessage());
    }
}

// 4. Ambil data untuk Form Edit
$edit_data = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmtEdit = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
        $stmtEdit->execute([$_GET['edit_id']]);
        $edit_data = $stmtEdit->fetch();
    } catch (Exception $e) {
        die("Gagal memuat data edit: " . $e->getMessage());
    }
}

// 5. Ambil Semua Jadwal untuk Tabel
try {
    $stmtAll = $pdo->query("SELECT * FROM pengaturan_ujian ORDER BY waktu_mulai DESC");
    $semua_jadwal = $stmtAll->fetchAll();
} catch (Exception $e) {
    die("Error Database (Tabel Pengaturan Ujian): " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jadwal Ujian</title>
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --secondary: #3b82f6;
            --secondary-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --warning: #f59e0b;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-main); 
            color: var(--text-main); 
            margin: 0; 
            padding: 30px 20px; 
        }

        .container { 
            max-width: 1100px; 
            margin: auto; 
            background: var(--card-bg); 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.04); 
        }

        .header-title { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid var(--border-color); 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
            margin-top: 0;
        }

        .header-title h2 { margin: 0; color: var(--text-main); font-weight: 700; }
        
        .btn-back { 
            background: var(--secondary); 
            color: white; 
            padding: 10px 16px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: 600; 
            font-size: 14px;
            transition: all 0.3s; 
        }
        .btn-back:hover { background: var(--secondary-hover); }

        .form-box { 
            background: #f1f5f9; 
            padding: 25px; 
            border-radius: 10px; 
            border: 1px solid var(--border-color); 
            margin-bottom: 35px; 
        }
        
        .form-box h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; }

        .grid-form {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px;
        }

        .form-group label { 
            font-weight: 600; 
            display: block; 
            margin-bottom: 8px; 
            font-size: 13.5px; 
            color: var(--text-main);
        }

        .form-control { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 14.5px; 
            font-family: inherit;
            color: var(--text-main);
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            color: white; 
            transition: 0.3s; 
            font-size: 14.5px; 
        }
        .btn-primary { background: var(--primary); } 
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px);}
        .btn-secondary { background: #94a3b8; text-decoration: none; padding: 12px 24px; color: white; display: inline-block;} 
        .btn-secondary:hover { background: #64748b; }
        
        /* Table Styles */
        .table-responsive { overflow-x: auto; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th, td { 
            border-bottom: 1px solid var(--border-color); 
            padding: 16px 12px; 
            text-align: left; 
            font-size: 14px; 
        }
        th { 
            background: #f8fafc; 
            color: var(--text-muted); 
            text-align: center; 
            font-weight: 600; 
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        tr:hover { background-color: #f1f5f9; }
        
        .mapel-text { color: var(--secondary); font-weight: 600; }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }
        .badge-aktif { background: #d1fae5; color: #065f46; }
        .badge-tutup { background: #fee2e2; color: #991b1b; }
        .badge-belum { background: #fef3c7; color: #92400e; }
        .badge-kelas { background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 6px; font-weight: bold; }

        /* Action Buttons */
        .action-btns { display: flex; gap: 8px; justify-content: center; }
        .btn-sm { padding: 6px 12px; border-radius: 4px; font-size: 12px; text-decoration: none; color: white; font-weight: 600;}
        .btn-edit { background: var(--secondary); }
        .btn-edit:hover { background: var(--secondary-hover); }
        .btn-delete { background: var(--danger); }
        .btn-delete:hover { background: var(--danger-hover); }

        .footer-wrapper { margin-top: 40px; text-align: center; }
    </style>
</head>
<body>
    <?php require "navbar.php" ?>
    <div class="container">
        
        <div class="header-title">
            
            <h2>📅 Kelola Jadwal Ujian</h2>
            <a href="index.php" class="btn-back">⬅️ Kembali ke Dashboard</a>
        </div>

        <div class="form-box">
            <form method="POST" action="jadwal.php">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id_jadwal" value="<?php echo $edit_data['id']; ?>">
                    <h3 style="color: var(--secondary);">✏️ Edit Jadwal Ujian</h3>
                <?php else: ?>
                    <h3 style="color: var(--primary);">➕ Tambah Jadwal Baru</h3>
                <?php endif; ?>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Nama Ujian</label>
                        <input type="text" name="nama_ujian" class="form-control" value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_ujian']) : ''; ?>" placeholder="Contoh: PAS Semester 1" required>
                    </div>
                    <div class="form-group">
                        <label>Mata Pelajaran (Dari Bank Soal)</label>
                        <select name="mata_pelajaran" class="form-control" required>
                            <option value="" disabled selected>-- Pilih Mata Pelajaran --</option>
                            <?php foreach($list_mapel as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['mata_pelajaran']); ?>" <?php echo ($edit_data && $edit_data['mata_pelajaran'] == $m['mata_pelajaran']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['mata_pelajaran']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- INPUT BARU: DROPDOWN PILIHAN KELAS -->
                    <div class="form-group">
                        <label>Tingkatan Kelas</label>
                        <select name="kelas" class="form-control" required>
                            <option value="" disabled selected>-- Pilih Kelas --</option>
                            <option value="X" <?php echo ($edit_data && $edit_data['kelas'] == 'X') ? 'selected' : ''; ?>>Kelas X</option>
                            <option value="XI" <?php echo ($edit_data && $edit_data['kelas'] == 'XI') ? 'selected' : ''; ?>>Kelas XI</option>
                            <option value="XII" <?php echo ($edit_data && $edit_data['kelas'] == 'XII') ? 'selected' : ''; ?>>Kelas XII</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Waktu Mulai Ujian</label>
                        <input type="datetime-local" name="waktu_mulai" class="form-control" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_mulai'])) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Waktu Selesai Ujian</label>
                        <input type="datetime-local" name="waktu_selesai" class="form-control" value="<?php echo $edit_data ? date('Y-m-d\TH:i', strtotime($edit_data['waktu_selesai'])) : ''; ?>" required>
                    </div>
                </div>

                <div style="margin-top: 25px;">
                    <?php if($edit_data): ?>
                        <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="jadwal.php" class="btn btn-secondary">Batal</a>
                    <?php else: ?>
                        <button type="submit" name="tambah" class="btn btn-primary">Simpan Jadwal</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Ujian</th>
                        <th>Mata Pelajaran</th>
                        <th width="10%">Kelas</th> <!-- KOLOM BARU -->
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Status</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $sekarang = date('Y-m-d H:i:s');
                    foreach($semua_jadwal as $j): 
                        
                        // Deteksi Status Ujian menggunakan Badge Modern
                        if ($sekarang < $j['waktu_mulai']) {
                            $status = "<span class='badge badge-belum'>⏳ Belum Mulai</span>";
                        } elseif ($sekarang >= $j['waktu_mulai'] && $sekarang <= $j['waktu_selesai']) {
                            $status = "<span class='badge badge-aktif'>🟢 Aktif</span>";
                        } else {
                            $status = "<span class='badge badge-tutup'>🔴 Selesai</span>";
                        }
                    ?>
                    <tr>
                        <td align="center"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($j['nama_ujian']); ?></td>
                        <td><span class="mapel-text"><?php echo htmlspecialchars($j['mata_pelajaran']); ?></span></td>
                        <!-- MENAMPILKAN KELAS -->
                        <td align="center"><span class="badge-kelas"><?php echo htmlspecialchars($j['kelas'] ?? '-'); ?></span></td>
                        <td align="center"><?php echo date('d-M-Y H:i', strtotime($j['waktu_mulai'])); ?></td>
                        <td align="center"><?php echo date('d-M-Y H:i', strtotime($j['waktu_selesai'])); ?></td>
                        <td align="center"><?php echo $status; ?></td>
                        <td align="center">
                            <div class="action-btns">
                                <a href="jadwal.php?edit_id=<?php echo $j['id']; ?>" class="btn-sm btn-edit">Edit</a>
                                <a href="jadwal.php?hapus=<?php echo $j['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal mapel ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($semua_jadwal)): ?>
                    <tr>
                        <td colspan="8" align="center" style="padding: 30px; color: var(--text-muted);">
                            <em>Belum ada jadwal ujian yang ditambahkan.</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer-wrapper">
            <?php include "footer.php"; ?>
        </div>
    </div>
</body>
</html>