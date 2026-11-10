#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# Run manually from cPanel SSH. Nothing invokes this script automatically.
if [[ "$(git branch --show-current)" != "dev" ]]; then
    echo 'Switch to dev before running this development deployment.' >&2
    exit 1
fi
if [[ -n "$(git status --porcelain)" ]]; then
    echo 'Working tree has changes. Review and commit them before updating.' >&2
    exit 1
fi
[[ -f .env ]] || { echo 'Create .env from .env.example first.' >&2; exit 1; }
command -v php >/dev/null
command -v composer >/dev/null
php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || { echo 'PHP 8.3+ required.' >&2; exit 1; }
git pull --ff-only origin dev
[[ -f composer.lock ]] || { echo 'Composer lock missing; restore the reviewed lock before deployment.' >&2; exit 1; }
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php artisan gotry:check-database
php artisan config:cache
php artisan view:cache
echo 'GoTry dev update complete. Verify /health and parent/provider signup.'
