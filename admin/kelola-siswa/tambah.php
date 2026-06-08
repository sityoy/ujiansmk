<?php
// session_start();
require 'cek_admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kartu = $_POST['kartu_peserta'];
    $nama = $_POST['nama_siswa'];
    $kelas = $_POST['kelas'];
    // Enkripsi password sebelum masuk database
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 

    $stmt = $pdo->prepare("INSERT INTO siswa (kartu_peserta, nama_siswa, kelas, password, status_ujian) VALUES (?, ?, ?, ?, 'belum')");
    $stmt->execute([$kartu, $nama, $kelas, $password]);
    echo "<script>alert('Siswa berhasil ditambah!'); window.location='index.php';</script>";
}
?>
<!-- Gunakan type="password" agar ketikan tersembunyi -->
<form method="POST">
    <input type="text" name="kartu_peserta" placeholder="No Peserta" required>
    <input type="text" name="nama_siswa" placeholder="Nama Lengkap" required>
    <input type="text" name="kelas" placeholder="Kelas" required>
    <input type="password" name="password" placeholder="Password Ujian" required>
    <button type="submit">Simpan</button>
</form>
<?php include "footer.php"; ?>