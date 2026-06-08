<?php
session_start();
require '../cek_admin.php';

// Panggil library SimpleXLSX (asumsi file ini ada di folder admin/)
require '../SimpleXLSX.php'; 

if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }

if (isset($_POST['import']) && isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == 0) {
    
    // Buat folder sementara jika belum ada
    $tmp_dir = "temp_import/";
    if (!file_exists($tmp_dir)) mkdir($tmp_dir, 0777, true);
    
    $file_name = "import_siswa_" . time() . ".xlsx";
    $file_path = $tmp_dir . $file_name;
    
    // Pindahkan file excel yang diupload
    if (move_uploaded_file($_FILES['file_excel']['tmp_name'], $file_path)) {
        
        // Parsing file menggunakan SimpleXLSX
        if ($xlsx = Shuchkin\SimpleXLSX::parse($file_path)) {
            $sukses = 0;
            $gagal = 0;
            
            try {
                $pdo->beginTransaction();
                
                $stmtCek = $pdo->prepare("SELECT id FROM siswa WHERE kartu_peserta = ?");
                $stmtInsert = $pdo->prepare("INSERT INTO siswa (kartu_peserta, nama_siswa, kelas, password, status_ujian) VALUES (?, ?, ?, ?, 'belum')");
                
                $rows = $xlsx->rows();
                
                // Mulai dari $i = 1 untuk melewati baris Judul Kolom
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    $kartu_peserta = trim($row[0] ?? '');
                    $nama_siswa    = trim($row[1] ?? '');
                    $kelas         = trim($row[2] ?? '');
                    $password      = trim($row[3] ?? '');

                    if (!empty($kartu_peserta) && !empty($nama_siswa)) {
                        
                        // Cek Duplikat No Peserta
                        $stmtCek->execute([$kartu_peserta]);
                        if ($stmtCek->rowCount() > 0) {
                            $gagal++; 
                        } else {
                            $stmtInsert->execute([$kartu_peserta, $nama_siswa, $kelas, $password]);
                            $sukses++;
                        }
                    }
                }
                
                $pdo->commit();
                unlink($file_path); // Hapus file dari server
                
                echo "<script>alert('Import Selesai! Berhasil: $sukses siswa. Gagal/Duplikat: $gagal siswa.'); window.location='index.php';</script>";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                unlink($file_path);
                die("Gagal memproses database: " . $e->getMessage());
            }
        } else {
            unlink($file_path);
            echo "<script>alert('Gagal memproses file. Pastikan formatnya .xlsx'); window.location='import_siswa.php';</script>";
        }
    } else {
        echo "<script>alert('Gagal memindahkan file upload.'); window.location='import_siswa.php';</script>";
    }
} else {
    echo "<script>alert('Akses tidak sah atau file tidak ditemukan!'); window.location='import_siswa.php';</script>";
}
?>