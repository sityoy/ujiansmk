#!/usr/bin/env bash
set -Eeuo pipefail
cd -- "$(dirname -- "$0")"
[[ $(id -u) == 0 && "$(pwd -P)" == /var/www/cbt-sekolah/deploy/tenants ]] || { echo 'Jalankan dengan sudo dari checkout /var/www/cbt-sekolah.' >&2; exit 1; }
for path in /etc/sis-cbt/provision.ini ../../vendor/autoload.php ../../public/build/manifest.json /run/php/php8.4-fpm.sock; do
    [[ -e "$path" ]] || { echo "Belum tersedia: $path" >&2; exit 1; }
done
php -r 'exit(PHP_VERSION_ID >= 80401 && extension_loaded("pdo_mysql") && extension_loaded("posix") ? 0 : 1);'
chown root:root /etc/sis-cbt/provision.ini
chmod 0600 /etc/sis-cbt/provision.ini
install -d -m 0750 -o root -g www-data /var/lib/sis-cbt
install -d /usr/local/lib/sis-cbt
install -m 0644 provision.php /usr/local/lib/sis-cbt/
bash hubungkan-domain.sh
install -m 0644 sis-cbt-provision.{service,timer} /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now sis-cbt-provision.timer
