#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${UJIANSMK_APP_DIR:-/var/www/ujiansmk}"
BRANCH="${UJIANSMK_BRANCH:-laravel-v2}"

cd "$APP_DIR"

if [[ ! -f artisan || ! -f .env ]]; then
    echo "Direktori aplikasi atau file .env tidak ditemukan di $APP_DIR." >&2
    exit 1
fi

require_clean_checkout() {
    local operation
    for operation in rebase-merge rebase-apply MERGE_HEAD CHERRY_PICK_HEAD REVERT_HEAD sequencer; do
        if [[ -e "$(git rev-parse --git-path "$operation")" ]]; then
            echo "Deployment dihentikan. Selesaikan operasi Git yang masih terbuka sesuai petunjuk git status." >&2
            return 1
        fi
    done

    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        echo "Deployment dihentikan. Periksa perubahan lokal dengan git status." >&2
        return 1
    fi
}

exec {deploy_lock}>"$(git rev-parse --git-path ujiansmk-deploy.lock)"
flock -n "$deploy_lock" || { echo "Deployment lain sedang berjalan." >&2; exit 1; }

require_clean_checkout
current_branch="$(git symbolic-ref --quiet HEAD)" || {
    echo "Pindah ke branch rilis sebelum menjalankan deployment." >&2
    exit 1
}
current="$(git rev-parse HEAD)"

git fetch origin "$BRANCH"
target="$(git rev-parse --verify 'FETCH_HEAD^{commit}')"

if ! git merge-base --is-ancestor "$current" "$target" && ! git merge-base --is-ancestor "$target" "$current"; then
    # Prepare the merge without changing the branch, index or working tree.
    if ! merge_result="$(git merge-tree --write-tree "$current" "$target")"; then
        echo "Deployment dihentikan. Periksa konflik atau kesalahan Git di bawah ini." >&2
        printf '%s\n' "$merge_result" >&2
        exit 1
    fi
    merged_tree="${merge_result%%$'\n'*}"
    target="$(git commit-tree "$merged_tree" -p "$current" -p "$target" -m "Merge origin/$BRANCH for deployment")"
fi

if ! git cat-file -e "$target:public/build/manifest.json" 2>/dev/null; then
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

require_clean_checkout
if [[ "$(git symbolic-ref --quiet HEAD)" != "$current_branch" || "$(git rev-parse HEAD)" != "$current" ]]; then
    echo "Posisi checkout berubah selama persiapan. Jalankan deploy.sh kembali." >&2
    exit 1
fi
git merge --ff-only "$target"

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
