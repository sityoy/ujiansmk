<?php

define('LARAVEL_START', microtime(true));
require __DIR__.'/runtime.php';
$key = $_SERVER['CBT_SCHOOL'] ?? '';
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$shared = $host === 'cbt-sekolah.sityoyitsolutions.com';
if (!preg_match('/^[a-z0-9]{8}$/D', $key) || (!$shared && $host !== 'cbt-'.$key.'.sityoyitsolutions.com')) {
    http_response_code(404);
    exit;
}
$dir = '/var/lib/sis-cbt/'.$key;
$lease = is_file($dir.'/active-until') ? trim((string)file_get_contents($dir.'/active-until')) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $lease) || $lease < (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d')) {
    http_response_code(403);
    exit('Layanan CBT sekolah belum aktif.');
}
if ($shared) {
    $prefix = '/s/'.$key.'/';
    if (!str_starts_with($_SERVER['REQUEST_URI'] ?? '', $prefix)) {
        http_response_code(404);
        exit;
    }
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $prefix.'http.php';
}
tenantApplication($key,$dir,$shared)->handleRequest(Illuminate\Http\Request::capture());
