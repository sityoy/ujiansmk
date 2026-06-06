<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../koneksi.php';

if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['ujian_id'])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
$ujian_id = $_SESSION['ujian_id'];
$mapel_aktif = $_SESSION['mapel_aktif'];

// Tangkap jawaban, jika kosong beri array kosong agar tidak error
$jawaban_siswa = $_POST['jawaban'] ?? [];
$stmtDelete = $pdo->prepare("DELETE FROM jawaban_siswa WHERE ujian_id = ?");
$stmtDelete->execute([$ujian_id]);

try {
    // Ambil kunci jawaban dari bank soal
    $stmtSoal = $pdo->prepare("SELECT id, kunci_jawaban FROM soal WHERE mata_pelajaran = ?");
    $stmtSoal->execute([$mapel_aktif]);
    $semua_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

    $total_soal = count($semua_soal);
    $benar = 0;

    // Mulai transaksi DB agar aman
    $pdo->beginTransaction();
    
    // Hapus jawaban lama (jika sebelumnya nyangkut)
    $stmtDelete = $pdo->prepare("DELETE FROM jawaban_siswa WHERE ujian_id = ?");
    $stmtDelete->execute([$ujian_id]);

    // Siapkan query insert menggunakan kolom 'jawaban'
    // Ganti baris ini (sekitar baris 47):
        $stmtInsert = $pdo->prepare("INSERT INTO jawaban_siswa (ujian_id, soal_id, jawaban, status_benar) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$ujian_id, $soal_id, $jawaban_dipilih, $status_benar]);

    foreach ($semua_soal as $soal) {
        $soal_id = $soal['id'];
        $jawaban_dipilih = isset($jawaban_siswa[$soal_id]) ? strtoupper(trim($jawaban_siswa[$soal_id])) : '';
        $status_benar = ($jawaban_dipilih === strtoupper(trim($soal['kunci_jawaban']))) ? 1 : 0;

        // INSERT ke database (kolomnya 'jawaban')
        $stmtInsert = $pdo->prepare("INSERT INTO jawaban_siswa (ujian_id, soal_id, jawaban, status_benar) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$ujian_id, $soal_id, $jawaban_dipilih, $status_benar]);
    }

    // Hitung Nilai Skala 100
    $nilai = ($total_soal > 0) ? ($benar / $total_soal) * 100 : 0;

    // Kunci ujian dan simpan nilai ke tabel ujian_siswa
    $stmtUpdateUjian = $pdo->prepare("UPDATE ujian_siswa SET nilai = ?, waktu_selesai = NOW() WHERE id = ?");
    $stmtUpdateUjian->execute([$nilai, $ujian_id]);

    // Jika semua sukses, Commit dan lemparkan ke halaman selesai
    $pdo->commit();
    header("Location: selesai_ujian.php");
    exit;

} catch (Exception $e) {
    // Jika ada error database, batalkan semua dan tampilkan errornya
    $pdo->rollBack();
    die("<div style='color:red; padding:20px; font-family:Arial;'><b>FATAL ERROR GAGAL SIMPAN JAWABAN:</b><br>" . $e->getMessage() . "</div>");
}
?>