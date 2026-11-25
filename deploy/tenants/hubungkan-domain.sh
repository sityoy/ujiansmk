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
    local enabled="/etc/nginx/sites-enabled/sis-cbt-sekolah.conf" linked=0
    if [[ -f "$site" ]]; then cp "$site" "$work/previous"; else rm -f "$work/previous"; fi
    install -m 0644 "$1" "$site"
    if nginx -T >"$work/active" 2>"$work/test" && ! grep -Fxq "$marker" "$work/active" &&
        [[ -d /etc/nginx/sites-enabled && ! -e "$enabled" && ! -L "$enabled" ]]; then
        ln -s "$site" "$enabled"
        linked=1
    fi
    if nginx -T >"$work/active" 2>"$work/test" && grep -Fxq "$marker" "$work/active" && ! grep -Fq "conflicting server name \"$domain\"" "$work/test" && systemctl reload nginx; then return; fi
    cat "$work/test" >&2
    if (( linked )); then rm -- "$enabled"; fi
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
# Reload is asynchronous. Verify the page and hostname on fresh connections.
probe() {
    local ip="$1"
    curl --noproxy '*' --connect-timeout 3 --max-time 6 -fsS --resolve "$domain:443:$ip" \
        "https://$domain/" -o "$work/page" 2>"$work/curl-error" &&
        grep -Fq '<title>Masuk CBT Sekolah · SIS</title>' "$work/page"
}
diagnose() {
    local ip="$1"
    echo "Sertifikat yang disajikan ke $domain pada $ip:443:"
    timeout 6 openssl s_client -connect "$ip:443" -servername "$domain" </dev/null 2>/dev/null |
        openssl x509 -noout -subject -ext subjectAltName || true
}
origin_ip=${CBT_ORIGIN_IP:-127.0.0.1}
for attempt in 1 2 3 4 5; do
    if probe "$origin_ip"; then
        echo "Halaman pilih sekolah aktif di https://$domain/ (diuji melalui $origin_ip)."
        exit 0
    fi
    if (( attempt < 5 )); then sleep 1; fi
done
cat "$work/curl-error" >&2
echo "HTTPS belum menampilkan halaman CBT. Sertifikat pada disk:"
openssl x509 -in "$cert_dir/fullchain.pem" -noout -subject -ext subjectAltName
diagnose "$origin_ip"
if [[ "$origin_ip" != 127.0.0.1 ]]; then diagnose 127.0.0.1; fi
echo "Konfigurasi server HTTPS aktif (file, listen dan nama host):"
awk '/^# configuration file / {file=$0} /^[[:space:]]*listen[[:space:]].*443/ || /server_name.*cbt-sekolah[.]sityoyitsolutions[.]com/ {print file, $0}' "$work/active"
exit 1
