<?php
session_start();
// Pastikan file SimpleXLSXGen.php sudah Bapak download dan taruh di folder admin/
require '../SimpleXLSXGen.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// 1. Siapkan struktur data Excel-nya (Baris pertama adalah Judul Kolom)
$data_template = [
    ['No Peserta', 'Nama Lengkap', 'Kelas', 'Password'],
    ['2026001', 'Budi Santoso', 'XII AK 1', 'siswa123'],
    ['2026002', 'Siti Aminah', 'XII AK 2', 'siswa123'],
    ['2026003', 'Andi Wijaya', 'XII AK 1', 'siswa123']
];

// 2. Generate menjadi file Excel dan paksa browser untuk mendownloadnya
Shuchkin\SimpleXLSXGen::fromArray($data_template)->downloadAs('Template_Data_Siswa.xlsx');
exit;
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body>
    <table border="1">
        <tr>
            <th style="background-color:#ffff00;">No Peserta</th>
            <th style="background-color:#ffff00;">Nama Lengkap</th>
            <th style="background-color:#ffff00;">Kelas</th>
            <th style="background-color:#ffff00;">Password</th>
        </tr>
        <tr>
            <td>2026001</td>
            <td>Budi Santoso</td>
            <td>XII AK 1</td>
            <td>siswa123</td>
        </tr>
        <tr>
            <td>2026002</td>
            <td>Siti Aminah</td>
            <td>XII AK 2</td>
            <td>siswa123</td>
        </tr>
    </table>
</body>
</html>