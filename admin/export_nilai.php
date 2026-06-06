<?php

session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=rekap_nilai.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'No Peserta',
    'Nama',
    'Kelas',
    'Mapel',
    'Nilai',
    'Pelanggaran'
]);

$stmt = $pdo->query("
SELECT
s.kartu_peserta,
s.nama_siswa,
s.kelas,
u.mata_pelajaran,
u.nilai,
u.jumlah_pelanggaran
FROM ujian_siswa u
JOIN siswa s
ON s.id=u.siswa_id
ORDER BY s.nama_siswa
");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

    fputcsv($output,$row);

}

fclose($output);
exit;