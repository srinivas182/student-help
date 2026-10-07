#!/usr/bin/env bash
#
# Deploy DX Student Help on a cPanel/WHM account.
#
# Run as the cPanel user, from the application root:
#   bash deploy/deploy.sh
#
# Safe to run repeatedly. Puts the site in maintenance mode only for the
# seconds it takes to migrate, and refuses to continue if the security check
# fails.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

PHP="${PHP:-/usr/local/bin/ea-php83}"
COMPOSER="${COMPOSER:-$HOME/composer.phar}"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$1"; exit 1; }

[ -f .env ] || fail ".env is missing. Copy deploy/.env.production.example to .env and fill it in."

say "PHP version"
$PHP -v | head -1

say "Pulling latest code"
git fetch --all --quiet
git reset --hard origin/main --quiet
git log --oneline -1

say "Installing PHP dependencies"
$PHP "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

say "Building front-end assets"
if command -v npm >/dev/null 2>&1; then
    npm ci --silent
    npm run build
else
    echo "npm not found — expecting public/build to have been uploaded."
    [ -d public/build ] || fail "No public/build directory and no npm. Build assets first."
fi

say "Checking security configuration"
$PHP artisan platform:security-check || fail "Security check failed. Fix the items above before deploying."

say "Maintenance mode on"
$PHP artisan down --render="errors::503" --retry=60 || true

say "Running migrations"
$PHP artisan migrate --force

say "Caching configuration, routes, views and events"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

say "Linking storage"
$PHP artisan storage:link || true

say "Restarting queue workers"
$PHP artisan queue:restart

say "Maintenance mode off"
$PHP artisan up

say "Health check"
$PHP artisan about --only=environment | head -8

printf '\n\033[1;32mDeployed: %s\033[0m\n\n' "$(git log --oneline -1)"
