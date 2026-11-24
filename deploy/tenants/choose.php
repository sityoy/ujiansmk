<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$error = '';
if (isset($_GET['school'])) {
    $value = is_string($_GET['school']) ? strtolower(trim($_GET['school'])) : '';
    $index = @file_get_contents('/var/lib/sis-cbt/schools.json');
    $schools = $index !== false ? json_decode($index, true) : [];
    $type = ($_GET['type'] ?? '') === 'code' ? 'code' : 'npsn';
    $school = preg_match('/^[a-z0-9][a-z0-9-]{2,31}$/D', $value) ? ($schools[$type][$value] ?? null) : null;
    if ($school === 'legacy') {
        header('Location: https://ujiansmkbhg.sityoyitsolutions.com/login', true, 303);
        exit;
    }
    if (is_string($school) && preg_match('/^[a-z0-9]{8}$/D', $school)) {
        header('Location: /s/'.$school.'/login', true, 303);
        exit;
    }
    $error = 'Sekolah belum ditemukan. Periksa pilihan NPSN atau kode sekolah dan pastikan layanan CBT sudah aktif.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk CBT Sekolah · SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#edf3f9;color:#142b48;font:1rem/1.5 system-ui,sans-serif}main{max-width:29rem;margin:8vh auto;padding:2rem;background:#fff;border-radius:1rem;border:1px solid #d1deeb}img{width:5rem}h1{margin:.75rem 0}form{display:grid;gap:.85rem}label{display:grid;gap:.25rem;font-weight:600}input,select,button{padding:.7rem;font:inherit;border-radius:.45rem;border:1px solid #9db1c8}button{background:#164f91;color:white;border:0;font-weight:600;cursor:pointer}[role=alert]{color:#9a3525}a{color:#164f91}
</style></head><body><main><img src="/images/logo-pt-sityoy.png" alt="Sityoy IT Solutions"><h1>Masuk CBT Sekolah</h1>
<p>Pilih sekolah untuk masuk ke ruang ujian.</p>
<?php if ($error !== ''): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif ?>
<form method="get" action="/"><label>Cari berdasarkan<select name="type"><option value="npsn">NPSN</option><option value="code" <?= ($type ?? '') === 'code' ? 'selected' : '' ?>>Kode sekolah SIS</option></select></label>
<label>Identitas sekolah<input name="school" required maxlength="32" autocapitalize="characters" spellcheck="false"></label><button type="submit">Lanjut ke login</button></form>
<p><a href="https://ujiansmkbhg.sityoyitsolutions.com/login">Masuk CBT SMK Islam Bahagia</a></p></main></body></html>
