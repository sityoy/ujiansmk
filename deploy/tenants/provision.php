<?php
// Invoked by the VPS timer, outside the web root, using local MySQL socket authentication.
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit(1); }
const ROOT_DIR = '/var/lib/sis-cbt';
const DOMAIN_TABLE = 'db_portalsiscom.schools';
date_default_timezone_set('Asia/Jakarta');
umask(0027);
$lock = fopen(ROOT_DIR.'/.provision.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit($lock ? 0 : 1); }
$config = parse_ini_file('/etc/sis-cbt/provision.ini', false, INI_SCANNER_RAW);
if (!$config || !preg_match('/^(?:NONE|[A-Z0-9]{8})$/D', $config['legacy_npsn'] ?? '')) {
    throw new RuntimeException('Tetapkan NPSN CBT lama terverifikasi atau NONE dalam provision.ini.');
}
$socket = $config['socket'] ?? '/run/mysqld/mysqld.sock';
$mysql = new PDO('mysql:unix_socket='.$socket.';charset=utf8mb4', $config['user'] ?? 'root', $config['password'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
function atomicWrite(string $path,string $value): void
{
    $temp = tempnam(dirname($path), '.tmp-');
    if (!$temp || file_put_contents($temp, $value) !== strlen($value) ||
        !chgrp($temp,'www-data') || !chmod($temp,0640) || !rename($temp,$path)) {
        throw new RuntimeException('Gagal menyimpan konfigurasi lokal.');
    }
}
function status(PDO $db,array $row,string $status,?string $setupKey=null): void
{
    $sql = 'UPDATE '.DOMAIN_TABLE.' SET cbt_status=?,cbt_setup_key=? WHERE id=? AND npsn=? AND active=1 AND FIND_IN_SET(?,modules) AND until_date>=?';
    $db->prepare($sql)->execute([$status,$setupKey,$row['id'],$row['npsn'],'cbt',date('Y-m-d')]);
}
function identity(PDO $db,array $row,string $dir,string $key,string $socket): array
{
    $file = $dir.'/identity.json';
    if (!is_file($file)) {
        $name = 'cbt'.$key.bin2hex(random_bytes(5));
        $check = $db->prepare('SELECT EXISTS(SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?) OR EXISTS(SELECT 1 FROM mysql.user WHERE User=?)');
        $check->execute([$name,$name]);
        if ($check->fetchColumn()) { throw new RuntimeException('Nama database sudah terpakai.'); }
        $data = ['school_id'=>(int)$row['id'],'npsn'=>$key,'database'=>$name,'socket'=>$socket,
            'app_key'=>'base64:'.base64_encode(random_bytes(32)), 'password'=>bin2hex(random_bytes(32)), 'setup_key'=>bin2hex(random_bytes(32))];
        atomicWrite($file,json_encode($data,JSON_THROW_ON_ERROR));
    }
    $data = json_decode(file_get_contents($file),true,flags:JSON_THROW_ON_ERROR);
    if ($data['school_id'] !== (int)$row['id'] || $data['npsn'] !== $key || !preg_match('/^cbt'.$key.'[a-f0-9]{10}$/D',$data['database'])) {
        throw new RuntimeException('Identitas sekolah berbeda dari pemilik database.');
    }
    return $data;
}
function initialize(PDO $db,array $row,string $dir,array $data): void
{
    $name = $data['database'];
    $account = $db->quote($name)."@'localhost'";
    $db->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$db->quote($data['password']));
    // Verify ownership before granting rights on the dedicated database, also on retries.
    new PDO('mysql:unix_socket='.$data['socket'], $name, $data['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE DATABASE IF NOT EXISTS \x60$name\x60 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->exec("GRANT ALL PRIVILEGES ON \x60$name\x60.* TO ".$account);
    $storage = $dir.'/storage';
    if (!is_dir($storage) && !mkdir($storage,0770)) { throw new RuntimeException('Gagal membuat storage.'); }
    if (!chgrp($storage,'www-data') || !chmod($storage,0770)) { throw new RuntimeException('Gagal menyiapkan akses storage.'); }
    $process = proc_open(['/usr/sbin/runuser','-u','www-data','--',PHP_BINARY,'/var/www/cbt-sekolah/deploy/tenants/initialize.php',$data['npsn']],
        [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Gagal memulai migrasi.'); }
    fwrite($pipes[0],json_encode(['database'=>$name,'name'=>$row['name']],JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Migrasi tenant gagal.'); }
    atomicWrite($dir.'/initialized','1');
}
$rows = $mysql->query('SELECT id,name,npsn,until_date,modules,active,cbt_status,cbt_setup_key FROM '.DOMAIN_TABLE.
    " WHERE npsn IS NOT NULL AND (FIND_IN_SET('cbt',modules) OR cbt_status IS NOT NULL)")->fetchAll(PDO::FETCH_ASSOC);
$allowed = [];
$failed = false;
foreach ($rows as $row) {
    if (!preg_match('/^[A-Z0-9]{8}$/D',$row['npsn'])) { continue; }
    $key = strtolower($row['npsn']);
    $dir = ROOT_DIR.'/'.$key;
    if (!$row['active'] || $row['until_date'] < date('Y-m-d') || !in_array('cbt',explode(',',$row['modules']),true)) { continue; }
    try {
        if ($row['npsn'] === $config['legacy_npsn']) {
            if (is_dir($dir)) { throw new RuntimeException('Sudah ada database tenant untuk NPSN CBT lama.'); }
            status($mysql,$row,'legacy');
            continue;
        }
        if ($row['cbt_status'] === 'legacy') { throw new RuntimeException('Periksa pemetaan NPSN CBT lama.'); }
        if (!is_dir($dir) && (!mkdir($dir,0750) || !chgrp($dir,'www-data'))) { throw new RuntimeException('Gagal membuat direktori sekolah.'); }
        $data = identity($mysql,$row,$dir,$key,$socket);
        if (!is_file($dir.'/initialized')) {
            initialize($mysql,$row,$dir,$data);
        }
        if (!is_file($dir.'/active-until') || trim(file_get_contents($dir.'/active-until')) !== $row['until_date']) {
            atomicWrite($dir.'/active-until',$row['until_date']);
        }
        $setup = null;
        if ($row['cbt_status'] !== 'ready' || $row['cbt_setup_key'] !== null) {
            $used = $mysql->query('SELECT consumed_at FROM '.$data['database'].'.tenant_activation WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            if (!$used) { throw new RuntimeException('Penanda aktivasi CBT belum siap.'); }
            $setup = $used['consumed_at'] === null ? $data['setup_key'] : null;
        }
        if ($row['cbt_status'] !== 'ready' || $row['cbt_setup_key'] !== $setup) { status($mysql,$row,'ready',$setup); }
        $allowed[$key] = true;
    } catch (Throwable $error) {
        $failed = true;
        // Do not echo SQL/user creation exceptions that may include a password.
        fwrite(STDERR,"CBT ".$key." gagal pada ".basename($error->getFile()).':'.$error->getLine().' ('.$error::class.").\n");
        if ($row['cbt_status'] !== 'legacy') { status($mysql,$row,'error'); }
    }
}
foreach (glob(ROOT_DIR.'/*/active-until') as $permit) {
    if (!isset($allowed[basename(dirname($permit))])) { unlink($permit); }
}
exit($failed ? 1 : 0);
