<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Soal Excel</title>
    <style>
        body { font-family: Arial; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 5px; max-width: 600px; margin: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h3>Upload File Excel</h3>
        <form action="proses_import.php" method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 20px;">
                <label>Pilih Mata Pelajaran:</label><br>
                <select name="mata_pelajaran" required style="width:100%; padding:8px;">
                    <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                    <option value="MTK">MTK</option>
                    <option value="Bahasa Inggris">Bahasa Inggris</option>
                    <option value="PAI">PAI</option>
                </select>
            </div>
            <div style="margin-bottom: 20px;">
                <label>Pilih Kelas:</label><br>
                <select name="kelas" required style="width:100%; padding:8px;">
                    <option value="X">Kelas X</option>
                    <option value="XI">Kelas XI</option>
                    <option value="XII">Kelas XII</option>
                </select>
            </div>
            <div style="margin-bottom: 20px;">
                <label>File Excel (.xlsx):</label><br>
                <input type="file" name="file_excel" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn">Upload & Lanjut ke Import</button>
        </form>
    </div>
</body>
</html>
