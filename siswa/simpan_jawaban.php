<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['ujian_id'])) {
    header("Location: login.php");
    exit;
}

$siswa_id = $_SESSION['siswa_id'];
$ujian_id = $_SESSION['ujian_id'];
// Tangkap array jawaban dari form, jika kosong (tidak dijawab sama sekali), beri array kosong
$jawaban_siswa = $_POST['jawaban'] ?? [];

// Ambil semua kunci jawaban dari database
$mapel_aktif = $_SESSION['mapel_aktif'];

$stmtSoal = $pdo->prepare("
SELECT
id,
kunci_jawaban
FROM soal
WHERE mata_pelajaran = ?
");

$stmtSoal->execute([
    $mapel_aktif
]);

$semua_soal =
$stmtSoal->fetchAll(PDO::FETCH_ASSOC);

$total_soal = count($semua_soal);
$benar = 0;

try {
    // Gunakan Transaction agar jika terjadi error di tengah jalan, database tidak rusak
    $pdo->beginTransaction();
    
    $stmtDelete = $pdo->prepare("
        DELETE FROM jawaban_siswa
        WHERE ujian_id = ?
        ");
        
        $stmtDelete->execute([
            $ujian_id
        ]);

    foreach ($semua_soal as $soal) {
        $soal_id = $soal['id'];
        $kunci = $soal['kunci_jawaban'];
        
        // Cek apakah siswa menjawab soal ini (menghindari error jika ada soal yang dikosongi)
        $jawaban_dipilih = isset($jawaban_siswa[$soal_id]) ? $jawaban_siswa[$soal_id] : null;
        $status_benar = ($jawaban_dipilih === $kunci) ? 1 : 0;

        if ($status_benar) {
            $benar++;
        }

        // Simpan detail jawaban siswa ke database agar Admin Guru bisa mengeceknya nanti
        // KODE YANG BENAR:
        $stmtInsert = $pdo->prepare("INSERT INTO jawaban_siswa (ujian_id, soal_id, jawaban, status_benar) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$ujian_id, $soal_id, $jawaban_dipilih, $status_benar]);
    }

    // Hitung nilai (Skala 100)
    $nilai = ($total_soal > 0) ? ($benar / $total_soal) * 100 : 0;

    // Update tabel ujian_siswa (simpan nilai dan catat waktu selesai)
    $stmtUpdateUjian = $pdo->prepare("UPDATE ujian_siswa SET nilai = ?, waktu_selesai = NOW() WHERE id = ?");
    $stmtUpdateUjian->execute([$nilai, $ujian_id]);

    // Kunci akun siswa agar statusnya menjadi 'selesai' dan tidak bisa login ulang
    $stmtUpdateSiswa = $pdo->prepare("UPDATE siswa SET status_ujian = 'selesai' WHERE id = ?");
    $stmtUpdateSiswa->execute([$siswa_id]);

    $pdo->commit();
    
    // Arahkan ke halaman hasil
    unset($_SESSION['urutan_soal']);
    header("Location: selesai_ujian.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Terjadi kesalahan sistem saat menyimpan jawaban: " . $e->getMessage());
}
?>