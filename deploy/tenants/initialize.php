<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__.'/runtime.php';
$key = $argv[1] ?? '';
if (!preg_match('/^[a-z0-9]{8}$/D', $key)) { exit(1); }
$dir = '/var/lib/sis-cbt/'.$key;
foreach (['app/private','app/public','framework/cache/data','framework/views','framework/sessions','logs'] as $sub) {
    $path = $dir.'/storage/'.$sub;
    if (!is_dir($path) && !mkdir($path,0750,true)) { throw new RuntimeException('Gagal membuat storage.'); }
}
$app = tenantApplication($key,$dir);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (DB::selectOne('SELECT DATABASE() AS db')->db !== $input['database']) {
    throw new RuntimeException('Database aktif berbeda dari target.');
}
if ($kernel->call('migrate', ['--force'=>true]) !== 0) {
    fwrite(STDERR,$kernel->output());
    exit(1);
}
if (!Schema::hasTable('tenant_activation')) {
    Schema::create('tenant_activation', function (Blueprint $table) {
        $table->unsignedTinyInteger('id')->primary();
        $table->timestamp('consumed_at')->nullable();
    });
}
DB::transaction(function () use ($key,$input) {
    DB::table('tenant_activation')->insertOrIgnore(['id'=>1]);
    if (!DB::table('school_profiles')->exists()) {
        DB::table('school_profiles')->insert(['name'=>$input['name'],'npsn'=>strtoupper($key),'created_at'=>now(),'updated_at'=>now()]);
    }
});
