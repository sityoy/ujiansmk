<?php
// session_start();
require 'cek_admin.php';

// Pastikan hanya admin yang bisa mengakses
// if (!isset($_SESSION['admin_id'])) { 
//     header("Location: login.php"); 
//     exit; 
// }

$ujian_id = $_GET['id'] ?? 0;

// 1. Ambil data ujian beserta data siswa
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

// 2. Hitung durasi pengerjaan
$waktu_mulai = new DateTime($data['waktu_mulai']);
$waktu_selesai = new DateTime($data['waktu_selesai'] ?? $data['waktu_mulai']);
$durasi = $waktu_mulai->diff($waktu_selesai)->format('%h Jam %i Menit %s Detik');

// 3. Logika Pembersihan Nama Kelas
$kelas_raw = strtoupper(trim($data['kelas']));
if (strpos($kelas_raw, 'XII') === 0) { $kelas_clean = 'XII'; } 
elseif (strpos($kelas_raw, 'XI') === 0) { $kelas_clean = 'XI'; } 
else { $kelas_clean = 'X'; }

// 4. Ambil Daftar Soal sesuai Mapel & Kelas (Urut ASC)
$stmtSoal = $pdo->prepare("SELECT * FROM soal WHERE mata_pelajaran = ? AND kelas = ? ORDER BY id ASC");
$stmtSoal->execute([$data['mata_pelajaran'], $kelas_clean]);
$daftar_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

// 5. Dekode Jawaban Siswa
$stmtJawaban = $pdo->prepare("SELECT soal_id, jawaban_dipilih FROM jawaban_siswa WHERE ujian_id = ?");
$stmtJawaban->execute([$ujian_id]);
$jawaban_siswa = [];
foreach ($stmtJawaban->fetchAll(PDO::FETCH_ASSOC) as $jawaban) {
    $jawaban_siswa[(int) $jawaban['soal_id']] = $jawaban['jawaban_dipilih'];
}

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
        .container { max-width: 850px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 10px; }
        
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
        .selfie-box img { max-width: 150px; max-height: 200px; border-radius: 8px; border: 2px solid #ddd; padding: 5px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); object-fit: cover;}

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

        /* === STYLE UNTUK REVIEW SOAL === */
        .review-section { margin-top: 50px; padding-top: 20px; }
        .review-title { background: #333; color: white; padding: 10px 15px; border-radius: 5px; font-size: 18px; margin-bottom: 20px; text-align: center;}
        .soal-item { margin-bottom: 25px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; page-break-inside: avoid; }
        .soal-deskripsi { background: #f9f9f9; padding: 12px; border-left: 4px solid #17a2b8; margin-bottom: 15px; font-size: 14px; color: #444; }
        .soal-pertanyaan { font-size: 16px; margin-bottom: 15px; line-height: 1.6; }
        
        .alert-kosong { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; font-size: 14px; margin-bottom: 15px; font-weight: bold; border: 1px solid #f5c6cb; display: inline-block; }
        
        .opsi-list { list-style: none; padding: 0; margin: 0; }
        .opsi-item { margin-bottom: 8px; padding: 10px 15px; border: 1px solid #eee; border-radius: 5px; display: flex; gap: 10px; font-size: 15px; background: #fafafa;}
        
        /* Pewarnaan Kunci Jawaban & Jawaban Siswa */
        .opsi-benar { background: #d4edda !important; border-color: #c3e6cb !important; font-weight: bold; color: #155724; }
        .opsi-salah { background: #f8d7da !important; border-color: #f5c6cb !important; font-weight: bold; color: #721c24; }
        
        .badge-kunci { margin-left: auto; background: #28a745; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; white-space: nowrap; height: fit-content; }
        .badge-salah { margin-left: auto; background: #dc3545; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; white-space: nowrap; height: fit-content; }
        
        .img-soal { max-width: 250px; max-height: 200px; border-radius: 5px; border: 1px solid #ccc; margin-bottom: 10px; display: block; }

        /* === STYLE UNTUK PREVIEW GAMBAR (MODAL) === */
        .modal-zoom { display: none; position: fixed; z-index: 999999; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); justify-content: center; align-items: center; cursor: zoom-out; }
        .modal-zoom img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); object-fit: contain; }
        .img-zoomable { cursor: zoom-in; transition: transform 0.2s; }
        .img-zoomable:hover { transform: scale(1.03); opacity: 0.9; border-color: #007bff; }

        /* === STYLE UNTUK CETAK KERTAS A4 === */
        @media print {
            @page { size: A4 portrait; margin: 15mm; }
            body { background: white; margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container { box-shadow: none; border: none; padding: 0; max-width: 100%; width: 100%; }
            .no-print, .modal-zoom { display: none !important; }
            .card-stat { border: 1px solid #ccc !important; background-color: #f8f9fa !important; }
            .score-cards, .signature-area { page-break-inside: avoid; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>

    <div id="previewModal" class="modal-zoom" onclick="tutupPreview()">
        <img id="imgPreview" src="" alt="Pratinjau Gambar">
    </div>

    <div class="container">
        <div class="header-action no-print">
            <a href="nilai.php" class="btn btn-back">⬅️ Kembali</a>
            <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Laporan & Soal (A4)</button>
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
                        <td>: <?php echo date('d-m-Y H:i:s', strtotime($data['waktu_mulai'])); ?> WIB</td>
                    </tr>
                    <tr>
                        <td>Waktu Selesai</td>
                        <td>: <?php echo date('d-m-Y H:i:s', strtotime($data['waktu_selesai'])); ?> WIB</td>
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
                    <img src="<?php echo $foto_path; ?>" alt="Foto Verifikasi Siswa" class="img-zoomable" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar">
                    <div style="font-size: 11px; color: #777; margin-top: 5px;">*Foto Verifikasi Sistem</div>
                <?php else: ?>
                    <div style="width:150px; height:200px; border:2px dashed #ccc; display:inline-flex; align-items:center; justify-content:center; color:#999; font-size: 12px; border-radius: 8px; float: right;">
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
            <?php if ((int)$data['jumlah_pelanggaran'] > 0): ?>
            <div class="card-stat">
                <h4>PELANGGARAN</h4>
                <div class="value val-langgar"><?php echo (int)($data['jumlah_pelanggaran']); ?>x</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                <p>Pengawas Ujian,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 5px; color: #555;">NIP. ............................</p>
            </div>
            
            <div class="signature-box">
                <p>Jakarta, <?php echo date('d F Y'); ?><br>Guru Mata Pelajaran,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 5px; color: #555;">NIP. ............................</p>
            </div>
        </div>

        <div class="page-break"></div> 

        <div class="review-section">
            <div class="review-title">📚 Lampiran: Detail Soal & Kunci Jawaban</div>
            
            <?php if (empty($daftar_soal)): ?>
                <p style="text-align: center; color: #777;">Data soal tidak ditemukan untuk mata pelajaran ini.</p>
            <?php else: ?>
                <?php foreach ($daftar_soal as $index => $soal): 
                    // Ambil jawaban spesifik siswa untuk ID soal ini
                    $soal_id = $soal['id'];
                    $jawaban_terpilih = $jawaban_siswa[$soal_id] ?? '';
                ?>
                    <div class="soal-item">
                        <div style="font-weight: bold; margin-bottom: 10px; color: #007bff; border-bottom: 1px solid #eee; padding-bottom: 5px; display: flex; justify-content: space-between;">
                            <span>Soal No. <?php echo $index + 1; ?></span>
                        </div>

                        <?php if (empty($jawaban_terpilih)): ?>
                            <?php if ((int)$data['jumlah_pelanggaran'] > 0): ?>
                                <div class="alert-kosong">⚠️ Siswa tidak menjawab soal ini karena terkena <i style="color:red;">Pelanggaran</i></div>
                            <?php else: ?>
                                <div class="alert-kosong" style="background: #e2e3e5; color: #383d41; border-color: #d6d8db;">⚠️ Siswa tidak menjawab soal ini (Dikosongkan)</div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($soal['deskripsi'])): ?>
                            <div class="soal-deskripsi">
                                <?php echo htmlspecialchars_decode($soal['deskripsi']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($soal['gambar'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($soal['gambar']); ?>" class="img-soal img-zoomable" alt="Gambar Soal" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar">
                        <?php endif; ?>

                        <div class="soal-pertanyaan">
                            <?php echo htmlspecialchars_decode($soal['pertanyaan']); ?>
                        </div>

                        <ul class="opsi-list">
                            <?php 
                            $opsi_huruf = ['A', 'B', 'C', 'D', 'E'];
                            foreach ($opsi_huruf as $huruf): 
                                $is_kunci = (strtoupper(trim($soal['kunci_jawaban'])) === $huruf);
                                $is_dijawab = (strtoupper(trim($jawaban_terpilih)) === $huruf);
                                
                                $class_li = "opsi-item";
                                $badge = "";

                                if ($is_kunci && $is_dijawab) {
                                    $class_li .= " opsi-benar"; 
                                    $badge = '<span class="badge-kunci">✔️ Kunci & Jawaban Siswa</span>';
                                } elseif ($is_kunci && !$is_dijawab) {
                                    $class_li .= " opsi-benar"; 
                                    $badge = '<span class="badge-kunci">✔️ Kunci Jawaban</span>';
                                } elseif (!$is_kunci && $is_dijawab) {
                                    $class_li .= " opsi-salah"; 
                                    $badge = '<span class="badge-salah">❌ Jawaban Siswa</span>';
                                }
                                
                                $teks_opsi = $soal['opsi_' . strtolower($huruf)];
                                $gbr_opsi = $soal['gambar_' . strtolower($huruf)];

                                if (empty($teks_opsi) && empty($gbr_opsi)) continue;
                            ?>
                                <li class="<?php echo $class_li; ?>">
                                    <span style="font-weight: bold; width: 25px;"><?php echo $huruf; ?>.</span>
                                    <div style="flex-grow: 1;">
                                        <?php if (!empty($teks_opsi)) echo htmlspecialchars_decode($teks_opsi); ?>
                                        <?php if (!empty($gbr_opsi)): ?>
                                            <div style="margin-top: 5px;">
                                                <img src="../uploads/<?php echo htmlspecialchars($gbr_opsi); ?>" class="img-soal img-zoomable" style="max-height: 80px;" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo $badge; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function bukaPreview(srcGambar) {
            document.getElementById('imgPreview').src = srcGambar;
            document.getElementById('previewModal').style.display = 'flex';
        }

        function tutupPreview() {
            document.getElementById('previewModal').style.display = 'none';
        }
    </script>
</body>
</html>