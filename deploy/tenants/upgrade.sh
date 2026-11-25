#!/usr/bin/env bash
set -Eeuo pipefail
cd /var/www/cbt-sekolah
exec 9>/var/lib/sis-cbt/.provision.lock
flock -n 9 || { echo 'Provisioner sedang bekerja.' >&2; exit 1; }
shopt -s nullglob
for marker in /var/lib/sis-cbt/*/initialized; do
    dir="$(dirname -- "$marker")"
    key="$(basename -- "$dir")"
    [[ "$key" =~ ^[a-z0-9]{8}$ ]] || exit 1
    php -r '$id=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);echo json_encode(["database"=>$id["database"],"name"=>"Sekolah ".strtoupper($id["npsn"])]);' "$dir/identity.json" |
        runuser -u www-data -- php deploy/tenants/initialize.php "$key"
done
