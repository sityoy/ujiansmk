<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

if (!isset($_SESSION['siswa_id'], $_SESSION['ujian_id'], $_SESSION['jadwal_id'], $_SESSION['mapel_aktif'], $_SESSION['kelas'])) {
    header("Location: login.php");
    exit;
}

$ujian_id = $_SESSION['ujian_id'];
$stmtCek = $pdo->prepare("SELECT jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmtCek->execute([$ujian_id]);
$dataUjian = $stmtCek->fetch();

if (!$dataUjian) {
    unset($_SESSION['ujian_id']);
    header("Location: selfie.php");
    exit;
}

// Jika sudah melebihi atau sama dengan 2 pelanggaran, langsung tendang ke halaman selesai
if ($dataUjian['jumlah_pelanggaran'] >= 2) {
    header("Location: selesai_ujian.php");
    exit;
}

$jadwal_id = $_SESSION['jadwal_id'];
$stmtJadwal = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
$stmtJadwal->execute([$jadwal_id]);
$jadwal = $stmtJadwal->fetch();

if (!$jadwal) {
    unset($_SESSION['jadwal_id'], $_SESSION['mapel_aktif'], $_SESSION['urutan_soal']);
    $_SESSION['error_login'] = "Jadwal ujian tidak ditemukan. Silakan pilih jadwal ulang.";
    header("Location: login.php");
    exit;
}

$sisa_detik = strtotime($jadwal['waktu_selesai']) - time();
$mapel_aktif = $_SESSION['mapel_aktif'];

if ($sisa_detik <= 0) {
    header("Location: selesai_ujian.php");
    exit;
}

// LOGIKA KELAS
$kelas = strtoupper(trim($_SESSION['kelas']));
if (strpos($kelas, 'XII') === 0) { $kelas = 'XII'; } 
elseif (strpos($kelas, 'XI') === 0) { $kelas = 'XI'; } 
else { $kelas = 'X'; }

    if (!isset($_SESSION['urutan_soal'])) {
    // Tambahkan ORDER BY id ASC agar jika tidak diacak, urutannya sesuai saat input di tambah_soal.php
    $stmtSoal = $pdo->prepare("SELECT * FROM soal WHERE mata_pelajaran = ? AND kelas = ? ORDER BY id ASC");
    $stmtSoal->execute([$mapel_aktif, $kelas]);
    $daftar_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);
    
    // 1. Tentukan mapel apa saja yang SOALNYA TIDAK BOLEH DIACAK
    // (Perhatikan huruf besar/kecilnya, harus sama persis dengan yang ada di database/tambah_soal)
    $mapel_urutan_tetap = ['Bahasa Indonesia']; 
    
    // 2. Logika Pengecekan: Jika mapel_aktif BUKAN mapel di atas, maka ACAK soalnya!
    if (!in_array($mapel_aktif, $mapel_urutan_tetap)) {
        shuffle($daftar_soal);
    }
    
    $_SESSION['urutan_soal'] = $daftar_soal;
} else {
    $daftar_soal = $_SESSION['urutan_soal'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lembar Ujian - <?php echo htmlspecialchars($mapel_aktif); ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 10px; 
            user-select: none; -moz-user-select: none; -webkit-user-select: none; -ms-user-select: none;
            -webkit-touch-callout: none; overscroll-behavior-y: none; 
        }
        
        #fullscreen-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 9999; text-align: center; padding: 20px; box-sizing: border-box; }
        #btn-fullscreen { background: #28a745; color: white; padding: 15px 30px; font-size: 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 25px; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        #btn-fullscreen:hover { background: #1e7e34; }
        #konten-ujian { display: none; } 
        #layar-hitam { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: black; z-index: 100000; color: red; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 20px;}
        #custom-alert { display: none; position: fixed; top: 70px; left: 50%; transform: translateX(-50%); background: #dc3545; color: white; padding: 15px 25px; border-radius: 5px; z-index: 100001; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: bold; text-align: center; max-width: 90%; line-height: 1.5; }
        #custom-confirm { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); z-index: 100002; flex-direction: column; justify-content: center; align-items: center; }
        .confirm-box { background: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; }
        .btn-ya { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-size: 16px; cursor: pointer; margin-right: 10px; font-weight: bold;}
        .btn-batal { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-size: 16px; cursor: pointer; font-weight: bold;}

        .header-info { background: #333; color: white; padding: 12px 20px; position: fixed; top: 0; left: 0; right: 0; display: flex; justify-content: space-between; align-items: center; font-weight: bold; z-index: 1000; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .container { margin-top: 75px; max-width: 800px; margin-left: auto; margin-right: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .soal-box { margin-bottom: 35px; border-bottom: 1px solid #ddd; padding-bottom: 25px; }
        .opsi { margin: 10px 0; font-size: 16px; cursor: pointer; display: flex; align-items: flex-start; padding: 12px 15px; border: 1px solid #eee; border-radius: 5px; transition: 0.2s; background: #fff; }
        .opsi:hover { background: #f0f8ff; border-color: #007bff; }
        .opsi input[type="radio"] { margin-right: 15px; margin-top: 4px; transform: scale(1.2); cursor: pointer;}
        .btn-submit { background: #d9534f; color: white; border: none; padding: 15px; width: 100%; font-size: 18px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .btn-submit:hover { background: #c9302c; }
        .mapel-badge { background: #ffc107; color: #333; padding: 3px 8px; border-radius: 3px; margin-left: 10px; font-size: 12px; }
        .modal-zoom { display: none; position: fixed; z-index: 200000; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.9); justify-content: center; align-items: center; cursor: pointer; }
        .modal-zoom img { max-width: 95%; max-height: 95%; border-radius: 4px; box-shadow: 0 0 20px rgba(255,255,255,0.2); }
    </style>
</head>
<body oncontextmenu="return false;" oncopy="return false;" onpaste="return false;" onkeydown="return mencegahAksi(event);">

    <div id="previewModal" class="modal-zoom" onclick="tutupPreview()">
        <img id="imgPreview" src="" alt="Pratinjau Gambar">
    </div>

    <div id="layar-hitam">
        <h2>Peringatan Sistem!</h2>
        <p>Anda terdeteksi melanggar aturan ujian! Sesi Anda dibekukan.</p>
    </div>

    <div id="custom-alert"><span id="custom-alert-text">Pesan</span></div>
    
    <div id="custom-confirm">
        <div class="confirm-box">
            <h3 style="color: #333; margin-top: 0;">Konfirmasi Selesai</h3>
            <p style="color: #555; margin-bottom: 20px;">Yakin ingin menyelesaikan ujian dan mengirimkan jawaban?</p>
            <button class="btn-ya" onclick="submitFormFinal()">Ya, Kirim</button>
            <button class="btn-batal" onclick="tutupConfirm()">Batal</button>
        </div>
    </div>
    
    <div id="fullscreen-overlay">
        <h2 style="color: #ffc107; font-size: 28px;">Persiapan Ujian: <?php echo htmlspecialchars($mapel_aktif); ?></h2>
        <p style="max-width: 600px; line-height: 1.6; font-size: 16px;">
            Sistem mendeteksi aktivitas Layar Terbelah (Split Screen), Gelembung Chat (Bubble), dan Notifikasi. <br><br>
            <strong>2 kali pelanggaran = ujian otomatis selesai.</strong>
        </p>
        <button id="btn-fullscreen">Mulai Ujian</button>
    </div>

    <div id="konten-ujian">
        <div class="header-info">
            <div>
                No: <?php echo htmlspecialchars($_SESSION['kartu_peserta']); ?><br> 
                Nama: <?php echo htmlspecialchars($_SESSION['nama_siswa']); ?>
            </div>
            <div>
                Kelas: <?php echo htmlspecialchars($_SESSION['kelas']); ?><br>
                Mapel: <span class="mapel-badge"><?php echo htmlspecialchars($mapel_aktif); ?></span>
            </div>
            <div style="font-size: 15px;">Waktu: <span id="timer" style="color: #ffcccc; font-weight: bold;">...</span></div>
            <div style="color: #ff9f43; font-size: 15px;">Pelanggaran: <span id="pelanggaran-count"><?php echo $dataUjian['jumlah_pelanggaran']; ?></span>/2</div>
        </div>

        <div class="container">
            <form id="form-ujian" action="simpan_jawaban.php" method="POST">
                <?php if (empty($daftar_soal)): ?>
                    <p style="text-align:center; color: #555;">Belum ada soal.</p>
                <?php else: ?>
                    <?php $no = 1; foreach ($daftar_soal as $s): ?>
                        <div class="soal-box">
                            <p style="font-size: 18px; color: #007bff; border-bottom: 2px solid #007bff; display: inline-block; padding-bottom: 5px; margin-top:0;"><strong>Soal No. <?php echo $no++; ?></strong></p>
                            
                            <?php if(!empty($s['deskripsi'])): ?>
                                <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #17a2b8; margin-bottom: 15px; overflow-x: auto; border-radius: 4px; font-size: 15px;">
                                    <?php echo $s['deskripsi']; ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($s['gambar'])): ?>
                                <div style="text-align: left; margin-bottom: 15px;">
                                    <img src="../uploads/<?php echo htmlspecialchars($s['gambar']); ?>" style="max-width:100%; cursor:pointer;" onclick="bukaPreview(this.src)">
                                </div>
                            <?php endif; ?>

                            <div style="margin-bottom: 20px; font-size: 16px; line-height: 1.6;">
                                <?php echo $s['pertanyaan']; ?>
                            </div>
                            
                            <?php 
                                $opsi = ['A' => ['teks' => $s['opsi_a'], 'gbr' => $s['gambar_a']], 'B' => ['teks' => $s['opsi_b'], 'gbr' => $s['gambar_b']], 'C' => ['teks' => $s['opsi_c'], 'gbr' => $s['gambar_c']], 'D' => ['teks' => $s['opsi_d'], 'gbr' => $s['gambar_d']], 'E' => ['teks' => $s['opsi_e'], 'gbr' => $s['gambar_e']]];
                                $keys = array_keys($opsi);
                                shuffle($keys);
                                $huruf_tampil = ['A', 'B', 'C', 'D', 'E'];
                                
                                foreach($keys as $index => $k): 
                            ?>
                                <label class="opsi">
                                    <input type="radio" name="jawaban[<?php echo $s['id']; ?>]" value="<?php echo $k; ?>"> 
                                    <div style="display: flex; flex-direction: column;">
                                        <span><strong><?php echo $huruf_tampil[$index]; ?>.</strong> <?php echo $opsi[$k]['teks']; ?></span>
                                        <?php if(!empty($opsi[$k]['gbr'])): ?>
                                            <div style="margin-top: 10px; margin-left: 20px;">
                                                <img src="../uploads/<?php echo htmlspecialchars($opsi[$k]['gbr']); ?>" style="max-height: 120px; border-radius: 4px; border: 1px solid #ccc; cursor: zoom-in;" onclick="bukaPreview(this.src)">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-submit" onclick="bukaConfirm()">Selesai & Kirim Jawaban</button>
                <?php endif; ?>
            </form>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        let pelanggaran = <?php echo $dataUjian['jumlah_pelanggaran']; ?>;
        const maxPelanggaran = 2; 

        // PENGAWASAN SELALU AKTIF!
        let isPengawasanAktif = false; 
        let isUjianFullscreen = false; 
        let isSubmitting = false; 

        const overlay = document.getElementById('fullscreen-overlay');
        const kontenUjian = document.getElementById('konten-ujian');
        const elem = document.documentElement;

        function mencegahAksi(e) {
            // Blokir Inspeksi Elemen dll
            if(e.keyCode === 123 || (e.ctrlKey && e.shiftKey && e.keyCode === 73)) {
                e.preventDefault(); return false;
            }
        }

        function tampilkanAlert(pesan) {
            const alertBox = document.getElementById('custom-alert');
            document.getElementById('custom-alert-text').innerHTML = pesan;
            alertBox.style.display = 'block';
            setTimeout(() => { alertBox.style.display = 'none'; }, 4500);
        }

        function bukaConfirm() {
            // === 1. PENGECEKAN JAWABAN KOSONG (TIDAK BISA DIKIRIM JIKA ADA YG KOSONG) ===
            const totalSoal = <?php echo count($daftar_soal); ?>;
            const jawabanTerisi = document.querySelectorAll('input[type="radio"]:checked').length;

            if (jawabanTerisi < totalSoal) {
                let belumDijawab = totalSoal - jawabanTerisi;
                tampilkanAlert("⚠️ MASIH ADA " + belumDijawab + " SOAL KOSONG!<br>Silakan periksa dan jawab semua soal sebelum mengirim.");
                return; // Berhenti di sini, modal konfirmasi TIDAK akan muncul!
            }

            // === 2. TAMPILKAN MODAL KONFIRMASI (SISTEM GALAK TETAP NYALA!) ===
            // Saya hapus isPengawasanAktif = false; di sini.
            document.getElementById('custom-confirm').style.display = 'flex';
        }

        function tutupConfirm() {
            document.getElementById('custom-confirm').style.display = 'none';
        }

        function submitFormFinal() {
            // Hanya kalau siswa klik "Ya, Kirim", sistem pengawasan baru dimatikan agar saat proses submit loading tidak kena pelanggaran.
            isSubmitting = true; 
            document.getElementById('custom-confirm').style.display = 'none';
            document.getElementById('form-ujian').submit();
        }

        document.getElementById('btn-fullscreen').addEventListener('click', () => {
            if (elem.requestFullscreen) { elem.requestFullscreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            
            overlay.style.display = 'none';
            kontenUjian.style.display = 'block';
            setTimeout(() => { isUjianFullscreen = true; isPengawasanAktif = true; }, 1000); // Sistem galak mulai diaktifkan!
        });

        // ==========================================
        // 🚨 SISTEM ANTI-CHEAT (SUPER GALAK) 🚨
        // ==========================================
        function catatPelanggaran(jenis) {
            if(!isPengawasanAktif || isSubmitting) return; 
            
            pelanggaran++;
            document.getElementById('pelanggaran-count').innerText = pelanggaran;

            let formData = new FormData();
            formData.append('ujian_id', <?php echo $ujian_id; ?>);
            formData.append('jumlah', pelanggaran);
            navigator.sendBeacon('catat_pelanggaran.php', formData);

            if (pelanggaran >= maxPelanggaran) {
                isSubmitting = true; // Kunci sistem agar tidak kirim data pelanggaran dobel
                document.getElementById('layar-hitam').style.display = 'flex';
                setTimeout(() => { document.getElementById('form-ujian').submit(); }, 2000);
            } else {
                tampilkanAlert("⚠️ PELANGGARAN: " + jenis + " (" + pelanggaran + "/" + maxPelanggaran + ")");
            }
        }

        // 1. Ketahuan Ganti Tab / Minimize Browser
        document.addEventListener('visibilitychange', () => { 
            if (document.hidden && isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Membuka Aplikasi Lain / Keluar Browser');
            }
        });

        // 2. Ketahuan Buka Pop-up Notif WA / Split Screen / Kehilangan Fokus
        window.addEventListener('blur', () => {
            if(isPengawasanAktif && !isSubmitting) {
                catatPelanggaran('Kehilangan Fokus Layar / Membuka Aplikasi Lain');
            }
        });

        // 3. Ketahuan Keluar dari Mode Layar Penuh (ESC)
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && isUjianFullscreen && !isSubmitting) {
                catatPelanggaran("Keluar Mode Layar Penuh");
                // Memaksa siswa untuk menekan tombol mulai lagi untuk lanjut ujian
                overlay.style.display = 'flex'; 
                kontenUjian.style.display = 'none';
                isUjianFullscreen = false; 
                isPengawasanAktif = false; // Matikan sementara sampai mereka klik "Mulai" lagi
            }
        });

        // ==========================================
        // 🔄 SINKRONISASI SERVER & TIMER
        // ==========================================
        setInterval(() => {
            if(!isPengawasanAktif || isSubmitting) return;
            let formPing = new FormData();
            formPing.append('ujian_id', <?php echo $ujian_id; ?>);
            fetch('ping_ujian.php', { method: 'POST', body: formPing })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'stop') {
                    isSubmitting = true;
                    window.location.href = 'selesai_ujian.php';
                }
            }).catch(() => {});
        }, 5000); 

        let sisaDetik = <?php echo $sisa_detik; ?>;
        const timerElement = document.getElementById('timer');
        const formUjian = document.getElementById('form-ujian');

        const hitungMundur = setInterval(() => {
            if (sisaDetik <= 0) {
                clearInterval(hitungMundur);
                isSubmitting = true;
                tampilkanAlert("Waktu habis! Mengirim otomatis...");
                
                // Jika waktu habis, akan DIPAKSA kirim walaupun ada yang kosong
                setTimeout(() => { formUjian.submit(); }, 2000);
            } else {
                let jam = Math.floor(sisaDetik / 3600);
                let menit = Math.floor((sisaDetik % 3600) / 60);
                let detik = (sisaDetik % 3600) % 60;
                timerElement.innerText = (jam < 10 ? "0" + jam : jam) + ":" + (menit < 10 ? "0" + menit : menit) + ":" + (detik < 10 ? "0" + detik : detik);
                sisaDetik--;
            }
        }, 1000);

        // FITUR ZOOM GAMBAR SOAL
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