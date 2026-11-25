<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$error = '';
$type = in_array($_GET['type'] ?? '', ['npsn', 'code'], true) ? $_GET['type'] : 'auto';
$value = '';
if (isset($_GET['school'])) {
    $value = is_string($_GET['school']) ? strtolower(trim($_GET['school'])) : '';
    $index = @file_get_contents('/var/lib/sis-cbt/schools.json');
    $schools = $index !== false ? json_decode($index, true) : [];
    $school = null;
    if (preg_match('/^[a-z0-9][a-z0-9-]{2,31}$/D', $value)) {
        if ($type === 'auto') {
            $byNpsn = $schools['npsn'][$value] ?? null;
            $byCode = $schools['code'][$value] ?? null;
            if ($byNpsn && $byCode && $byNpsn !== $byCode) {
                $error = 'Identitas cocok dengan dua sekolah. Pilih NPSN atau kode SIS untuk melanjutkan.';
            } else {
                $school = $byNpsn ?? $byCode;
            }
        } else {
            $school = $schools[$type][$value] ?? null;
        }
    }
    if ($school === 'legacy') {
        header('Location: https://ujiansmkbhg.sityoyitsolutions.com/login', true, 303);
        exit;
    }
    if (is_string($school) && preg_match('/^[a-z0-9]{8}$/D', $school)) {
        header('Location: /s/'.$school.'/login', true, 303);
        exit;
    }
    if ($error === '') {
        $error = 'Sekolah belum ditemukan. Periksa NPSN atau kode SIS dan pastikan layanan CBT sekolah sudah aktif.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk CBT Sekolah · SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#edf3f9;color:#142b48;font:1rem/1.5 system-ui,sans-serif}main{max-width:29rem;margin:8vh auto;padding:2rem;background:#fff;border-radius:1rem;border:1px solid #d1deeb}img{width:5rem}h1{margin:.75rem 0}form{display:grid;gap:.85rem}label{display:grid;gap:.25rem;font-weight:600}input,select,button{padding:.7rem;font:inherit;border-radius:.45rem;border:1px solid #9db1c8}button{background:#164f91;color:white;border:0;font-weight:600;cursor:pointer}[role=alert]{color:#9a3525}a{color:#164f91}
</style></head><body><main><img src="/images/logo-pt-sityoy.png" alt="Sityoy IT Solutions"><h1>Masuk CBT Sekolah</h1>
<p>Masukkan NPSN atau kode SIS sekolah yang sudah mengaktifkan CBT. Modul pendamping dapat dibuka melalui portal sekolah.</p>
<?php if ($error !== ''): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif ?>
<form method="get" action="/"><label>Cari berdasarkan<select name="type"><option value="auto">Deteksi otomatis</option><option value="npsn" <?= $type === 'npsn' ? 'selected' : '' ?>>NPSN</option><option value="code" <?= $type === 'code' ? 'selected' : '' ?>>Kode sekolah SIS</option></select></label>
<label>Identitas sekolah<input name="school" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" placeholder="NPSN atau kode sekolah SIS" required maxlength="32" autocapitalize="characters" spellcheck="false"></label><button type="submit">Lanjut ke login</button></form>
<p><a href="https://sityoyitsolutions.com/portal#masuk">Masuk Portal Sekolah</a> · <a href="https://sityoyitsolutions.com/portal#daftar">Daftarkan sekolah dan pilih paket</a></p>
<p><a href="https://ujiansmkbhg.sityoyitsolutions.com/login">Masuk CBT SMK Islam Bahagia</a></p></main></body></html>
