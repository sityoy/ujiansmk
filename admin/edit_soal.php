<?php
// session_start();
require 'cek_admin.php';

function hapus_file_lama($nama_file, $dir) {
    if (!empty($nama_file) && file_exists($dir . $nama_file)) unlink($dir . $nama_file);
}

// if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
//     header("Location: soal.php");
//     exit;
// }

$stmtMapel = $pdo->query("SELECT DISTINCT mata_pelajaran FROM soal ORDER BY mata_pelajaran");
$listMapel = $stmtMapel->fetchAll();

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ?");
$stmt->execute([$id]);
$soal = $stmt->fetch();

if (!$soal) die("Soal tidak ditemukan!");

// Fungsi membersihkan teks untuk textarea
function decode_for_edit($text){
    $text = str_ireplace(['<br />','<br>','<br/>'], "\n", $text ?? '');
    return htmlspecialchars_decode($text, ENT_QUOTES);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas = $_POST['kelas'];
    
    // Simpan teks apa adanya (termasuk tag HTML dari custom editor kita) + nl2br untuk enter
    $deskripsi = nl2br($_POST['deskripsi'] ?? '');
    $pertanyaan = nl2br($_POST['pertanyaan'] ?? '');
    
    // Hapus htmlspecialchars pada opsi agar tag pangkat/miring tetap berfungsi
    $opsi_a = nl2br($_POST['opsi_a'] ?? '');
    $opsi_b = nl2br($_POST['opsi_b'] ?? '');
    $opsi_c = nl2br($_POST['opsi_c'] ?? '');
    $opsi_d = nl2br($_POST['opsi_d'] ?? '');
    $opsi_e = nl2br($_POST['opsi_e'] ?? '');
    $kunci = $_POST['kunci_jawaban'];
    
    $gambar = $soal['gambar'];
    $gbr = ['a' => $soal['gambar_a'], 'b' => $soal['gambar_b'], 'c' => $soal['gambar_c'], 'd' => $soal['gambar_d'], 'e' => $soal['gambar_e']];
    $target_dir = "../uploads/";
    
    // Hapus gambar utama
    if(isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == 'yes'){
        hapus_file_lama($gambar, $target_dir);
        $gambar = '';
    }

    // Update gambar utama
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        hapus_file_lama($gambar, $target_dir);
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = "soal_utama_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target_dir . $gambar);
    }
    
    // Cek hapus gambar opsi
    foreach(['a','b','c','d','e'] as $opt){
        if(isset($_POST['hapus_gambar_'.$opt]) && $_POST['hapus_gambar_'.$opt] == 'yes'){
            hapus_file_lama($gbr[$opt], $target_dir);
            $gbr[$opt] = '';
        }
    }

    // Update gambar opsi
    foreach (['a', 'b', 'c', 'd', 'e'] as $opt) {
        if (isset($_FILES['gambar_'.$opt]) && $_FILES['gambar_'.$opt]['error'] == 0) {
            hapus_file_lama($gbr[$opt], $target_dir);
            $ext = pathinfo($_FILES['gambar_'.$opt]['name'], PATHINFO_EXTENSION);
            $nama_file = "opsi_{$opt}_" . time() . "_" . rand(10,99) . "." . $ext;
            move_uploaded_file($_FILES['gambar_'.$opt]['tmp_name'], $target_dir . $nama_file);
            $gbr[$opt] = $nama_file;
        }
    }

    $stmtUpdate = $pdo->prepare("UPDATE soal SET mata_pelajaran=?, kelas=?, deskripsi=?, gambar=?, pertanyaan=?, opsi_a=?, gambar_a=?, opsi_b=?, gambar_b=?, opsi_c=?, gambar_c=?, opsi_d=?, gambar_d=?, opsi_e=?, gambar_e=?, kunci_jawaban=? WHERE id=?");
    $stmtUpdate->execute([$mata_pelajaran, $kelas, $deskripsi, $gambar, $pertanyaan, $opsi_a, $gbr['a'], $opsi_b, $gbr['b'], $opsi_c, $gbr['c'], $opsi_d, $gbr['d'], $opsi_e, $gbr['e'], $kunci, $id]);
    
    echo "<script>alert('Soal berhasil diupdate!'); window.location='soal.php';</script>";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Soal</title>
    <style>
        :root { --primary: #17a2b8; --success: #28a745; --danger: #dc3545; --bg: #f0f2f5; --border: #ced4da; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .card { background: white; padding: 40px; border-radius: 8px; max-width: 950px; margin: auto; border-top: 5px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #2c3e50; font-size: 24px; border-bottom: 2px solid var(--bg); padding-bottom: 15px; margin-bottom: 25px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 25px; }
        label { font-weight: 600; display: block; margin-bottom: 8px; color: #495057; font-size: 14.5px; }
        
        select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 15px; font-family: inherit; transition: 0.2s; }
        select:focus, textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        textarea { min-height: 120px; resize: vertical; border-top-left-radius: 0; border-top-right-radius: 0; border-top: none; }
        
        /* === CUSTOM TOOLBAR STYLE === */
        .editor-wrapper { border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
        .editor-wrapper:focus-within { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .editor-toolbar { background: #f8f9fa; padding: 8px 10px; display: flex; gap: 5px; border-bottom: 1px solid var(--border); }
        .toolbar-btn { background: white; border: 1px solid var(--border); border-radius: 4px; padding: 5px 10px; cursor: pointer; font-size: 14px; font-weight: bold; color: #333; transition: 0.2s; }
        .toolbar-btn:hover { background: #e2e6ea; border-color: #dae0e5; }

        /* === DRAG AND DROP ZONE STYLE === */
        .drop-zone { border: 2px dashed var(--primary); background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; position: relative; transition: 0.3s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px; overflow: hidden; }
        .drop-zone:hover, .drop-zone.dragover { background: #e2eafc; border-color: #117a8b; }
        .drop-zone input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .dz-text { color: #6c757d; font-size: 14px; pointer-events: none; }
        .dz-text b { color: var(--primary); }
        .dz-preview { max-width: 100%; max-height: 180px; border-radius: 6px; display: none; object-fit: contain; pointer-events: none; z-index: 2; margin-bottom: 10px; }
        .btn-hapus-gambar { display: none; margin-top: 10px; background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; z-index: 3; font-weight: bold; }

        .opsi-box { background: #fff; padding: 20px; border: 1px solid var(--border); border-left: 5px solid var(--success); margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .opsi-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
        
        .btn-group { display: flex; gap: 12px; margin-top: 30px; border-top: 2px solid var(--bg); padding-top: 20px; justify-content: center;}
        .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; font-size: 15px; transition: all 0.2s; }
        .btn:hover, .btn-batal:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); opacity: 0.9; }
        .hint-text { font-size: 13px; color: #6c757d; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h2>✏️ Edit Soal Ujian</h2>
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-row">
                <div>
                    <label>Mata Pelajaran (*Wajib):</label>
                    <select name="mata_pelajaran" required>
                        <?php foreach($listMapel as $m): ?>
                        <option value="<?= htmlspecialchars($m['mata_pelajaran']) ?>" <?= $soal['mata_pelajaran']==$m['mata_pelajaran'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['mata_pelajaran']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Kelas (*Wajib):</label>
                    <select name="kelas" required>
                        <option value="X" <?= $soal['kelas']=='X' ? 'selected' : '' ?>>X</option>
                        <option value="XI" <?= $soal['kelas']=='XI' ? 'selected' : '' ?>>XI</option>
                        <option value="XII" <?= $soal['kelas']=='XII' ? 'selected' : '' ?>>XII</option>
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
                    <textarea id="ta_deskripsi" name="deskripsi" placeholder="Ketik deskripsi di sini..."><?php echo htmlspecialchars(decode_for_edit($soal['deskripsi']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label>2. Upload Gambar Soal Utama (Opsional):</label>
                <?php $has_img = !empty($soal['gambar']); ?>
                <div class="drop-zone" id="dz_utama" style="<?php echo $has_img ? 'border-style: solid;' : ''; ?>">
                    <span class="dz-text" style="<?php echo $has_img ? 'display:none;' : ''; ?>">📁 Drag & Drop Gambar di Sini<br>atau <b>Klik untuk Mencari</b></span>
                    <input type="file" name="gambar" id="input_utama" accept="image/*" onchange="previewImage(this, 'prev_utama', 'dz_utama')">
                    
                    <img id="prev_utama" class="dz-preview" src="<?php echo $has_img ? '../uploads/'.$soal['gambar'] : ''; ?>" style="<?php echo $has_img ? 'display:block;' : ''; ?>">
                    <button type="button" class="btn-hapus-gambar" style="<?php echo $has_img ? 'display:inline-block;' : ''; ?>" onclick="hapusGambar('input_utama', 'prev_utama', 'dz_utama', 'chk_hapus_utama', event)">🗑️ Hapus Gambar</button>
                    
                    <input type="checkbox" name="hapus_gambar" id="chk_hapus_utama" value="yes" style="display:none;">
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
                    <textarea id="ta_pertanyaan" name="pertanyaan" required placeholder="Ketik pertanyaan utama..."><?php echo htmlspecialchars(decode_for_edit($soal['pertanyaan']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <hr style="margin: 40px 0 20px; border: 1px solid #e9ecef;">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">Pilihan Ganda</h3>
            
            <?php foreach(['A', 'B', 'C', 'D', 'E'] as $opt): $low = strtolower($opt); ?>
            <div class="opsi-box">
                <label style="color: #28a745; font-size: 16px; margin-bottom: 10px;">Opsi <?php echo $opt; ?></label>
                <div class="opsi-layout">
                    <div>
                        <span class="hint-text mb-2" style="margin-top:0; margin-bottom:5px;">Teks Opsi:</span>
                        <div class="editor-wrapper">
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'b')"><b>B</b></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'i')"><i>I</i></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'sup')">X<sup>2</sup></button>
                                <button type="button" class="toolbar-btn" onclick="formatText('ta_opsi_<?php echo $low; ?>', 'sub')">X<sub>2</sub></button>
                            </div>
                            <textarea id="ta_opsi_<?php echo $low; ?>" name="opsi_<?php echo $low; ?>" placeholder="Ketik teks opsi..." style="min-height: 80px;"><?php echo htmlspecialchars(decode_for_edit($soal['opsi_'.$low] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <span class="hint-text mb-2" style="margin-top:0; margin-bottom:5px;">Atau Upload Gambar:</span>
                        <?php $has_img_opsi = !empty($soal['gambar_'.$low]); ?>
                        <div class="drop-zone" id="dz_<?php echo $low; ?>" style="min-height: 110px; padding: 10px; <?php echo $has_img_opsi ? 'border-style: solid;' : ''; ?>">
                            <span class="dz-text" style="font-size: 12px; <?php echo $has_img_opsi ? 'display:none;' : ''; ?>">📁 Drag & Drop<br>atau <b>Klik Disini</b></span>
                            <input type="file" name="gambar_<?php echo $low; ?>" id="input_<?php echo $low; ?>" accept="image/*" onchange="previewImage(this, 'prev_<?php echo $low; ?>', 'dz_<?php echo $low; ?>')">
                            
                            <img id="prev_<?php echo $low; ?>" class="dz-preview" src="<?php echo $has_img_opsi ? '../uploads/'.$soal['gambar_'.$low] : ''; ?>" style="<?php echo $has_img_opsi ? 'display:block;' : ''; ?>">
                            <button type="button" class="btn-hapus-gambar" style="<?php echo $has_img_opsi ? 'display:inline-block;' : ''; ?>" onclick="hapusGambar('input_<?php echo $low; ?>', 'prev_<?php echo $low; ?>', 'dz_<?php echo $low; ?>', 'chk_hapus_<?php echo $low; ?>', event)">🗑️ Hapus</button>
                            
                            <input type="checkbox" name="hapus_gambar_<?php echo $low; ?>" id="chk_hapus_<?php echo $low; ?>" value="yes" style="display:none;">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-group" style="background: #e2eafc; padding: 25px; border-radius: 8px; margin-top: 30px; text-align: center; border: 2px dashed #17a2b8;">
                <label style="font-size: 18px; color: #17a2b8;">🎯 Kunci Jawaban yang Benar:</label>
                <select name="kunci_jawaban" required style="max-width: 200px; font-weight: bold; border: 2px solid #17a2b8; text-align: center; font-size: 16px; margin: 0 auto; display: block;">
                    <?php foreach(['A','B','C','D','E'] as $k): ?>
                        <option value="<?php echo $k; ?>" <?php if($soal['kunci_jawaban'] == $k) echo 'selected'; ?>><?php echo $k; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="btn-group">
                <a href="soal.php" class="btn-batal">❌ Batal</a>
                <button type="submit" class="btn btn-primary">💾 Update Soal & Selesai</button>
            </div>
        </form>
    </div>

    <script>
        function formatText(textareaId, tag) {
            const textarea = document.getElementById(textareaId);
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            const replacement = `<${tag}>${selectedText}</${tag}>`;
            
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            
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
                    dropZone.style.borderStyle = 'solid';
                };
                reader.readAsDataURL(input.files[0]);
                
                // Jika user upload gambar baru, uncheck checkbox hapus (supaya PHP mengerti ini update, bukan sekadar hapus)
                const checkboxHapus = dropZone.querySelector('input[type="checkbox"]');
                if(checkboxId) {
                    checkboxId.checked = false;
                }
            }
        }

        function hapusGambar(inputId, previewId, dropZoneId, checkboxId, event) {
            event.preventDefault(); 
            
            document.getElementById(inputId).value = ""; // Kosongkan file input
            document.getElementById(previewId).src = ""; // Kosongkan src gambar
            document.getElementById(previewId).style.display = 'none'; // Sembunyikan gambar
            
            const dropZone = document.getElementById(dropZoneId);
            dropZone.querySelector('.dz-text').style.display = 'block'; // Tampilkan teks Drag & Drop lagi
            dropZone.querySelector('.btn-hapus-gambar').style.display = 'none'; // Sembunyikan tombol hapus
            dropZone.style.borderStyle = 'dashed'; // Kembalikan border dashed
            
            // CENTANG CHECKBOX TERSEMBUNYI agar PHP menghapus file lamanya di database dan server
            if(checkboxId) {
                document.getElementById(checkboxId).checked = true;
            }
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