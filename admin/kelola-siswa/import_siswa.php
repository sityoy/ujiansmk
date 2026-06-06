<?php
// session_start();
require 'cek_admin.php';
// if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Data Siswa (.xlsx)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .card { background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: auto; border-top: 5px solid #28a745; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn { padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold; color: white; display: inline-block; cursor: pointer; border: none; font-size: 15px; }
        .btn-success { background: #28a745; margin-bottom: 20px; }
        .btn-primary { background: #007bff; }
        .btn-batal { background: #6c757d; }
        .form-group { margin-bottom: 20px; background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px dashed #28a745; text-align: center;}
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0;">📥 Import Siswa (.xlsx)</h2>
        
        <ol style="line-height: 1.8; color: #444; margin-bottom: 25px;">
            <li>Klik tombol download di bawah ini untuk mendapatkan template Excel.</li>
            <li>Buka file <b>Template_Data_Siswa.xlsx</b> di Microsoft Excel.</li>
            <li>Isi data siswa Bapak, lalu <b>Save</b> (Pastikan formatnya tetap .xlsx).</li>
            <li>Upload file yang sudah diisi tersebut ke kolom di bawah ini.</li>
        </ol>

        <div style="text-align: center;">
            <a href="download_template_siswa.php" class="btn btn-success">⬇️ Download Template (.xlsx)</a>
        </div>

        <form action="proses_import_siswa.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label style="font-weight: bold; display: block; margin-bottom: 10px;">Upload File Excel (.xlsx) Anda:</label>
                <input type="file" name="file_excel" accept=".xlsx" required>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="submit" name="import" class="btn btn-primary">🚀 Mulai Import Data</button>
                <a href="index.php" class="btn btn-batal">Kembali</a>
            </div>
        </form>
        <?php include "footer.php"; ?>
    </div>
</body>
</html>