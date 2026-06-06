<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas = $_POST['kelas'];
    
    // ENCODING KHUSUS: Menggunakan ENT_QUOTES dan UTF-8
    // $deskripsi = nl2br(htmlspecialchars($_POST['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8'));
    // $pertanyaan = nl2br(htmlspecialchars($_POST['pertanyaan'] ?? '', ENT_QUOTES, 'UTF-8'));
    // GANTI DARI:


// MENJADI (Hapus htmlspecialchars agar tag HTML tetap tersimpan):
$deskripsi = nl2br($_POST['deskripsi'] ?? '');
$pertanyaan = nl2br($_POST['pertanyaan'] ?? '');
    
    // Default fallback ke string kosong ('') jika teks tidak diisi
    $opsi_a = htmlspecialchars($_POST['opsi_a'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_b = htmlspecialchars($_POST['opsi_b'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_c = htmlspecialchars($_POST['opsi_c'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_d = htmlspecialchars($_POST['opsi_d'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_e = htmlspecialchars($_POST['opsi_e'] ?? '', ENT_QUOTES, 'UTF-8');
    $kunci = $_POST['kunci_jawaban'];
    
    $submit_action = $_POST['submit_action'] ?? 'kembali';
    
    $gambar = "";
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = "soal_utama_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target_dir . $gambar);
    }

    $gbr_opsi = ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => ''];
    foreach (['a', 'b', 'c', 'd', 'e'] as $opt) {
        if (isset($_FILES['gambar_'.$opt]) && $_FILES['gambar_'.$opt]['error'] == 0) {
            $ext = pathinfo($_FILES['gambar_'.$opt]['name'], PATHINFO_EXTENSION);
            $nama_file = "opsi_{$opt}_" . time() . "_" . rand(10,99) . "." . $ext;
            move_uploaded_file($_FILES['gambar_'.$opt]['tmp_name'], $target_dir . $nama_file);
            $gbr_opsi[$opt] = $nama_file;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO soal (mata_pelajaran, kelas, deskripsi, gambar, pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $mata_pelajaran,
            $kelas,
            $deskripsi,
            $gambar,
            $pertanyaan,
            $opsi_a,
            $gbr_opsi['a'],
            $opsi_b,
            $gbr_opsi['b'],
            $opsi_c,
            $gbr_opsi['c'],
            $opsi_d,
            $gbr_opsi['d'],
            $opsi_e,
            $gbr_opsi['e'],
            $kunci
        ]);
        
        if ($submit_action == 'tambah_lagi') {
            echo "<script>alert('Soal berhasil disimpan! Silakan input soal berikutnya.'); window.location='tambah_soal.php?mapel=".urlencode($mata_pelajaran)."';</script>";
        } else {
            echo "<script>alert('Soal berhasil disimpan!'); window.location='soal.php';</script>";
        }
        
    } catch (Exception $e) {
        die("Gagal menyimpan soal: " . $e->getMessage());
    }
}

$selected_mapel = $_GET['mapel'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Soal Manual</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .card { background: white; padding: 40px; border-radius: 8px; max-width: 900px; margin: auto; border-top: 5px solid #007bff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #2c3e50; font-size: 24px; border-bottom: 2px solid #f0f2f5; padding-bottom: 15px; margin-bottom: 25px; }
        .form-group { margin-bottom: 25px; }
        .opsi-box { background: #f8f9fa; padding: 20px; border-left: 5px solid #28a745; margin-bottom: 15px; border-radius: 0 8px 8px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        label { font-weight: 600; display: block; margin-bottom: 8px; color: #495057; font-size: 14.5px; }
        input[type="text"], select { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 15px; transition: border-color 0.15s ease-in-out; }
        textarea { width: 100%; padding: 15px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 15px; line-height: 1.6; min-height: 140px; resize: vertical; background-color: #fff; transition: border-color 0.15s ease-in-out; }
        input[type="text"]:focus, select:focus, textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        input[type="file"] { display: block; margin-top: 5px; font-size: 14px; padding: 8px; border: 1px dashed #ced4da; width: 100%; border-radius: 6px; background: #fdfdfd; cursor: pointer; }
        input[type="file"]:hover { border-color: #007bff; background: #f1f8ff; }
        .preview-container { display: none; margin-top: 15px; text-align: center; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e9ecef; }
        .preview-img { max-width: 100%; max-height: 250px; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .btn-group { display: flex; gap: 12px; margin-top: 30px; border-top: 2px solid #f0f2f5; padding-top: 20px; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.2s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; font-size: 15px; }
        .btn:hover, .btn-batal:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); opacity: 0.9; }
        .hint-text { font-size: 13px; color: #6c757d; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>✨ Tambah Soal Manual</h2>
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Mata Pelajaran (*Wajib):</label>
                <select name="mata_pelajaran" required>
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <option value="Bahasa Indonesia" <?php if($selected_mapel == 'Bahasa Indonesia') echo 'selected'; ?>>Bahasa Indonesia</option>
                    <option value="MTK" <?php if($selected_mapel == 'MTK') echo 'selected'; ?>>MTK</option>
                    <option value="Bahasa Inggris" <?php if($selected_mapel == 'Bahasa Inggris') echo 'selected'; ?>>Bahasa Inggris</option>
                    <option value="PAI" <?php if($selected_mapel == 'PAI') echo 'selected'; ?>>PAI</option>
                </select>
                <label>Kelas(*Wajib):</label>

                <select name="kelas" required>
                    <option value="">Pilih Kelas</option>
                    <option value="X">X</option>
                    <option value="XI">XI</option>
                </select>
            </div>
            

            <div class="form-group">
                <label>1. Deskripsi / Pernyataan (Opsional):</label>
                <span class="hint-text">Aman untuk Copy-Paste dari Word. Tekan Enter untuk baris baru.</span>
                <textarea name="deskripsi" placeholder="Ketik atau paste deskripsi soal di sini..."></textarea>
            </div>
            
            <div class="form-group">
                <label>2. Upload Gambar Soal Utama (Opsional):</label>
                <span class="hint-text">Gunakan gambar untuk teks hitungan/rumus yang rumit.</span>
                <input type="file" name="gambar" accept="image/*" onchange="previewImage(event, 'preview_utama')">
                <div id="preview_utama_container" class="preview-container">
                    <img id="preview_utama" class="preview-img" src="" alt="Pratinjau Gambar Utama">
                </div>
            </div>

            <div class="form-group">
                <label>3. Soal / Pertanyaan (*Wajib):</label>
                <small class="hint-text">
                    Tips Format: 
                    <i>&lt;i&gt;Teks Miring&lt;/i&gt;</i> | 
                    Pangkat: <i>&lt;sup&gt;2&lt;/sup&gt;</i> (Contoh: x<sup>2</sup>)
                </small>
                <textarea name="pertanyaan" required placeholder="Ketik pertanyaan..."></textarea>
            </div>

            <hr style="margin: 40px 0 30px; border: 1px solid #e9ecef;">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">Pilihan Ganda</h3>
            <p style="font-size: 14px; color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 6px;">
                💡 <b>Tips Rumus Matematika:</b> Jika opsi berupa rumus pecahan/matriks, gunakan Snipping Tool (<i>Windows + Shift + S</i>) di Word, lalu upload sebagai gambar. Kosongkan saja Teks Opsinya.
            </p>

            <?php foreach(['A', 'B', 'C', 'D', 'E'] as $opt): $low = strtolower($opt); ?>
            <div class="opsi-box">
                <!-- REQUIRED DIHILANGKAN DI SINI -->
                <label style="color: #28a745; font-size: 16px;">Opsi <?php echo $opt; ?> Teks (Opsional jika pakai gambar):</label>
                <input type="text" name="opsi_<?php echo $low; ?>" autocomplete="off" placeholder="Ketik teks opsi atau biarkan kosong jika upload gambar ->">
                
                <label style="font-weight: 600; font-size: 13.5px; margin-top: 15px; color: #6c757d;">Upload Gambar Opsi <?php echo $opt; ?>:</label>
                <input type="file" name="gambar_<?php echo $low; ?>" accept="image/*" onchange="previewImage(event, 'preview_<?php echo $low; ?>')">
                <div id="preview_<?php echo $low; ?>_container" class="preview-container">
                    <img id="preview_<?php echo $low; ?>" class="preview-img" src="" alt="Pratinjau Opsi <?php echo $opt; ?>">
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-group" style="background: #e9ecef; padding: 20px; border-radius: 8px; margin-top: 25px;">
                <label style="font-size: 16px;">Kunci Jawaban yang Benar:</label>
                <select name="kunci_jawaban" required style="max-width: 200px; font-weight: bold; border: 2px solid #007bff;">
                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option>
                </select>
            </div>

            <div class="btn-group">
                <button type="submit" name="submit_action" value="tambah_lagi" class="btn btn-success">💾 Simpan & Tambah Lagi</button>
                <button type="submit" name="submit_action" value="kembali" class="btn btn-primary">💾 Simpan & Kembali</button>
                <a href="soal.php" class="btn-batal">❌ Batal</a>
            </div>
        </form>
    </div>

    <script>
        function previewImage(event, previewId) {
            var input = event.target;
            var reader = new FileReader();
            var containerId = previewId + '_container';
            
            reader.onload = function(){
                var imgElement = document.getElementById(previewId);
                imgElement.src = reader.result;
                document.getElementById(containerId).style.display = 'block';
            };
            
            if(input.files && input.files[0]) {
                reader.readAsDataURL(input.files[0]);
            } else {
                document.getElementById(containerId).style.display = 'none';
                document.getElementById(previewId).src = "";
            }
        }
    </script>
</body>
</html>