<?php
session_start();
require '../koneksi.php';

function hapus_file_lama($nama_file, $dir) {
        if (!empty($nama_file) && file_exists($dir . $nama_file)) unlink($dir . $nama_file);
    }

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    header("Location: soal.php");
    exit;
}
$stmtMapel = $pdo->query("
SELECT DISTINCT mata_pelajaran
FROM soal
ORDER BY mata_pelajaran
");

$listMapel = $stmtMapel->fetchAll();

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ?");
$stmt->execute([$id]);
$soal = $stmt->fetch();

if (!$soal) die("Soal tidak ditemukan!");

// Fungsi membersihkan teks untuk textarea
function decode_for_edit($text){

    $text = str_ireplace(
        ['<br />','<br>','<br/>'],
        "\n",
        $text ?? ''
    );

    return htmlspecialchars_decode(
        $text,
        ENT_QUOTES
    );
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas = $_POST['kelas'];
    $deskripsi = nl2br($_POST['deskripsi'] ?? '');
    $pertanyaan = nl2br($_POST['pertanyaan'] ?? '');
    $opsi_a = htmlspecialchars($_POST['opsi_a'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_b = htmlspecialchars($_POST['opsi_b'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_c = htmlspecialchars($_POST['opsi_c'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_d = htmlspecialchars($_POST['opsi_d'] ?? '', ENT_QUOTES, 'UTF-8');
    $opsi_e = htmlspecialchars($_POST['opsi_e'] ?? '', ENT_QUOTES, 'UTF-8');
    $kunci = $_POST['kunci_jawaban'];
    
    $gambar = $soal['gambar'];
    $gbr = ['a' => $soal['gambar_a'], 'b' => $soal['gambar_b'], 'c' => $soal['gambar_c'], 'd' => $soal['gambar_d'], 'e' => $soal['gambar_e']];
    $target_dir = "../uploads/";
    
    // Hapus gambar utama
    if(isset($_POST['hapus_gambar'])){
    
        hapus_file_lama(
            $gambar,
            $target_dir
        );
    
        $gambar = '';
    }
    

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        hapus_file_lama($gambar, $target_dir);
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = "soal_utama_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target_dir . $gambar);
    }
    
    foreach(['a','b','c','d','e'] as $opt){

    if(isset($_POST['hapus_gambar_'.$opt])){

        hapus_file_lama(
            $gbr[$opt],
            $target_dir
        );

        $gbr[$opt] = '';
    }

}

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
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; padding: 20px; }
        .card { background: white; padding: 40px; border-radius: 8px; max-width: 1000px; margin: auto; border-top: 5px solid #17a2b8; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { color: #2c3e50; border-bottom: 2px solid #f0f2f5; padding-bottom: 15px; margin-bottom: 25px; }
        .form-group { margin-bottom: 25px; }
        .opsi-box { background: #f8f9fa; padding: 20px; border-left: 5px solid #17a2b8; margin-bottom: 15px; border-radius: 0 8px 8px 0; }
        label { font-weight: 600; display: block; margin-bottom: 8px; color: #495057; }
        input[type="text"], select { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; }
        textarea { width: 100%; padding: 15px; border: 1px solid #ced4da; border-radius: 6px; min-height: 140px; resize: vertical; }
        input[type="file"] { display: block; margin-top: 5px; padding: 8px; border: 1px dashed #ced4da; width: 100%; border-radius: 6px; }
        .preview-container { margin-top: 10px; text-align: center; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e9ecef; }
        .preview-img { max-width: 100%; max-height: 200px; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; background: #17a2b8; color: white; }
        .btn-batal { background: #6c757d; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; margin-left: 10px; }
        .hapus-gambar{
            display:block;
            margin-top:12px;
            padding:10px;
            background:#fff3f3;
            border:1px solid #ffb3b3;
            border-radius:6px;
            color:#dc3545;
            font-weight:600;
        }
        
        .hapus-gambar input{
            margin-right:8px;
        }
        
        .preview-container{
            margin-top:10px;
            padding:15px;
            border-radius:8px;
            background:#fafafa;
        }
        
        .preview-img{
            max-width:250px;
            max-height:180px;
            border-radius:8px;
            border:1px solid #ddd;
        }
        .preview-container{
            text-align:center;
            padding:15px;
            background:#fafafa;
            border:1px solid #ddd;
            border-radius:8px;
        }
        .btn{
            transition:.2s;
        }
        
        .btn:hover{
            transform:translateY(-2px);
        }
        
        .btn-batal:hover{
            background:#5a6268;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>✏️ Edit Soal</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Mata Pelajaran:</label>
                <select name="mata_pelajaran" required>

                <?php foreach($listMapel as $m): ?>
                
                <option
                value="<?= htmlspecialchars($m['mata_pelajaran']) ?>"
                <?= $soal['mata_pelajaran']==$m['mata_pelajaran']
                ? 'selected'
                : '' ?>>
                
                <?= htmlspecialchars($m['mata_pelajaran']) ?>
                
                </option>
                
                <?php endforeach; ?>
                
                </select>
                <label>Kelas:</label>
            
                <select name="kelas" required>

                    <option value="X"
                    <?= $soal['kelas']=='X' ? 'selected' : '' ?>>
                    X
                    </option>
                
                    <option value="XI"
                    <?= $soal['kelas']=='XI' ? 'selected' : '' ?>>
                    XI
                    </option>

                    <option value="XII"
                    <?= $soal['kelas']=='XII' ? 'selected' : '' ?>>
                    XII
                    </option>

                </select>
            </div>
        
            <div class="form-group">
                <label>Deskripsi (Opsional):</label>
                <textarea name="deskripsi"><?php echo htmlspecialchars(decode_for_edit($soal['deskripsi']), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Gambar Soal Utama:</label>
                <div id="prev_utama_container"
                    class="preview-container"
                    <?php echo empty($soal['gambar'])
                    ? 'style="display:none;"'
                    : ''; ?>>
                    
                        <img id="prev_utama"
                        class="preview-img"
                        src="<?php echo !empty($soal['gambar'])
                        ? '../uploads/'.$soal['gambar']
                        : ''; ?>">
                    
                    </div>
                    
                    <?php if(!empty($soal['gambar'])): ?>
                    
                    <label
                    style="
                    display:block;
                    margin-top:10px;
                    color:red;
                    font-weight:bold;
                    ">
                        <input
                        class="hapus-gambar" type="checkbox"
                        name="hapus_gambar">
                    
                        Hapus gambar soal utama
                    </label>
                    
                    <?php endif; ?>
                
                
                <input type="file" name="gambar" accept="image/*" onchange="previewImage(event, 'prev_utama')">
            </div>
            

            <div class="form-group">
                <label>Soal / Pertanyaan (*Wajib):</label>
                <small class="hint-text">
                Tips Format:
                &lt;i&gt;Teks Miring&lt;/i&gt;
                |
                Pangkat:
                &lt;sup&gt;2&lt;/sup&gt;
                (Contoh: x²)
                </small>
                <textarea name="pertanyaan" required><?php echo htmlspecialchars(decode_for_edit($soal['pertanyaan']), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <hr style="margin: 30px 0;">
            <h3>Pilihan Ganda</h3>
            
            <?php foreach(['A','B','C','D','E'] as $opt): $low = strtolower($opt); ?>

            <div class="opsi-box">
            
                <label>Opsi <?php echo $opt; ?></label>
            
                <input
                type="text"
                name="opsi_<?php echo $low; ?>"
                value="<?php echo htmlspecialchars(
                    htmlspecialchars_decode(
                        $soal['opsi_'.$low] ?? '',
                        ENT_QUOTES
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>">
            
                <div
                id="prev_<?php echo $low; ?>_container"
                class="preview-container"
                <?php echo empty($soal['gambar_'.$low])
                ? 'style="display:none;"'
                : ''; ?>>
            
                    <img
                    id="prev_<?php echo $low; ?>"
                    class="preview-img"
                    src="<?php echo !empty($soal['gambar_'.$low])
                    ? '../uploads/'.$soal['gambar_'.$low]
                    : ''; ?>">
            
                </div>
            
                <?php if(!empty($soal['gambar_'.$low])): ?>
                    <label class="hapus-gambar" style="margin-top:10px;display:block;color:red;
                        ">
                        <input
                        type="checkbox"
                        name="hapus_gambar_<?php echo $low; ?>">
                        Hapus gambar opsi <?php echo $opt; ?>
                    </label>
                <?php endif; ?>
            
                <input
                type="file"
                name="gambar_<?php echo $low; ?>"
                accept="image/*"
                onchange="previewImage(event,'prev_<?php echo $low; ?>')">
            
            </div>
            
            <?php endforeach; ?>
                
            
                
            
            

            <div class="form-group">
                <label>Kunci Jawaban:</label>
                <select name="kunci_jawaban" required style="max-width: 200px;">
                    <?php foreach(['A','B','C','D','E'] as $k): ?>
                        <option value="<?php echo $k; ?>" <?php if($soal['kunci_jawaban'] == $k) echo 'selected'; ?>><?php echo $k; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Update Soal</button>
            <a href="soal.php" class="btn-batal">Batal</a>
        </form>
    </div>

    <script>
        function previewImage(event, previewId) {
            var reader = new FileReader();
            reader.onload = function(){
                var img = document.getElementById(previewId);
                img.src = reader.result;
                let container =
                    document.getElementById(
                        previewId + '_container'
                    );
                    
                    container.style.display = 'block';
                    
                    img.style.display = 'block';
            };
            if(event.target.files && event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</body>
</html>
