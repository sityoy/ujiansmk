<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require '../koneksi.php';

if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['ujian_id'])) {
    header("Location: login.php");
    exit;
}

$ujian_id = $_SESSION['ujian_id'];
$stmtCek = $pdo->prepare("SELECT jumlah_pelanggaran FROM ujian_siswa WHERE id = ?");
$stmtCek->execute([$ujian_id]);
$dataUjian = $stmtCek->fetch();

// Jika pelanggaran sudah lewat batas
if ($dataUjian['jumlah_pelanggaran'] >= 1) {
    
    header("Location: selesai_ujian.php");
    exit;
}

// Cek Pengaturan Mapel & Timer
$jadwal_id = $_SESSION['jadwal_id'];
$stmtJadwal = $pdo->prepare("SELECT * FROM pengaturan_ujian WHERE id = ?");
$stmtJadwal->execute([$jadwal_id]);
$jadwal = $stmtJadwal->fetch();

$sisa_detik = strtotime($jadwal['waktu_selesai']) - time(); 
$mapel_aktif = $_SESSION['mapel_aktif'];

if ($sisa_detik <= 0) {
    header("Location: selesai_ujian.php");
    exit;
}

// ACAK SOAL
$kelas = strtoupper(trim($_SESSION['kelas']));

if (strpos($kelas, 'XII') === 0) {

    $kelas = 'XII';

} elseif (strpos($kelas, 'XI') === 0) {

    $kelas = 'XI';

} else {

    $kelas = 'X';

}

if (!isset($_SESSION['urutan_soal'])) {

    $stmtSoal = $pdo->prepare("
    SELECT *
    FROM soal
    WHERE
        mata_pelajaran = ?
        AND kelas = ?
    ");

    $stmtSoal->execute([
        $mapel_aktif,
        $kelas
    ]);

    $daftar_soal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

    shuffle($daftar_soal);

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
            -webkit-touch-callout: none; 
            overscroll-behavior-y: none; 
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
    </style>
</head>
<body
oncontextmenu="return false;"
oncopy="return false;"
onpaste="return false;"
onkeydown="return mencegahAksi(event);">

    <div id="modal-zoom-gambar" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.9); z-index: 100005; justify-content: center; align-items: center; flex-direction: column;">
        <span onclick="tutupZoomGambar()" style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; font-weight: bold;">&times;</span>
        <img id="gambar-zoom-aktif" src="" style="max-width: 95%; max-height: 85vh; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
        <p style="color: white; margin-top: 15px;">Ketuk tombol silang (x) untuk menutup</p>
    </div>

    <div id="layar-hitam">
        <h2>Peringatan Sistem!</h2>
        <p>Anda terdeteksi keluar dari layar ujian! Fokus kembali ke layar ujian ini.</p>
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
            Sistem mendeteksi setiap kali Anda berganti tab,
            membuka aplikasi lain, atau mengklik notifikasi.
            
            <strong>1 kali pelanggaran = ujian otomatis selesai.</strong>
        </p>
        <button id="btn-fullscreen">Mulai Ujian</button>
    </div>

    <div id="konten-ujian">
        <div class="header-info">
            <div>
                No: <?php echo htmlspecialchars($_SESSION['kartu_peserta']); ?>
                <br> 
                Nama: <?php echo htmlspecialchars($_SESSION['nama_siswa']); ?>
                <br>
                Kelas: <?php echo htmlspecialchars($_SESSION['kelas']); ?>
                <br>
                Mapel: <span class="mapel-badge"><?php echo htmlspecialchars($mapel_aktif); ?></span>
            </div>
            
            <div style="font-size: 15px;">Waktu: <span id="timer" style="color: #ffcccc; font-weight: bold;">...</span></div>
            <!--<div style="color: #ff9f43; font-size: 15px;">Pelanggaran: <span id="pelanggaran-count"><?php echo $dataUjian['jumlah_pelanggaran']; ?></span>/1</div>-->
            <div style="color: #ff9f43; font-size: 15px;">Pelanggaran: <span id="pelanggaran-count">
                    <?php echo $dataUjian['jumlah_pelanggaran']; ?>
                </span>/1
            </div><br>
            
            
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
                                    <img src="../uploads/<?php echo htmlspecialchars($s['gambar']); ?>" style="max-width:100%; border-radius:5px; cursor: zoom-in;" onclick="bukaZoomGambar(this.src)">
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
                                                <img src="../uploads/<?php echo htmlspecialchars($opsi[$k]['gbr']); ?>" style="max-height: 120px; border-radius: 4px; border: 1px solid #ccc; cursor: zoom-in;" onclick="bukaZoomGambar(this.src)">
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
        function bukaZoomGambar(src) {
            document.getElementById('gambar-zoom-aktif').src = src;
            document.getElementById('modal-zoom-gambar').style.display = 'flex';
        }
        function tutupZoomGambar() {
            document.getElementById('modal-zoom-gambar').style.display = 'none';
        }

        let pelanggaran = <?php echo $dataUjian['jumlah_pelanggaran']; ?>;
        const maxPelanggaran = 1;
        let tinggiAwal = 0;
        let isUjianAktif = false;
        let isSubmitting = false; 
        let isModalOpen = false;
        

        const overlay = document.getElementById('fullscreen-overlay');
        const kontenUjian = document.getElementById('konten-ujian');
        const elem = document.documentElement;

        function tampilkanAlert(pesan) {
            const alertBox = document.getElementById('custom-alert');
            document.getElementById('custom-alert-text').innerHTML = pesan;
            alertBox.style.display = 'block';
            setTimeout(() => { alertBox.style.display = 'none'; }, 4000);
        }
        
        

        window.addEventListener(
        'resize',
        function(){
        
            if(
                isUjianAktif &&
                !isSubmitting
            ){
        
                let selisih =
                    tinggiAwal -
                    window.innerHeight;
        
                if(selisih > 50){
        
                    catatPelanggaran(
                        'Status Bar Dibuka'
                    );
        
                }
        
            }
            
        
        });

        function bukaConfirm() {

            const totalSoal =
                document.querySelectorAll('.soal-box').length;
        
            const totalJawaban =
                document.querySelectorAll(
                    'input[type="radio"]:checked'
                ).length;
        
            if(totalJawaban < totalSoal){
        
                tampilkanAlert(
                    'Masih ada ' +
                    (totalSoal - totalJawaban) +
                    ' soal yang belum dijawab!'
                );
        
                return;
            }
        
            isModalOpen = true;
        
            document.getElementById(
                'custom-confirm'
            ).style.display = 'flex';
        }

        function tutupConfirm() {
            document.getElementById('custom-confirm').style.display = 'none';
            setTimeout(() => { isModalOpen = false; }, 200);
        }

        function submitFormFinal() {
            isSubmitting = true;
            document.getElementById('form-ujian').submit();
        }

        document.getElementById('btn-fullscreen').addEventListener('click', () => {
            tinggiAwal = window.innerHeight;
            if (elem.requestFullscreen) { elem.requestFullscreen(); } 
            else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } 
            else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); } 
            
            overlay.style.display = 'none';
            kontenUjian.style.display = 'block';
            isUjianAktif = true;
        });

        function mencegahAksi(e) {
            if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && (e.keyCode == 73 || e.keyCode == 74)) || (e.ctrlKey && e.keyCode == 85)) return false;
        }

        // MESIN PENCATAT PELANGGARAN UTAMA
        function catatPelanggaran(jenis) {
            if(!isUjianAktif || isSubmitting || isModalOpen) return; 

            pelanggaran++;
            document.getElementById('pelanggaran-count').innerText = pelanggaran;

            fetch('catat_pelanggaran.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ujian_id=' + <?php echo $ujian_id; ?> + '&jumlah=' + pelanggaran
            });

            if (pelanggaran >= maxPelanggaran) {
                isSubmitting = true; 
                tampilkanAlert("Batas pelanggaran tercapai!<br>Ujian dikirim otomatis.");
                setTimeout(() => { document.getElementById('form-ujian').submit(); }, 2500);
            } else {
                tampilkanAlert("⚠️ PELANGGARAN TERDETEKSI!<br>Pelanggaran ke: " + pelanggaran + " / " + maxPelanggaran);
            }
        }

        // 1. SENSOR PALING GALAK: VISIBILITY CHANGE (Cek Recent Apps & Notif)
        document.addEventListener('visibilitychange', () => {
        
            if (document.hidden) {
        
                fetch('catat_pelanggaran.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type':'application/x-www-form-urlencoded'
                    },
                    body: 'ujian_id=<?php echo $ujian_id; ?>&jumlah=1'
                })
                .finally(() => {

                    isSubmitting = true;
                
                    document
                        .getElementById('form-ujian')
                        .submit();
                
                });
        
            }
            
        
        });
        window.addEventListener(
            'blur',
            function(){
            
                if(
                    isUjianAktif &&
                    !isSubmitting
                ){
                    catatPelanggaran(
                        'Window Blur'
                    );
                }
            
        });
            
        setInterval(() => {

            if(
                isUjianAktif &&
                !isSubmitting &&
                !document.hasFocus()
            ){
        
                catatPelanggaran(
                    'Focus Hilang'
                );
        
            }
        
        }, 1000);

        // 2. SENSOR KELUAR FULLSCREEN
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && isUjianAktif && !isSubmitting) {
                catatPelanggaran("Keluar dari Mode Layar Penuh");
                overlay.style.display = 'flex'; 
                kontenUjian.style.display = 'none';
                isUjianAktif = false; 
            }
        });

        // HAPUS SENSOR RESIZE (SPLIT SCREEN LAMA)
        // Agar siswa tidak terkena error saat scroll yang menyembunyikan kotak URL browser

        // SENSOR SCREENSHOT LAPTOP (Tetap dipertahankan)
        document.addEventListener('keyup', (e) => {
            if (e.key === 'PrintScreen' || e.keyCode === 44) {
                catatPelanggaran("Menekan Tombol Screenshot!");
                navigator.clipboard.writeText(''); 
            }
        });

        let sisaDetik = <?php echo $sisa_detik; ?>;
        const timerElement = document.getElementById('timer');
        const formUjian = document.getElementById('form-ujian');

        const hitungMundur = setInterval(() => {
            if (sisaDetik <= 0) {
                clearInterval(hitungMundur);
                isSubmitting = true;
                tampilkanAlert("Waktu ujian habis!<br>Mengirim jawaban otomatis...");
                setTimeout(() => { formUjian.submit(); }, 2500);
            } else {
                let jam = Math.floor(sisaDetik / 3600);
                let menit = Math.floor((sisaDetik % 3600) / 60);
                let detik = (sisaDetik % 3600) % 60;
                timerElement.innerText = (jam < 10 ? "0" + jam : jam) + ":" + (menit < 10 ? "0" + menit : menit) + ":" + (detik < 10 ? "0" + detik : detik);
                sisaDetik--;
            }
        }, 1000); 
        
        // Mencegah Refresh dengan tombol F5 atau tarik layar ke bawah
        window.addEventListener('beforeunload', function (e) {
            if (isSubmitting) return; // Jika tombol kirim ditekan, izinkan keluar
            e.preventDefault();
            e.returnValue = ''; // Ini memicu pesan konfirmasi browser
        });
        
        // Mencegah Tombol Back Browser
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
            tampilkanAlert("Dilarang menekan tombol BACK!");
        };
        
        let lastWidth = window.innerWidth;
        let lastHeight = window.innerHeight;
        
        window.addEventListener('resize', () => {
        
            if(!isUjianAktif || isSubmitting) return;
        
            const selisihWidth =
                Math.abs(window.innerWidth - lastWidth);
        
            const selisihHeight =
                Math.abs(window.innerHeight - lastHeight);
        
            if(
                selisihWidth > 200 ||
                selisihHeight > 200
            ){
                catatPelanggaran(
                    'Split Screen Terdeteksi'
                );
            }
        
            lastWidth = window.innerWidth;
            lastHeight = window.innerHeight;
        
        });
        
        // window.addEventListener('resize', () => {

        //     if(window.devicePixelRatio > 3){
        
        //         catatPelanggaran(
        //             'Zoom Browser Tidak Diizinkan'
        //         );
        
        //     }
        
        // });
        
        document.addEventListener(
        'touchstart',
        function(e){
        
            if(e.touches.length > 1){
        
                e.preventDefault();
        
                catatPelanggaran(
                    'Multi Touch Terdeteksi'
                );
        
            }
        
        },
        { passive:false });
    </script>
</body>
</html>