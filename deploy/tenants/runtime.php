<?php

use Illuminate\Foundation\Application;

// Only the dedicated Nginx vhost and the provisioning CLI use this bootstrap.
function tenantApplication(string $npsn, string $directory): Application
{
    if (!preg_match('/^[a-z0-9]{8}$/D', $npsn) || !is_file($directory.'/identity.json')) {
        throw new RuntimeException('Sekolah CBT belum tersedia.');
    }
    $identity = json_decode(file_get_contents($directory.'/identity.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($identity['npsn'] !== $npsn || !preg_match('/^cbt'.$npsn.'[a-f0-9]{10}$/D', $identity['database'])) {
        throw new RuntimeException('Identitas database CBT tidak sesuai.');
    }
    $storage = $directory.'/storage';
    $env = [
        'APP_ENV'=>'production','APP_DEBUG'=>'false','APP_NAME'=>'CBT Sekolah','APP_TIMEZONE'=>'Asia/Jakarta',
        'APP_URL'=>'https://cbt-'.$npsn.'.sityoyitsolutions.com','APP_KEY'=>$identity['app_key'],
        'APP_CONFIG_CACHE'=>$directory.'/cache/config.php','APP_ROUTES_CACHE'=>$directory.'/cache/routes.php',
        'DB_CONNECTION'=>'mysql','DB_URL'=>'','DATABASE_URL'=>'','DB_SOCKET'=>$identity['socket'],
        'DB_DATABASE'=>$identity['database'],'DB_USERNAME'=>$identity['database'],'DB_PASSWORD'=>$identity['password'],
        'SESSION_DRIVER'=>'database','SESSION_CONNECTION'=>'mysql','SESSION_COOKIE'=>'__Host-cbt_'.$npsn,
        'SESSION_DOMAIN'=>'null','SESSION_PATH'=>'/','SESSION_SECURE_COOKIE'=>'true','SESSION_HTTP_ONLY'=>'true',
        'CACHE_STORE'=>'database','QUEUE_CONNECTION'=>'sync','FILESYSTEM_DISK'=>'local',
        'VIEW_COMPILED_PATH'=>$storage.'/framework/views','LARAVEL_STORAGE_PATH'=>$storage,
        'LOG_CHANNEL'=>'daily','MAIL_MAILER'=>'log',
    ];
    foreach ($env as $key=>$value) {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }
    $base = dirname(__DIR__, 2);
    require_once $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $app->useEnvironmentPath($directory);
    $app->useStoragePath($storage);
    $app->booted(function () use ($identity) {
        config(['tenant.npsn'=>strtoupper($identity['npsn']), 'tenant.setup_key'=>$identity['setup_key']]);
        \Illuminate\Support\Facades\Route::middleware(['web','throttle:15,1'])->group(function () {
            \Illuminate\Support\Facades\Route::get('/aktivasi-admin', [\App\Http\Controllers\TenantSetupController::class,'show']);
            \Illuminate\Support\Facades\Route::post('/aktivasi-admin', [\App\Http\Controllers\TenantSetupController::class,'store']);
        });
    });
    return $app;
}
