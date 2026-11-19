#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${UJIANSMK_APP_DIR:-/var/www/ujiansmk}"
BRANCH="${UJIANSMK_BRANCH:-laravel-v2}"

cd "$APP_DIR"

if [[ ! -f artisan || ! -f .env ]]; then
    echo "Direktori aplikasi atau file .env tidak ditemukan di $APP_DIR." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Deployment dihentikan: terdapat perubahan lokal pada file Git." >&2
    echo "Periksa dengan: git status" >&2
    exit 1
fi

git fetch origin "$BRANCH"

if ! git cat-file -e "origin/$BRANCH:public/build/manifest.json" 2>/dev/null; then
    echo "Aset production belum tersedia. Tunggu workflow Build production assets selesai." >&2
    exit 1
fi

# Stop before maintenance if participants are active. Repeat inside maintenance
# before replacing files to also catch starts admitted during the first check.
assert_no_active_exams() {
    php artisan tinker --execute='
        $core = App\Models\ExamAttempt::query()->where("status", "in_progress")->exists();
        $trial = App\Models\TrialAttempt::query()->where("status", "in_progress")
            ->with("trialExam")->lazyById(200)
            ->contains(fn ($attempt) => ! app(App\Services\Exams\TrialExamAttemptService::class)->isExpired($attempt));
        if ($core || $trial) { fwrite(STDERR, "Deployment ditolak: masih ada Ujian Inti atau Uji Coba yang berlangsung.\n"); exit(23); }
    ' >/dev/null
}

php artisan exams:finalize-expired
assert_no_active_exams
php artisan down --retry=30
restore_app() {
    php artisan up >/dev/null 2>&1 || true
}
trap restore_app EXIT
assert_no_active_exams

git merge --ff-only "origin/$BRANCH"

COMPOSER_MEMORY_LIMIT=512M composer install \
    --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan exams:finalize-expired

php artisan up
trap - EXIT

php artisan exams:readiness --deployment
echo "Deployment selesai tanpa npm ci/npm run build di VPS."
