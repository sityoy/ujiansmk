<!-- fle pertama -->
<?php
session_start();
require 'cek_admin.php';

// Pastikan admin sudah login (Sesuaikan dengan sistem login admin Anda)
// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

if (!isset($_GET['id'])) {
    die("ID Ujian tidak ditemukan!");
}

$ujian_id = $_GET['id'];

// Mengambil data ujian beserta data siswa
$stmt = $pdo->prepare("
    SELECT u.*, s.nama_siswa, s.kartu_peserta, s.kelas 
    FROM ujian_siswa u 
    JOIN siswa s ON u.siswa_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$ujian_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Data ujian tidak ditemukan di database.");
}

// Menghitung status pengerjaan
$status_ujian = ($data['jumlah_pelanggaran'] >= 5) ? "Diskualifikasi (Pelanggaran)" : "Selesai";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Nilai - <?php echo htmlspecialchars($data['nama_siswa']); ?></title>
    <style>
        /* Desain Layar Dasar */
        body {
            font-family: 'Times New Roman', Times, serif; /* Font standar dokumen resmi */
            background-color: #525659; /* Warna latar seperti pembaca PDF */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        /* Simulasi Kertas A4 */
        .kertas-a4 {
            background-color: #fff;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }

        /* Kop Surat Resmi */
        .kop-surat {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .kop-surat h2, .kop-surat h3, .kop-surat p { margin: 2px 0; }
        .kop-surat h2 { font-size: 22px; text-transform: uppercase; }
        .kop-surat h3 { font-size: 18px; }
        .kop-surat p { font-size: 14px; }

        /* Tabel Identitas & Nilai */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .info-table td {
            padding: 8px 5px;
            vertical-align: top;
        }
        .info-table td:first-child { width: 30%; font-weight: bold; }
        .info-table td:nth-child(2) { width: 2%; }

        /* Kotak Nilai Besar */
        .skor-box {
            text-align: center;
            border: 2px solid #000;
            padding: 15px;
            margin: 20px 0;
            background-color: #f9f9f9;
        }
        .skor-box h1 {
            font-size: 48px;
            margin: 0;
            color: #000;
        }
        .skor-box p { margin: 5px 0 0 0; font-size: 16px; font-weight: bold; }

        /* Foto Selfie */
        .foto-verifikasi {
            text-align: center;
            margin-top: 20px;
        }
        .foto-verifikasi img {
            max-width: 250px;
            max-height: 300px;
            border: 1px solid #ccc;
            padding: 5px;
            background: #fff;
        }

        /* Tombol Cetak Melayang (Floating) */
        .btn-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 15px 25px;
            font-size: 18px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            font-weight: bold;
            z-index: 1000;
        }
        .btn-print:hover { background-color: #218838; }

        /* PENGATURAN KHUSUS PRINTER (AJAIBNYA DI SINI) */
        @page {
            size: A4 portrait; /* Paksa printer membaca format A4 */
            margin: 15mm; /* Margin standar printer */
        }
        
        @media print {
            body { 
                background-color: #fff; 
                margin: 0; 
                padding: 0; 
                display: block; 
            }
            .kertas-a4 { 
                box-shadow: none; /* Hilangkan bayangan saat dicetak */
                width: auto; 
                min-height: auto; 
                padding: 0; 
                margin: 0; 
            }
            .btn-print { 
                display: none; /* Sembunyikan tombol saat dicetak */
            }
            /* Mencegah gambar atau kotak nilai terpotong di halaman kedua */
            .skor-box, .foto-verifikasi { 
                page-break-inside: avoid; 
            }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">🖨️ Cetak Dokumen</button>

    <div class="kertas-a4">
        
        <div class="kop-surat">
            <h2>LAPORAN HASIL UJIAN (CBT)</h2>
            <h3>TAHUN PELAJARAN 2025/2026</h3>
            <p>Dokumen ini merupakan hasil cetak resmi dari sistem Computer Based Test.</p>
        </div>

        <h3 style="text-align: center; text-decoration: underline; margin-bottom: 30px;">LEMBAR HASIL EVALUASI PESERTA</h3>

        <table class="info-table">
            <tr>
                <td>Nomor Peserta</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($data['kartu_peserta']); ?></td>
            </tr>
            <tr>
                <td>Nama Lengkap</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($data['nama_siswa']); ?></td>
            </tr>
            <tr>
                <td>Kelas / Kompetensi</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($data['kelas']); ?></td>
            </tr>
            <tr>
                <td>Mata Pelajaran</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($data['mata_pelajaran']); ?></td>
            </tr>
            <tr>
                <td>Waktu Pengerjaan</td>
                <td>:</td>
                <td>
                    <?php 
                        $mulai = strtotime($data['waktu_mulai']);
                        $selesai = strtotime($data['waktu_selesai']);
                        echo date('d M Y, H:i', $mulai) . " s/d " . date('H:i', $selesai); 
                    ?>
                </td>
            </tr>
            <tr>
                <td>Status Pengerjaan</td>
                <td>:</td>
                <td style="font-weight:bold; color: <?php echo ($data['jumlah_pelanggaran'] >= 5) ? 'red' : 'green'; ?>;">
                    <?php echo $status_ujian; ?>
                </td>
            </tr>
            <tr>
                <td>Total Pelanggaran</td>
                <td>:</td>
                <td><?php echo $data['jumlah_pelanggaran']; ?> Kali Peringatan</td>
            </tr>
        </table>

        <div class="skor-box">
            <p>NILAI AKHIR</p>
            <h1><?php echo isset($data['nilai']) ? $data['nilai'] : '0'; ?></h1>
        </div>

        <table class="info-table">
            <tr>
                <td>Jawaban Benar</td>
                <td>:</td>
                <td><?php echo isset($data['benar']) ? $data['benar'] : '-'; ?> Soal</td>
            </tr>
            <tr>
                <td>Jawaban Salah</td>
                <td>:</td>
                <td><?php echo isset($data['salah']) ? $data['salah'] : '-'; ?> Soal</td>
            </tr>
        </table>

        <div class="foto-verifikasi">
            <p style="font-weight: bold; text-decoration: underline; margin-bottom: 10px;">Verifikasi Wajah (Selfie Peserta)</p>
            <?php if (!empty($data['foto_selfie'])): ?>
                <img src="../assets/<?php echo htmlspecialchars($data['foto_selfie']); ?>" alt="Foto Verifikasi Siswa">
            <?php else: ?>
                <div style="border:1px dashed #ccc; padding:50px; color:#999; width: 200px; margin: auto;">Foto Tidak Tersedia</div>
            <?php endif; ?>
        </div>

        <table style="width: 100%; margin-top: 50px; text-align: center;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    Tangerang, <?php echo date('d F Y'); ?><br>
                    Pengawas / Instruktur<br><br><br><br><br>
                    (.............................................)
                </td>
            </tr>
        </table>

    </div>

</body>
</html>

<!-- file kedua -->

<?php
session_start();
require 'cek_admin.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$ujian_id = $_GET['id'] ?? 0;

// Ambil data ujian beserta data siswa
$stmt = $pdo->prepare("
    SELECT u.*, s.nama_siswa, s.kartu_peserta, s.kelas 
    FROM ujian_siswa u 
    JOIN siswa s ON u.siswa_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$ujian_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Data ujian tidak ditemukan.");
}

// Hitung durasi
$waktu_mulai = new DateTime($data['waktu_mulai']);
$waktu_selesai = new DateTime($data['waktu_selesai'] ?? $data['waktu_mulai']);
$durasi = $waktu_mulai->diff($waktu_selesai)->format('%h Jam %i Menit');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Nilai - <?php echo htmlspecialchars($data['nama_siswa']); ?></title>
    <style>
        /* === STYLE UNTUK LAYAR (UI/UX) === */
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 10px; }
        
        .header-action { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: 0.3s; }
        .btn-print { background: #28a745; color: white; }
        .btn-print:hover { background: #218838; }
        .btn-back { background: #6c757d; color: white; }
        .btn-back:hover { background: #5a6268; }

        .kop-surat { text-align: center; border-bottom: 3px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
        .kop-surat h2, .kop-surat h3, .kop-surat p { margin: 5px 0; }
        
        .info-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .table-info { width: 100%; border-collapse: collapse; }
        .table-info td { padding: 8px 0; font-size: 15px; border-bottom: 1px dashed #ddd;}
        .table-info td:first-child { font-weight: bold; width: 150px; color: #555; }
        
        .selfie-box { text-align: right; }
        .selfie-box img { max-width: 150px; border-radius: 8px; border: 2px solid #ddd; padding: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

        .score-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .card-stat { background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; border: 1px solid #eee; }
        .card-stat h4 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
        .card-stat .value { font-size: 28px; font-weight: bold; }
        .val-nilai { color: #007bff; }
        .val-benar { color: #28a745; }
        .val-salah { color: #dc3545; }
        .val-langgar { color: #fd7e14; }

        .signature-area { display: flex; justify-content: space-between; margin-top: 50px; text-align: center; }
        .signature-box { width: 200px; }
        .signature-line { margin-top: 70px; border-bottom: 1px solid #333; }

        /* === STYLE UNTUK CETAK KERTAS A4 === */
        @media print {
            @page { 
                size: A4 portrait; 
                margin: 20mm; /* Margin standar dokumen A4 */
            }
            
            body { background: white; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container { box-shadow: none; border: none; padding: 0; max-width: 100%; width: 100%; }
            
            /* Sembunyikan tombol saat dicetak */
            .no-print { display: none !important; }
            
            /* Pastikan background kartu nilai ikut tercetak */
            .card-stat { border: 1px solid #ccc; background-color: #f8f9fa !important; }
            
            /* Cegah elemen terpotong ke halaman 2 */
            .score-cards, .signature-area { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-action no-print">
            <a href="nilai.php" class="btn btn-back">⬅️ Kembali</a>
            <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Laporan (A4)</button>
        </div>

        <div class="kop-surat">
            <h2>LAPORAN HASIL UJIAN BERBASIS KOMPUTER (CBT)</h2>
            <h3>TAHUN PELAJARAN 2025/2026</h3>
        </div>

        <div class="info-grid">
            <div>
                <table class="table-info">
                    <tr>
                        <td>Nama Peserta</td>
                        <td>: <?php echo htmlspecialchars($data['nama_siswa']); ?></td>
                    </tr>
                    <tr>
                        <td>Nomor Peserta</td>
                        <td>: <?php echo htmlspecialchars($data['kartu_peserta']); ?></td>
                    </tr>
                    <tr>
                        <td>Kelas</td>
                        <td>: <?php echo htmlspecialchars($data['kelas']); ?></td>
                    </tr>
                    <tr>
                        <td>Mata Pelajaran</td>
                        <td>: <strong><?php echo htmlspecialchars($data['mata_pelajaran']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Waktu Mulai</td>
                        <td>: <?php echo date('d-m-Y H:i', strtotime($data['waktu_mulai'])); ?> WIB</td>
                    </tr>
                    <tr>
                        <td>Lama Pengerjaan</td>
                        <td>: <?php echo $durasi; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="selfie-box">
                <?php 
                $foto_path = "../assets/" . htmlspecialchars($data['foto_selfie']);
                if (file_exists($foto_path) && !empty($data['foto_selfie'])): 
                ?>
                    <img src="<?php echo $foto_path; ?>" alt="Foto Verifikasi Siswa">
                    <div style="font-size: 11px; color: #777; margin-top: 5px;">*Foto Verifikasi Sistem</div>
                <?php else: ?>
                    <div style="width:150px; height:200px; border:2px dashed #ccc; display:inline-flex; align-items:center; justify-content:center; color:#999; font-size: 12px; border-radius: 8px;">
                        Foto Tidak Tersedia
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="score-cards">
            <div class="card-stat">
                <h4>NILAI AKHIR</h4>
                <div class="value val-nilai"><?php echo (int)($data['nilai'] ?? 0); ?></div>
            </div>
            <div class="card-stat">
                <h4>JAWAB BENAR</h4>
                <div class="value val-benar"><?php echo (int)($data['benar'] ?? 0); ?></div>
            </div>
            <div class="card-stat">
                <h4>JAWAB SALAH</h4>
                <div class="value val-salah"><?php echo (int)($data['salah'] ?? 0); ?></div>
            </div>
            <div class="card-stat">
                <h4>PELANGGARAN</h4>
                <div class="value val-langgar"><?php echo (int)($data['jumlah_pelanggaran']); ?>x</div>
            </div>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                <p>Pengawas Ujian,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 5px; color: #555;">NIP. ............................</p>
            </div>
            
            <div class="signature-box">
                <p>Tangerang, <?php echo date('d F Y'); ?><br>Guru Mata Pelajaran,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 5px; color: #555;">NIP. ............................</p>
            </div>
        </div>

    </div>

</body>
</html>

<!-- file asli -->
 <?php

session_start();
require 'cek_admin.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: nilai.php");
    exit;
}

$ujian_id = (int)$_GET['id'];

$stmtInfo = $pdo->prepare("
SELECT
u.*,
s.nama_siswa,
s.kartu_peserta,
s.kelas
FROM ujian_siswa u
JOIN siswa s
ON s.id=u.siswa_id
WHERE u.id=?
");

$stmtInfo->execute([$ujian_id]);

$data = $stmtInfo->fetch();

if(!$data){
    die('Data tidak ditemukan');
}

$stmtJawaban = $pdo->prepare("
SELECT
    js.*,

    soal.deskripsi,
    soal.gambar,

    soal.pertanyaan,

    soal.opsi_a,
    soal.gambar_a,

    soal.opsi_b,
    soal.gambar_b,

    soal.opsi_c,
    soal.gambar_c,

    soal.opsi_d,
    soal.gambar_d,

    soal.opsi_e,
    soal.gambar_e,

    soal.kunci_jawaban

FROM jawaban_siswa js

JOIN soal
ON soal.id = js.soal_id

WHERE
    js.ujian_id = ?
    AND soal.mata_pelajaran = ?
");

$stmtJawaban->execute([
    $ujian_id,
    $data['mata_pelajaran']
]);

$jawaban = $stmtJawaban->fetchAll();
$total_soal   = count($jawaban);
$total_benar  = 0;
$total_salah  = 0;

foreach($jawaban as $j){

    if($j['status_benar']){
        $total_benar++;
    }else{
        $total_salah++;
    }

}
?>
<!DOCTYPE html>
<html>
<head>

<title>Detail Nilai</title>

<style>

body{
font-family:Arial;
background:#f4f7f6;
padding:20px;
}

.card{
background:white;
padding:20px;
border-radius:10px;
margin-bottom:20px;
box-shadow:0 2px 5px rgba(0,0,0,.1);
}

.benar{
color:green;
font-weight:bold;
}

.salah{
color:red;
font-weight:bold;
}

.soal-header{
    background:#007bff;
    color:white;
    padding:10px 15px;
    border-radius:5px;
    margin-bottom:15px;
    font-weight:bold;
}

.opsi-box{
    border:1px solid #ddd;
    border-radius:6px;
    padding:12px;
    margin-bottom:10px;
}

.opsi-benar{
    background:#d4edda;
    border:2px solid #28a745;
}

.opsi-dipilih{
    background:#fff3cd;
    border:2px solid #ffc107;
}

.opsi-salah{
    background:#f8d7da;
    border:2px solid #dc3545;
}

.preview-img{
    max-width:300px;
    margin-top:10px;
    border-radius:5px;
}

.badge-benar{
    background:#28a745;
    color:white;
    padding:5px 10px;
    border-radius:5px;
}

.badge-salah{
    background:#dc3545;
    color:white;
    padding:5px 10px;
    border-radius:5px;
}

</style>

</head>
<body>

<div class="card">

<h2>Detail Hasil Ujian</h2>
<div style="margin-bottom:20px;">

    <a
        href="nilai.php"
        style="
            background:#6c757d;
            color:white;
            padding:10px 15px;
            border-radius:5px;
            text-decoration:none;
            font-weight:bold;
        "
    >
        ← Kembali ke Rekap Nilai
    </a>

</div>

<p>
<b>Nama :</b>
<?php echo htmlspecialchars($data['nama_siswa']); ?>
</p>

<p>
<b>Kelas :</b>
<?php echo htmlspecialchars($data['kelas']); ?>
</p>

<p>
<b>Mapel :</b>
<?php echo htmlspecialchars($data['mata_pelajaran']); ?>
</p>

<p>
<b>Nilai :</b>
<?php echo number_format($data['nilai'],2); ?>
</p>

<p>
<b>Pelanggaran :</b>
<?php echo $data['jumlah_pelanggaran']; ?>
</p>

<p>
<b>Total Soal :</b>
<?php echo $total_soal; ?>
</p>

<p>
<b>Benar :</b>
<span style="color:green;font-weight:bold;">
<?php echo $total_benar; ?>
</span>
</p>

<p>
<b>Salah :</b>
<span style="color:red;font-weight:bold;">
<?php echo $total_salah; ?>
</span>
</p>

</div>

<?php
$no=1;

foreach($jawaban as $j):
?>

<div class="card">

<h3>
Soal <?php echo $no++; ?>
</h3>

<?php if(!empty($j['deskripsi'])): ?>

<div
style="
background:#f9f9f9;
padding:15px;
border-left:4px solid #17a2b8;
margin-bottom:15px;
">
<?php echo $j['deskripsi']; ?>
</div>

<?php endif; ?>

<?php if(!empty($j['gambar'])): ?>

<img
src="../uploads/<?php echo $j['gambar']; ?>"
class="preview-img">

<?php endif; ?>

<?php

$opsi = [
'A' => $j['opsi_a'],
'B' => $j['opsi_b'],
'C' => $j['opsi_c'],
'D' => $j['opsi_d'],
'E' => $j['opsi_e']
];

foreach($opsi as $huruf => $teks):

$class='opsi-box';

if(
    $huruf ==
    $j['kunci_jawaban']
){
    $class .= ' opsi-benar';
}

if(
    $huruf ==
    $j['jawaban_dipilih']
){
    $class .= ' opsi-dipilih';
}

if(
    $huruf ==
    $j['jawaban_dipilih']
    &&
    $huruf !=
    $j['kunci_jawaban']
){
    $class .= ' opsi-salah';
}

?>

<div class="<?php echo $class; ?>">

<strong>
<?php echo $huruf; ?>.
</strong>

<?php echo $teks; ?>

</div>

<?php endforeach; ?>

<p>
<?php echo $j['pertanyaan']; ?>
</p>

<hr>

<p>
<b>Jawaban Siswa :</b>
<?php echo $j['jawaban_dipilih']; ?>
</p>

<p>
<b>Kunci Jawaban :</b>
<?php echo $j['kunci_jawaban']; ?>
</p>

<p>

<b>Status :</b>

<?php if($j['status_benar']): ?>

<span class="badge-benar">
✓ BENAR
</span>

<?php else: ?>

<span class="badge-salah">
✗ SALAH
</span>

<?php endif; ?>

</p>

</div>

<?php endforeach; ?>

<div style="text-align:center;margin:30px 0;">

    <a
        href="nilai.php"
        style="
            background:#007bff;
            color:white;
            padding:12px 20px;
            border-radius:5px;
            text-decoration:none;
            font-weight:bold;
        "
    >
        ← Kembali
    </a>

</div>