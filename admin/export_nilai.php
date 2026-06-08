<?php
// 1. Bersihkan semua output buffer di awal untuk mencegah file Excel korup / rusak
ob_start();

// 2. Load file admin dan library Excel
require 'cek_admin.php';

// PENGAMAN: Jika di dalam cek_admin.php ternyata belum panggil koneksi.php, kita panggil manual di sini
if (!isset($pdo)) {
    require '../koneksi.php'; 
}

// Cari file SimpleXLSXGen.php. Jika tidak ketemu di ../ kita cek di folder saat ini
if (file_exists('../SimpleXLSXGen.php')) {
    require '../SimpleXLSXGen.php';
} elseif (file_exists('SimpleXLSXGen.php')) {
    require 'SimpleXLSXGen.php';
} else {
    die("Gagal Export: File 'SimpleXLSXGen.php' tidak ditemukan! Pastikan file tersebut sudah di-upload ke server.");
}

// Tangkap filter dari URL agar Excel yang diunduh sesuai dengan yang tampil di layar
$mapel_filter = isset($_GET['mapel']) ? trim($_GET['mapel']) : '';
$kelas_filter = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';

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
            // Ditambahkan petik satu (') di depan agar nomor peserta tidak berantakan/hilang angka nol-nya di Excel
            "'" . $row['kartu_peserta'], 
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
    $filename .= "_" . date('Ymd_His') . ".xlsx";

    // 3. Hapus bersih semua spasi kosong/HTML liar sebelum melempar file Excel ke browser
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Generate dan Download menggunakan library SimpleXLSXGen
    \Shuchkin\SimpleXLSXGen::fromArray($excel_data)->downloadAs($filename);
    exit;

} catch (Exception $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    die("Gagal mengekspor data ujian: " . $e->getMessage());
}
?>