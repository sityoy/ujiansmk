<?php
session_start();
require '../koneksi.php';
// Gunakan SimpleXLSX (Download file SimpleXLSX.php dan letakkan di folder admin/)
require 'SimpleXLSX.php'; 

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
    $file_tmp = $_FILES['file_excel']['tmp_name'];
    
    if ($xlsx = Shuchkin\SimpleXLSX::parse($file_tmp)) {
        $mata_pelajaran = $_POST['mata_pelajaran']; 
        
        try {
            $pdo->beginTransaction();
            
            // Query INSERT dengan 15 kolom
            $stmt = $pdo->prepare("INSERT INTO soal (
                mata_pelajaran, deskripsi, gambar, pertanyaan, 
                opsi_a, gambar_a, opsi_b, gambar_b, 
                opsi_c, gambar_c, opsi_d, gambar_d, 
                opsi_e, gambar_e, kunci_jawaban
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $rows = $xlsx->rows();
            
            // Loop mulai dari baris ke-1 (melewati header baris ke-0)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Jika baris kosong, lewati
                if (empty($row[2])) continue; 

                // Pemetaan Kolom Excel ke Database
                $deskripsi  = $row[0] ?? '';
                $gambar     = $row[1] ?? '';
                $pertanyaan = $row[2] ?? '';
                
                $opsi_a     = $row[3] ?? '';
                $gambar_a   = $row[4] ?? '';
                
                $opsi_b     = $row[5] ?? '';
                $gambar_b   = $row[6] ?? '';
                
                $opsi_c     = $row[7] ?? '';
                $gambar_c   = $row[8] ?? '';
                
                $opsi_d     = $row[9] ?? '';
                $gambar_d   = $row[10] ?? '';
                
                $opsi_e     = $row[11] ?? '';
                $gambar_e   = $row[12] ?? '';
                
                $kunci      = strtoupper(trim($row[13] ?? 'A'));

                // Simpan
                $stmt->execute([
                    $mata_pelajaran, $deskripsi, $gambar, $pertanyaan, 
                    $opsi_a, $gambar_a, $opsi_b, $gambar_b, 
                    $opsi_c, $gambar_c, $opsi_d, $gambar_d, 
                    $opsi_e, $gambar_e, $kunci
                ]);
            }

            $pdo->commit();
            echo "<script>alert('Import soal berhasil!'); window.location='soal.php';</script>";

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Gagal mengimport soal: " . $e->getMessage());
        }
    } else {
        echo "<script>alert('Gagal membaca file Excel!'); window.location='soal.php';</script>";
    }
}
?>