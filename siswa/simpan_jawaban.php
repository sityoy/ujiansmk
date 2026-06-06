<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['siswa_id'], $_SESSION['ujian_id'], $_SESSION['mapel_aktif'], $_SESSION['kelas'])) {
    header("Location: login.php");
    exit;
}

$ujian_id = (int) $_SESSION['ujian_id'];
$mapel_aktif = $_SESSION['mapel_aktif'];
$jawaban_siswa = $_POST['jawaban'] ?? [];

$kelas = strtoupper(trim($_SESSION['kelas']));
if (strpos($kelas, 'XII') === 0) {
    $kelas = 'XII';
} elseif (strpos($kelas, 'XI') === 0) {
    $kelas = 'XI';
} else {
    $kelas = 'X';
}

try {
    $stmtSoal = $pdo->prepare("SELECT id, kunci_jawaban FROM soal WHERE mata_pelajaran = ? AND kelas = ?");
    $stmtSoal->execute([$mapel_aktif, $kelas]);
    $semua_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

    $total_soal = count($semua_soal);
    $benar = 0;
    $salah = 0;

    $pdo->beginTransaction();

    $stmtDelete = $pdo->prepare("DELETE FROM jawaban_siswa WHERE ujian_id = ?");
    $stmtDelete->execute([$ujian_id]);

    $stmtInsert = $pdo->prepare("
        INSERT INTO jawaban_siswa (ujian_id, soal_id, jawaban_dipilih, status_benar)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($semua_soal as $soal) {
        $soal_id = (int) $soal['id'];
        $jawaban_dipilih = isset($jawaban_siswa[$soal_id]) ? strtoupper(trim($jawaban_siswa[$soal_id])) : null;

        if (!in_array($jawaban_dipilih, ['A', 'B', 'C', 'D', 'E'], true)) {
            $jawaban_dipilih = null;
        }

        $status_benar = ($jawaban_dipilih !== null && $jawaban_dipilih === strtoupper(trim($soal['kunci_jawaban']))) ? 1 : 0;
        if ($status_benar === 1) {
            $benar++;
        } else {
            $salah++;
        }

        $stmtInsert->execute([$ujian_id, $soal_id, $jawaban_dipilih, $status_benar]);
    }

    $nilai = ($total_soal > 0) ? round(($benar / $total_soal) * 100, 2) : 0;

    $stmtUpdateUjian = $pdo->prepare("UPDATE ujian_siswa SET nilai = ?, waktu_selesai = NOW() WHERE id = ?");
    $stmtUpdateUjian->execute([$nilai, $ujian_id]);

    $pdo->commit();
    header("Location: selesai_ujian.php");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Gagal simpan jawaban ujian {$ujian_id}: " . $e->getMessage());
    die("<div style='color:red; padding:20px; font-family:Arial;'><b>Gagal menyimpan jawaban.</b><br>Silakan hubungi administrator.</div>");
}
?>
