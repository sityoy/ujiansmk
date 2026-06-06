<?php
// session_start();
require 'cek_admin.php';

// Pastikan hanya admin yang bisa akses (Sesuaikan dengan session admin Bapak)
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit;
// }

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas          = strtoupper(trim($_POST['kelas']));
    $deskripsi      = $_POST['deskripsi'] ?? '';
    $pertanyaan     = $_POST['pertanyaan'];
    $kunci_jawaban  = strtoupper(trim($_POST['kunci_jawaban']));

    $opsi_a = $_POST['opsi_a'];
    $opsi_b = $_POST['opsi_b'];
    $opsi_c = $_POST['opsi_c'];
    $opsi_d = $_POST['opsi_d'];
    $opsi_e = $_POST['opsi_e'];

    // Fungsi canggih untuk menangani Upload Gambar dengan aman
    function uploadGambar($input_name) {
        $folder_upload = '../uploads/';
        
        // Buat folder otomatis jika belum ada di VPS
        if (!file_exists($folder_upload)) {
            @mkdir($folder_upload, 0755, true);
        }

        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES[$input_name]['tmp_name'];
            $file_name = $_FILES[$input_name]['name'];
            $file_size = $_FILES[$input_name]['size'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            // Validasi Ekstensi dan Ukuran (Maks 2MB per gambar)
            if (in_array($file_ext, $allowed_ext) && $file_size <= 2097152) {
                // Beri nama unik agar tidak saling menimpa
                $new_file_name = $input_name . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                $tujuan = $folder_upload . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $tujuan)) {
                    return $new_file_name;
                }
            }
        }
        return null; // Kembalikan null jika tidak ada upload atau gagal
    }

    // Proses semua upload gambar
    $gambar_soal = uploadGambar('gambar');
    $gambar_a    = uploadGambar('gambar_a');
    $gambar_b    = uploadGambar('gambar_b');
    $gambar_c    = uploadGambar('gambar_c');
    $gambar_d    = uploadGambar('gambar_d');
    $gambar_e    = uploadGambar('gambar_e');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO soal 
            (mata_pelajaran, kelas, deskripsi, pertanyaan, gambar, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $mata_pelajaran, $kelas, $deskripsi, $pertanyaan, $gambar_soal, 
            $opsi_a, $gambar_a, $opsi_b, $gambar_b, $opsi_c, $gambar_c, 
            $opsi_d, $gambar_d, $opsi_e, $gambar_e, $kunci_jawaban
        ]);

        $pesan = "<div class='alert success'>✅ Soal berhasil ditambahkan ke database!</div>";
    } catch (Exception $e) {
        $pesan = "<div class='alert error'>❌ Gagal menyimpan soal: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Soal - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-top: 5px solid #007bff; }
        h2 { margin-top: 0; color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 8px; font-size: 14px; }
        input[type="text"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-family: Arial; }
        textarea { resize: vertical; min-height: 100px; }
        .opsi-box { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; display: flex; gap: 15px; align-items: flex-start; }
        .opsi-huruf { font-size: 20px; font-weight: bold; color: #007bff; padding-top: 10px; }
        .opsi-input { flex-grow: 1; }
        input[type="file"] { font-size: 13px; color: #666; margin-top: 8px; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 15px 25px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; width: 100%; margin-top: 20px; transition: 0.3s; }
        .btn-submit:hover { background: #218838; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h2>✍️ Input Soal Baru</h2>
    <a href="soal.php" style="display:inline-block; margin-bottom: 20px; color: #666; text-decoration:none;">⬅️ Kembali ke Bank Soal</a>
    
    <?php echo $pesan; ?>

    <!-- Wajib pakai enctype="multipart/form-data" untuk kirim gambar -->
    <form action="" method="POST" enctype="multipart/form-data">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Mata Pelajaran</label>
                <input type="text" name="mata_pelajaran" placeholder="Contoh: Bahasa Indonesia" required>
            </div>
            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas" required>
                    <option value="">-- Pilih Kelas --</option>
                    <option value="X">Kelas X</option>
                    <option value="XI">Kelas XI</option>
                    <option value="XII">Kelas XII</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Deskripsi/Bacaan (Opsional)</label>
            <textarea name="deskripsi" placeholder="Isi jika ada wacana/teks bacaan sebelum pertanyaan..."></textarea>
        </div>

        <div class="form-group">
            <label>Pertanyaan Soal *</label>
            <textarea name="pertanyaan" placeholder="Ketik pertanyaan di sini..." required></textarea>
            <input type="file" name="gambar" accept="image/*">
            <small style="color:#777; display:block;">*Opsional: Upload gambar untuk soal (Maks 2MB, format JPG/PNG)</small>
        </div>

        <h3 style="margin-top:30px; margin-bottom:15px; color:#555;">Pilihan Ganda</h3>

        <?php 
        $huruf = ['A', 'B', 'C', 'D', 'E'];
        foreach($huruf as $h): 
        ?>
        <div class="opsi-box">
            <div class="opsi-huruf"><?php echo $h; ?></div>
            <div class="opsi-input">
                <textarea name="opsi_<?php echo strtolower($h); ?>" placeholder="Teks untuk opsi <?php echo $h; ?>..." style="min-height:60px;"></textarea>
                <input type="file" name="gambar_<?php echo strtolower($h); ?>" accept="image/*">
            </div>
        </div>
        <?php endforeach; ?>

        <div class="form-group" style="margin-top: 30px; background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeeba;">
            <label style="color: #856404; font-size: 16px;">Kunci Jawaban Benar *</label>
            <select name="kunci_jawaban" required style="font-size: 16px; font-weight: bold; padding: 12px;">
                <option value="">-- Pilih Kunci Jawaban --</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
                <option value="E">E</option>
            </select>
        </div>

        <button type="submit" class="btn-submit">💾 Simpan Soal</button>
    </form>
</div>

</body>
</html>
