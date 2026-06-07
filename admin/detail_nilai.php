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
        :root {
            --primary: #4361ee;
            --primary-hover: #3a0ca3;
            --success: #10b981;
            --success-bg: #d1fae5;
            --success-text: #065f46;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --danger-text: #991b1b;
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            --warning-text: #92400e;
            --bg-body: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border: #e2e8f0;
        }

        /* === STYLE UNTUK LAYAR (UI/UX) === */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            margin: 0; 
            padding: 30px 20px; 
        }
        
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #fff; 
            padding: 40px; 
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05); 
            border-radius: 16px; 
            border: 1px solid var(--border);
        }
        
        .header-action { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 30px; 
            gap: 15px;
        }
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 14px; 
            transition: all 0.2s ease; 
        }
        .btn-print { background: var(--success); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-print:hover { background: var(--success-hover); transform: translateY(-1px); }
        .btn-back { background: var(--text-muted); color: white; }
        .btn-back:hover { background: #334155; transform: translateY(-1px); }

        .kop-surat { 
            text-align: center; 
            border-bottom: 3px double var(--text-main); 
            padding-bottom: 20px; 
            margin-bottom: 35px; 
        }
        .kop-surat h2 { margin: 0 0 5px 0; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; }
        .kop-surat h3 { margin: 0; font-size: 16px; color: var(--text-muted); font-weight: 600; }
        
        .info-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 35px; align-items: start; }
        .table-info { width: 100%; border-collapse: collapse; }
        .table-info td { padding: 10px 0; font-size: 15px; border-bottom: 1px dashed var(--border); color: var(--text-main); }
        .table-info tr:last-child td { border-bottom: none; }
        .table-info td:first-child { font-weight: 700; width: 160px; color: var(--text-muted); }
        
        .selfie-box { text-align: right; display: flex; flex-direction: column; align-items: flex-end; }
        .selfie-box img { 
            width: 140px; 
            height: 180px; 
            border-radius: 12px; 
            border: 2px solid var(--border); 
            padding: 4px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            object-fit: cover;
            background: #fff;
        }

        .score-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 40px; }
        .card-stat { 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 20px 15px; 
            text-align: center; 
            border: 1px solid var(--border); 
            transition: all 0.2s;
        }
        .card-stat:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .card-stat h4 { margin: 0 0 8px 0; font-size: 12px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; }
        .card-stat .value { font-size: 32px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .val-nilai { color: var(--primary); }
        .val-benar { color: var(--success); }
        .val-salah { color: var(--danger); }
        .val-langgar { color: var(--warning); }

        .signature-area { display: flex; justify-content: space-between; margin-top: 60px; text-align: center; font-size: 15px; }
        .signature-box { width: 220px; }
        .signature-line { margin-top: 80px; border-bottom: 1px solid var(--text-main); }

        /* === STYLE UNTUK REVIEW SOAL === */
        .review-section { margin-top: 50px; padding-top: 10px; }
        .review-title { 
            background: #1e293b; 
            color: white; 
            padding: 14px 20px; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 700;
            margin-bottom: 25px; 
            text-align: center;
            letter-spacing: 0.5px;
        }
        .soal-item { 
            margin-bottom: 30px; 
            padding: 25px; 
            background: #fff; 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            page-break-inside: avoid; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.01);
        }
        .soal-header-num {
            font-weight: 800; 
            font-size: 15px; 
            color: var(--primary); 
            border-bottom: 1px solid var(--border); 
            padding-bottom: 10px; 
            margin-bottom: 15px;
        }
        .soal-deskripsi { 
            background: #f8fafc; 
            padding: 15px 20px; 
            border-left: 4px solid #0ea5e9; 
            margin-bottom: 15px; 
            font-size: 14px; 
            color: var(--text-muted); 
            border-radius: 4px;
            line-height: 1.6;
        }
        .soal-pertanyaan { font-size: 16px; margin-bottom: 20px; line-height: 1.7; font-weight: 600; color: var(--text-main); }
        
        .alert-kosong { 
            background: var(--danger-bg); 
            color: var(--danger-text); 
            padding: 10px 16px; 
            border-radius: 8px; 
            font-size: 13px; 
            margin-bottom: 15px; 
            font-weight: 700; 
            border: 1px solid #fca5a5; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
        }
        
        .opsi-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
        .opsi-item { 
            padding: 12px 18px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            display: flex; 
            align-items: flex-start;
            gap: 12px; 
            font-size: 15px; 
            background: #fff;
            transition: all 0.2s;
        }
        
        /* Pewarnaan Kunci Jawaban & Jawaban Siswa */
        .opsi-benar { background: var(--success-bg) !important; border-color: #a7f3d0 !important; color: var(--success-text); }
        .opsi-salah { background: var(--danger-bg) !important; border-color: #fca5a5 !important; color: var(--danger-text); }
        
        .pill-badge {
            margin-left: auto; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 800; 
            white-space: nowrap; 
            align-self: center;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .badge-kunci { background: var(--success); color: white; }
        .badge-salah { background: var(--danger); color: white; }
        
        .img-soal { max-width: 280px; max-height: 220px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 15px; display: block; object-fit: contain; }

        /* === STYLE UNTUK PREVIEW GAMBAR (MODAL) === */
        .modal-zoom { display: none; position: fixed; z-index: 999999; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.9); justify-content: center; align-items: center; cursor: zoom-out; backdrop-filter: blur(4px); }
        .modal-zoom img { max-width: 85%; max-height: 85%; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); object-fit: contain; }
        .img-zoomable { cursor: zoom-in; transition: all 0.2s; }
        .img-zoomable:hover { opacity: 0.85; border-color: var(--primary) !important; }

        /* === STYLE UNTUK CETAK KERTAS A4 === */
        @media print {
            @page { size: A4 portrait; margin: 12mm 15mm; }
            body { background: white; margin: 0; padding: 0; color: #000; }
            .container { box-shadow: none; border: none; padding: 0; max-width: 100%; width: 100%; }
            .no-print, .modal-zoom { display: none !important; }
            .card-stat { border: 1px solid #94a3b8 !important; background-color: #f8fafc !important; transform: none !important; box-shadow: none !important; }
            .soal-item { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
            .opsi-item { border: 1px solid #e2e8f0 !important; }
            .opsi-benar { background-color: #e6f4ea !important; border-color: #b7e1cd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .opsi-salah { background-color: #fce8e6 !important; border-color: #f9cb9c !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .score-cards, .signature-area, .soal-item { page-break-inside: avoid; }
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
            <a href="nilai.php" class="btn btn-back">⬅️ Kembali Ke Daftar</a>
            <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Laporan Resmi (A4)</button>
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
                        <td>: <strong style="color: var(--primary);"><?php echo htmlspecialchars($data['mata_pelajaran']); ?></strong></td>
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
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px; font-weight: 600;">*Foto Verifikasi Sistem</div>
                <?php else: ?>
                    <div style="width:140px; height:180px; border:2px dashed var(--border); display:inline-flex; align-items:center; justify-content:center; color:var(--text-muted); font-size: 12px; border-radius: 12px; float: right; background: #f8fafc; font-weight: 600;">
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
            <div class="card-stat" style="<?php echo ((int)$data['jumlah_pelanggaran'] > 0) ? '' : 'opacity: 0.4;'; ?>">
                <h4>PELANGGARAN</h4>
                <div class="value val-langgar"><?php echo (int)($data['jumlah_pelanggaran']); ?>x</div>
            </div>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                <p>Pengawas Ujian,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 8px; color: var(--text-muted); font-size: 13px;">NIP. ............................</p>
            </div>
            
            <div class="signature-box">
                <p>Jakarta, <?php echo date('d F Y'); ?><br>Guru Mata Pelajaran,</p>
                <div class="signature-line"></div>
                <p style="margin-top: 8px; color: var(--text-muted); font-size: 13px;">NIP. ............................</p>
            </div>
        </div>

        <div class="page-break"></div> 

        <div class="review-section">
            <div class="review-title">📚 Lampiran: Detail Soal & Analisis Jawaban</div>
            
            <?php if (empty($daftar_soal)): ?>
                <p style="text-align: center; color: var(--text-muted); font-weight: bold; padding: 20px;">Data soal tidak ditemukan untuk mata pelajaran ini.</p>
            <?php else: ?>
                <?php foreach ($daftar_soal as $index => $soal): 
                    // Ambil jawaban spesifik siswa untuk ID soal ini
                    $soal_id = $soal['id'];
                    $jawaban_terpilih = $jawaban_siswa[$soal_id] ?? '';
                ?>
                    <div class="soal-item">
                        <div class="soal-header-num">
                            <span>Soal Nomor <?php echo $index + 1; ?></span>
                        </div>

                        <?php if (empty($jawaban_terpilih)): ?>
                            <?php if ((int)$data['jumlah_pelanggaran'] > 0): ?>
                                <div class="alert-kosong">⚠️ Siswa tidak menjawab soal ini karena terkena <i>Pelanggaran / Blokir</i></div>
                            <?php else: ?>
                                <div class="alert-kosong" style="background: #e2e8f0; color: #334155; border-color: #cbd5e1;">⚠️ Siswa tidak menjawab soal ini (Dikosongkan)</div>
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
                                    $badge = '<span class="pill-badge badge-kunci">✔️ Kunci & Jawaban Siswa</span>';
                                } elseif ($is_kunci && !$is_dijawab) {
                                    $class_li .= " opsi-benar"; 
                                    $badge = '<span class="pill-badge badge-kunci">✔️ Kunci Jawaban</span>';
                                } elseif (!$is_kunci && $is_dijawab) {
                                    $class_li .= " opsi-salah"; 
                                    $badge = '<span class="pill-badge badge-salah">❌ Jawaban Siswa</span>';
                                }
                                
                                $teks_opsi = $soal['opsi_' . strtolower($huruf)];
                                $gbr_opsi = $soal['gambar_' . strtolower($huruf)];

                                if (empty($teks_opsi) && empty($gbr_opsi)) continue;
                            ?>
                                <li class="<?php echo $class_li; ?>">
                                    <span style="font-weight: 800; width: 25px; color: var(--primary);"><?php echo $huruf; ?>.</span>
                                    <div style="flex-grow: 1; line-height: 1.5;">
                                        <?php if (!empty($teks_opsi)) echo htmlspecialchars_decode($teks_opsi); ?>
                                        <?php if (!empty($gbr_opsi)): ?>
                                            <div style="margin-top: 8px;">
                                                <img src="../uploads/<?php echo htmlspecialchars($gbr_opsi); ?>" class="img-soal img-zoomable" style="max-height: 90px; margin-bottom:0;" onclick="bukaPreview(this.src)" title="Klik untuk memperbesar">
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