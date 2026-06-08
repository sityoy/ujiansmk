<?php
// session_start();
require 'cek_admin.php';

// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login.php");
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas = $_POST['kelas'];
    
    // Simpan teks apa adanya (termasuk tag HTML dari custom editor kita) + nl2br untuk enter
    $deskripsi = nl2br($_POST['deskripsi'] ?? '');
    $pertanyaan = nl2br($_POST['pertanyaan'] ?? '');
    
    // Opsi juga disimpan tanpa htmlspecialchars agar tag pangkat/miring (sup, i) tetap bekerja
    $opsi_a = nl2br($_POST['opsi_a'] ?? '');
    $opsi_b = nl2br($_POST['opsi_b'] ?? '');
    $opsi_c = nl2br($_POST['opsi_c'] ?? '');
    $opsi_d = nl2br($_POST['opsi_d'] ?? '');
    $opsi_e = nl2br($_POST['opsi_e'] ?? '');
    $kunci = $_POST['kunci_jawaban'];
    
    $submit_action = $_POST['submit_action'] ?? 'kembali';
    
    $gambar = "";
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    // Proses Upload Gambar Utama
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = "soal_utama_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target_dir . $gambar);
    }

    // Proses Upload Gambar Opsi A-E
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
            $mata_pelajaran, $kelas, $deskripsi, $gambar, $pertanyaan, 
            $opsi_a, $gbr_opsi['a'], $opsi_b, $gbr_opsi['b'], $opsi_c, $gbr_opsi['c'], 
            $opsi_d, $gbr_opsi['d'], $opsi_e, $gbr_opsi['e'], $kunci
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
        :root { --primary: #007bff; --success: #28a745; --danger: #dc3545; --bg: #f0f2f5; --border: #ced4da; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .card { background: white; padding: 40px; border-radius: 8px; max-width: 950px; margin: auto; border-top: 5px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #2c3e50; font-size: 24px; border-bottom: 2px solid var(--bg); padding-bottom: 15px; margin-bottom: 25px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 25px; }
        label { font-weight: 600; display: block; margin-bottom: 8px; color: #495057; font-size: 14.5px; }
        
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 15px; font-family: inherit; transition: 0.2s; }
        input[type="text"]:focus, select:focus, textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        textarea { min-height: 120px; resize: vertical; border-top-left-radius: 0; border-top-right-radius: 0; border-top: none; }
        
        /* === CUSTOM TOOLBAR STYLE === */
        .editor-wrapper { border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
        .editor-wrapper:focus-within { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .editor-toolbar { background: #f8f9fa; padding: 8px 10px; display: flex; gap: 5px; border-bottom: 1px solid var(--border); }
        .toolbar-btn { background: white; border: 1px solid var(--border); border-radius: 4px; padding: 5px 10px; cursor: pointer; font-size: 14px; font-weight: bold; color: #333; transition: 0.2s; }
        .toolbar-btn:hover { background: #e2e6ea; border-color: #dae0e5; }

        /* === DRAG AND DROP ZONE STYLE === */
        .drop-zone { border: 2px dashed #007bff; background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; position: relative; transition: 0.3s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px; overflow: hidden; }
        .drop-zone:hover, .drop-zone.dragover { background: #e2eafc; border-color: #0056b3; }
        .drop-zone input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .dz-text { color: #6c757d; font-size: 14px; pointer-events: none; }
        .dz-text b { color: var(--primary); }
        .dz-preview { max-width: 100%; max-height: 180px; border-radius: 6px; display: none; object-fit: contain; pointer-events: none; z-index: 2; }
        .btn-hapus-gambar { display: none; margin-top: 10px; background: var(--danger); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; z-index: 3; }

        .opsi-box { background: #fff; padding: 20px; border: 1px solid var(--border); border-left: 5px solid var(--success); margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .opsi-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
        
        .btn-group { display: flex; gap: 12px; margin-top: 30px; border-top: 2px solid var(--bg); padding-top: 20px; justify-content: center;}
        .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; }
        .btn:hover, .btn-batal:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); opacity: 0.9; }
        .hint-text { font-size: 13px; color: #6c757d; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>✨ Tambah Soal Manual</h2>
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-row">
                <div>
                    <label>Mata Pelajaran (*Wajib):</label>
                    <select name="mata_pelajaran" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <option value="Bahasa Indonesia" <?php if($selected_mapel == 'Bahasa Indonesia') echo 'selected'; ?>>Bahasa Indonesia</option>
                        <option value="MTK" <?php if($selected_mapel == 'MTK') echo 'selected'; ?>>MTK</option>
                        <option value="Bahasa Inggris" <?php if($selected_mapel == 'Bahasa Inggris') echo 'selected'; ?>>Bahasa Inggris</option>
                        <option value="PAI" <?php if($selected_mapel == 'PAI') echo 'selected'; ?>>PAI</option>
                    </select>
                </div>
                <div>
                    <label>Kelas (*Wajib):</label>
                    <select name="kelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <option value="X">X</option>
                        <option value="XI">XI</option>
                        <option value="XII">XII</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>1. Deskripsi / Pernyataan (Opsional):</label>
                <div class="editor-wrapper">
                    <div class="editor-toolbar">
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_deskripsi', 'b')" title="Bold"><b>B</b></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_deskripsi', 'i')" title="Italic"><i>I</i></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_deskripsi', 'sup')" title="Superscript">X<sup>2</sup></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_deskripsi', 'sub')" title="Subscript">X<sub>2</sub></button>
                    </div>
                    <textarea id="ta_deskripsi" name="deskripsi" placeholder="Ketik deskripsi di sini..."></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label>2. Upload Gambar Soal Utama (Opsional):</label>
                <div class="drop-zone" id="dz_utama">
                    <span class="dz-text">📁 Drag & Drop Gambar di Sini<br>atau <b>Klik untuk Mencari</b></span>
                    <input type="file" name="gambar" id="input_utama" accept="image/*" onchange="previewImage(this, 'prev_utama', 'dz_utama')">
                    <img id="prev_utama" class="dz-preview" src="">
                    <button type="button" class="btn-hapus-gambar" onclick="hapusGambar('input_utama', 'prev_utama', 'dz_utama', event)">🗑️ Hapus Gambar</button>
                </div>
            </div>

            <div class="form-group">
                <label>3. Soal / Pertanyaan (*Wajib):</label>
                <div class="editor-wrapper">
                    <div class="editor-toolbar">
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_pertanyaan', 'b')"><b>B</b></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_pertanyaan', 'i')"><i>I</i></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_pertanyaan', 'sup')">X<sup>2</sup></button>
                        <button type="button" class="toolbar-btn" onclick="formatText('ta_pertanyaan', 'sub')">X<sub>2</sub></button>
                    </div>
                    <textarea id="ta_pertanyaan" name="pertanyaan" required placeholder="Ketik pertanyaan utama..."></textarea>
                </div>
            </div>

            <hr style="margin: 40px 0 20px; border: 1px solid #e9ecef;">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">Pilihan Ganda</h3>
            
            <?php foreach(['A', 'B', 'C', 'D', 'E'] as $opt): $low = strtolower($opt); ?>
            <div class="opsi-box">
                <label style="color: #28a745; font-size: 16px; margin-bottom: 10px;">Opsi <?php echo $opt; ?></label>
                <div class="opsi-layout">
                    <div>
                        <span class="hint-text mb-2" style="margin-top:0; margin-bottom:5px;">Teks Opsi (Opsional):</span>
                        <div class="editor-wrapper">
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'b')"><b>B</b></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'i')"><i>I</i></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'sup')">X<sup>2</sup></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'sub')">X<sub>2</sub></button>
                            </div>
                            <textarea id="ta_opsi_<?php echo $low; ?>" name="opsi_<?php echo $low; ?>" placeholder="Ketik teks opsi..." style="min-height: 80px;"></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <span class="hint-text mb-2" style="margin-top:0; margin-bottom:5px;">Atau Upload Gambar:</span>
                        <div class="drop-zone" id="dz_<?php echo $low; ?>" style="min-height: 110px; padding: 10px;">
                            <span class="dz-text" style="font-size: 12px;">📁 Drag & Drop<br>atau <b>Klik Disini</b></span>
                            <input type="file" name="gambar_<?php echo $low; ?>" id="input_<?php echo $low; ?>" accept="image/*" onchange="previewImage(this, 'prev_<?php echo $low; ?>', 'dz_<?php echo $low; ?>')">
                            <img id="prev_<?php echo $low; ?>" class="dz-preview" src="">
                            <button type="button" class="btn-hapus-gambar" onclick="hapusGambar('input_<?php echo $low; ?>', 'prev_<?php echo $low; ?>', 'dz_<?php echo $low; ?>', event)">🗑️ Batal</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-group" style="background: #e2eafc; padding: 25px; border-radius: 8px; margin-top: 30px; text-align: center; border: 2px dashed var(--primary);">
                <label style="font-size: 18px; color: var(--primary);">🎯 Kunci Jawaban yang Benar:</label>
                <select name="kunci_jawaban" required style="max-width: 200px; font-weight: bold; border: 2px solid var(--primary); text-align: center; font-size: 16px; margin: 0 auto; display: block;">
                    <option value="" disabled selected>-- Pilih Kunci --</option>
                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option>
                </select>
            </div>

            <div class="btn-group">
                <a href="soal.php" class="btn btn-batal">❌ Batal</a>
                <button type="submit" name="submit_action" value="tambah_lagi" class="btn btn-success">➕ Simpan & Tambah Lagi</button>
                <button type="submit" name="submit_action" value="kembali" class="btn btn-primary">💾 Simpan & Selesai</button>
            </div>
        </form>
    </div>

    <script>
        function formatText(textareaId, tag) {
            const textarea = document.getElementById(textareaId);
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            // Format tag (contoh: <b>teks</b>)
            const replacement = `<${tag}>${selectedText}</${tag}>`;
            
            // Ganti teks dalam textarea
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            
            // Pindahkan kursor ke tengah tag jika tidak ada teks yang diblok
            if (start === end) {
                textarea.selectionStart = textarea.selectionEnd = start + tag.length + 2;
            }
            textarea.focus();
        }
    </script>

    <script>
        function previewImage(input, previewId, dropZoneId) {
            const preview = document.getElementById(previewId);
            const dropZone = document.getElementById(dropZoneId);
            const textSpan = dropZone.querySelector('.dz-text');
            const btnHapus = dropZone.querySelector('.btn-hapus-gambar');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    textSpan.style.display = 'none';
                    btnHapus.style.display = 'inline-block';
                    dropZone.style.borderStyle = 'solid'; // Ubah border jadi solid saat ada gambar
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function hapusGambar(inputId, previewId, dropZoneId, event) {
            event.preventDefault(); // Mencegah form ter-submit atau file input terbuka
            
            document.getElementById(inputId).value = ""; // Kosongkan file input
            document.getElementById(previewId).src = ""; // Kosongkan src gambar
            document.getElementById(previewId).style.display = 'none'; // Sembunyikan gambar
            
            const dropZone = document.getElementById(dropZoneId);
            dropZone.querySelector('.dz-text').style.display = 'block'; // Tampilkan teks Drag & Drop lagi
            dropZone.querySelector('.btn-hapus-gambar').style.display = 'none'; // Sembunyikan tombol hapus
            dropZone.style.borderStyle = 'dashed'; // Kembalikan border dashed
        }

        // Event Listener untuk animasi seret & lepas (Drag & Drop)
        document.querySelectorAll('.drop-zone').forEach(dropZone => {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                const fileInput = dropZone.querySelector('input[type="file"]');
                const previewId = dropZone.querySelector('img').id;
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    previewImage(fileInput, previewId, dropZone.id);
                }
            });
        });
    </script>
</body>
</html>