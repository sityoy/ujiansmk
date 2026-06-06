<?php
session_start();
require '../koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: soal.php");
    exit;
}

$id = (int)$_GET['id'];

try {

    $stmt = $pdo->prepare("
        SELECT
            gambar,
            gambar_a,
            gambar_b,
            gambar_c,
            gambar_d,
            gambar_e
        FROM soal
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $soal = $stmt->fetch();

    if (!$soal) {
        die("Data soal tidak ditemukan.");
    }

    $folder = "../uploads/";

    foreach (
        [
            $soal['gambar'],
            $soal['gambar_a'],
            $soal['gambar_b'],
            $soal['gambar_c'],
            $soal['gambar_d'],
            $soal['gambar_e']
        ]
        as $file
    ) {

        if (
            !empty($file) &&
            file_exists($folder . $file)
        ) {
            unlink($folder . $file);
        }
    }

    $stmtDelete = $pdo->prepare(
        "DELETE FROM soal WHERE id = ?"
    );

    $stmtDelete->execute([$id]);

    echo "
    <script>
        alert('Soal berhasil dihapus');
        window.location='soal.php';
    </script>
    ";

} catch (Exception $e) {

    die(
        'Gagal menghapus soal : ' .
        $e->getMessage()
    );

}