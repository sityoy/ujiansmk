<?php
// Pastikan file SimpleXLSXGen.php sudah ada di folder utama/parent
require '../SimpleXLSXGen.php';
require 'cek_admin.php';

// Tangkap filter dari URL agar Excel yang diunduh sesuai dengan yang tampil di layar
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$kelas_filter = isset($_GET['kelas']) ? $_GET['kelas'] : '';

$where = "WHERE 1=1";
$params = [];

if ($mapel_filter != '') {
    $where .= " AND u.mata_pelajaran = ?";
    $params[] = $mapel_filter;
}
if ($kelas_filter != '') {
    $where .= " AND s.kelas = ?";
    $params[] = $kelas_filter;
}

try {
    // Ambil data nilai yang sudah digabungkan dengan tabel siswa
    $stmt = $pdo->prepare("
        SELECT u.*, s.nama_siswa, s.kartu_peserta, s.kelas 
        FROM ujian_siswa u 
        JOIN siswa s ON u.siswa_id = s.id 
        $where 
        ORDER BY s.kelas ASC, s.nama_siswa ASC
    ");
    $stmt->execute($params);
    $data_ujian = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Siapkan Baris Pertama Excel (Header Kolom)
    $excel_data = [
        ['No', 'No. Peserta', 'Nama Siswa', 'Kelas', 'Mata Pelajaran', 'Jawab Benar', 'Jawab Salah', 'Nilai Akhir', 'Pelanggaran']
    ];

    // Masukkan data siswa ke baris berikutnya
    $no = 1;
    foreach ($data_ujian as $row) {
        $excel_data[] = [
            $no++,
            // Tambahkan spasi atau petik satu agar Nomor Peserta dibaca sebagai teks utuh oleh Excel
            " " . $row['kartu_peserta'], 
            $row['nama_siswa'],
            $row['kelas'],
            $row['mata_pelajaran'],
            (int)($row['benar'] ?? 0),
            (int)($row['salah'] ?? 0),
            (int)($row['nilai'] ?? 0),
            $row['jumlah_pelanggaran'] . 'x'
        ];
    }

    // Penamaan File Excel Otomatis Berdasarkan Filter
    $filename = "Rekap_Nilai_CBT";
    if ($kelas_filter) $filename .= "_Kelas_" . preg_replace('/[^A-Za-z0-9]/', '', $kelas_filter);
    if ($mapel_filter) $filename .= "_" . preg_replace('/[^A-Za-z0-9]/', '', $mapel_filter);
    $filename .= "_" . date('Ymd') . ".xlsx";

    // Generate dan Download
    Shuchkin\SimpleXLSXGen::fromArray($excel_data)->downloadAs($filename);
    exit;

} catch (Exception $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}
?>