<?php
session_start();
require 'cek_admin.php';
require 'SimpleXLSX.php'; 

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas = strtoupper(trim($_POST['kelas'] ?? 'X'));
    if (!in_array($kelas, ['X', 'XI', 'XII'], true)) {
        die("Kelas tidak valid.");
    }

    $tmp_dir = "temp_import/";
    if (!file_exists($tmp_dir)) mkdir($tmp_dir, 0777, true);
    
    $file_name = "import_" . time() . ".xlsx";
    $file_path = $tmp_dir . $file_name;
    
    if (move_uploaded_file($_FILES['file_excel']['tmp_name'], $file_path)) {
        
        // Mulai Parsing
        if ($xlsx = Shuchkin\SimpleXLSX::parse($file_path)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO soal (mata_pelajaran, kelas, deskripsi, gambar, pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $rows = $xlsx->rows();
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    if (empty($row[2])) continue; // Skip jika kolom pertanyaan kosong

                    $stmt->execute([
                        $mata_pelajaran,
                        $kelas,
                        nl2br(htmlspecialchars($row[0] ?? '')), // Deskripsi
                        $row[1] ?? '', // Gambar Soal
                        nl2br(htmlspecialchars($row[2] ?? '')), // Pertanyaan
                        $row[3] ?? '', $row[4] ?? '', // Opsi A, Gbr A
                        $row[5] ?? '', $row[6] ?? '', // Opsi B, Gbr B
                        $row[7] ?? '', $row[8] ?? '', // Opsi C, Gbr C
                        $row[9] ?? '', $row[10] ?? '', // Opsi D, Gbr D
                        $row[11] ?? '', $row[12] ?? '', // Opsi E, Gbr E
                        strtoupper(trim($row[13] ?? 'A')) // Kunci
                    ]);
                }
                $pdo->commit();
                
                // Hapus file sementara setelah selesai
                unlink($file_path);
                
                echo "<script>alert('Import berhasil!'); window.location='soal.php';</script>";
            } catch (Exception $e) {
                $pdo->rollBack();
                die("Gagal memproses database: " . $e->getMessage());
            }
        } else {
            echo "Gagal memproses file Excel.";
        }
    } else {
        echo "Gagal mengupload file.";
    }
} else {
    echo "Tidak ada file yang diupload.";
}
?>
