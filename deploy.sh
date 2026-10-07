#!/usr/bin/env bash
#
# Deploy DX Student Help on a cPanel/WHM server.
#
# Safe to run repeatedly. Takes the site down for a few seconds during the
# migration, and refuses to continue if the security check fails.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

PHP="${PHP_BIN:-php}"
echo "→ Using PHP: $($PHP -v | head -1)"

if [ ! -f .env ]; then
    echo "✗ No .env file. Copy .env.production.example to .env and fill it in first."
    exit 1
fi

echo "→ Backing up the database before touching anything"
mkdir -p storage/backups
BACKUP="storage/backups/pre-deploy-$(date +%Y%m%d-%H%M%S).sql"
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2-)
DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)

if command -v mysqldump >/dev/null 2>&1 && [ -n "$DB_NAME" ]; then
    mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP" 2>/dev/null \
        && echo "  saved $BACKUP" \
        || echo "  ! backup failed — check credentials before continuing"
else
    echo "  ! mysqldump not found, skipping backup"
fi

echo "→ Maintenance mode"
$PHP artisan down --render="errors::503" --retry=60 || true

cleanup() {
    $PHP artisan up || true
}
trap cleanup EXIT

echo "→ Clearing stale caches"
$PHP artisan optimize:clear

echo "→ Running migrations"
$PHP artisan migrate --force

echo "→ Linking storage"
$PHP artisan storage:link 2>/dev/null || true

echo "→ Caching config, routes, views and events"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "→ Restarting queue workers"
$PHP artisan queue:restart || true

echo "→ Security check"
if ! $PHP artisan platform:security-check; then
    echo ""
    echo "✗ Security check failed. The site is still in maintenance mode."
    echo "  Fix the items above, then run this script again."
    trap - EXIT
    exit 1
fi

$PHP artisan up
trap - EXIT

echo ""
echo "✓ Deployed. Verify:"
echo "    https://x-student-help.mcs.bz"
echo "    https://x-teacher-help.mcs.bz"
