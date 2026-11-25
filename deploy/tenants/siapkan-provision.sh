#!/usr/bin/env bash
set -Eeuo pipefail
[[ $(id -u) == 0 ]] || { echo 'Jalankan dengan sudo.' >&2; exit 1; }
config=/etc/sis-cbt/provision.ini
[[ ! -e "$config" && ! -L "$config" ]] || { echo "Konfigurasi sudah tersedia: $config"; exit 0; }
umask 077
socket=$(mysql --protocol=socket --batch --skip-column-names -e 'SELECT @@socket')
[[ "$socket" =~ ^/[a-zA-Z0-9_./-]+$ && -S "$socket" ]] || { echo 'Socket MySQL lokal belum ditemukan.' >&2; exit 1; }
# Confirm exactly the same root socket access that the timer will use.
php -r '$db = new PDO("mysql:unix_socket=".$argv[1], "root", ""); $db->query("SELECT npsn FROM db_ujiansis.school_profiles LIMIT 1");' "$socket" ||
    { echo 'Periksa akses PDO MySQL root melalui socket atau gunakan konfigurasi DBA manual.' >&2; exit 1; }
echo 'Profil dari database CBT lama (db_ujiansis):'
mysql --protocol=socket --socket="$socket" --table -e 'SELECT name AS sekolah, npsn AS NPSN FROM db_ujiansis.school_profiles'
read -r -p 'Masukkan NPSN resmi SMK Islam Bahagia (8 karakter): ' npsn
npsn=${npsn^^}
[[ "$npsn" =~ ^[A-Z0-9]{8}$ ]] || { echo 'Format NPSN tidak sesuai.' >&2; exit 1; }
known=$(mysql --protocol=socket --socket="$socket" --batch --skip-column-names -e "SELECT DISTINCT UPPER(TRIM(npsn)) FROM db_ujiansis.school_profiles WHERE npsn IS NOT NULL AND TRIM(npsn) <> ''")
if [[ -n "$known" ]] && ! grep -Fxq "$npsn" <<<"$known"; then
    echo 'NPSN berbeda dengan profil CBT lama. Periksa profil sekolah sebelum melanjutkan.' >&2
    exit 1
fi
install -d -m 0750 /etc/sis-cbt
tmp=$(mktemp /etc/sis-cbt/.provision.XXXXXXXX)
trap 'rm -f -- "$tmp"' EXIT
printf 'socket=%s\nuser=root\nlegacy_npsn=%s\n' "$socket" "$npsn" >"$tmp"
ln -T "$tmp" "$config"
echo "Konfigurasi tersedia untuk NPSN $npsn."
