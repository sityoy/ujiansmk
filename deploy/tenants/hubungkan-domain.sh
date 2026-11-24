#!/usr/bin/env bash
set -Eeuo pipefail
cd -- "$(dirname -- "$0")"
[[ $(id -u) == 0 && "$(pwd -P)" == /var/www/cbt-sekolah/deploy/tenants ]] || { echo 'Jalankan dengan sudo dari checkout /var/www/cbt-sekolah.' >&2; exit 1; }
domain=cbt-sekolah.sityoyitsolutions.com
site=/etc/nginx/conf.d/sis-cbt-sekolah.conf
marker='# Managed by SIS CBT Sekolah'
webroot=/var/lib/sis-cbt-acme
exec 9>/run/lock/sis-cbt-domain.lock
flock -n 9 || { echo 'Konfigurasi domain sedang berjalan.' >&2; exit 1; }
[[ -S /run/php/php8.4-fpm.sock && -r choose.php && -f ../../public/images/logo-pt-sityoy.png ]] || { echo 'Periksa checkout CBT dan socket PHP 8.4 FPM.' >&2; exit 1; }
if [[ -e "$site" ]] && ! grep -Fxq "$marker" "$site"; then echo "Periksa konfigurasi Nginx yang sudah ada di $site." >&2; exit 1; fi
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
install -d -m 0755 "$webroot/.well-known/acme-challenge"
apply_site() {
    if [[ -f "$site" ]]; then cp "$site" "$work/previous"; else rm -f "$work/previous"; fi
    install -m 0644 "$1" "$site"
    if nginx -t >"$work/test" 2>&1 && ! grep -Fq "conflicting server name \"$domain\"" "$work/test" && systemctl reload nginx; then return; fi
    cat "$work/test" >&2
    if [[ -f "$work/previous" ]]; then install -m 0644 "$work/previous" "$site"; else rm -f "$site"; fi
    echo "Pemasangan dibatalkan. Periksa blok server_name $domain pada konfigurasi aktif." >&2
    return 1
}
http_site() {
    echo "$marker"
    echo 'server {'
    echo '    listen 80;'
    echo "    server_name $domain;"
    echo "    location ^~ /.well-known/acme-challenge/ { root $webroot; try_files \$uri =404; }"
    echo "    location / { $1 }"
    echo '}'
}
cert_dir=''
for candidate in "/etc/letsencrypt/live/$domain" /etc/sis-cbt /etc/letsencrypt/live/sis-cbt-wildcard; do
    if [[ -s "$candidate/fullchain.pem" && -s "$candidate/privkey.pem" ]] &&
        openssl x509 -in "$candidate/fullchain.pem" -noout -checkend 0 >/dev/null &&
        LC_ALL=C openssl x509 -in "$candidate/fullchain.pem" -noout -checkhost "$domain" | grep -Fq 'does match certificate'; then
        cert_dir="$candidate"
        break
    fi
done
if [[ -z "$cert_dir" ]]; then
    command -v certbot >/dev/null || { echo 'Certbot belum terpasang.' >&2; exit 1; }
    http_site 'return 503;' >"$work/http"
    apply_site "$work/http"
    certbot certonly --webroot -w "$webroot" --cert-name "$domain" -d "$domain" --deploy-hook 'systemctl reload nginx'
    cert_dir="/etc/letsencrypt/live/$domain"
fi
http_site 'return 301 https://cbt-sekolah.sityoyitsolutions.com$request_uri;' >"$work/https"
sed "s|__CERT_DIR__|$cert_dir|g" shared-nginx.conf >>"$work/https"
apply_site "$work/https"
curl -fsS --resolve "$domain:443:127.0.0.1" "https://$domain/" | grep -Fq 'Masuk CBT Sekolah'
echo "Halaman pilih sekolah aktif di https://$domain/"
