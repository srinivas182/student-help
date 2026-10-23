#!/usr/bin/env bash
#
# Point the platform at a pair of domains, without editing .env by hand.
#
#   bash deploy/set-domains.sh thexstudenthelp.co.za thexteacherhelp.co.za
#
# Rewrites the four settings that decide which portal a visitor sees, keeps a
# timestamped backup of .env, and re-caches afterwards — config:cache is the
# step people forget, and until it runs Laravel keeps serving the old hosts.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

PHP="${PHP:-/usr/local/bin/ea-php83}"

STUDENT="${1:-}"
TEACHER="${2:-}"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$1"; exit 1; }

[ -n "$STUDENT" ] && [ -n "$TEACHER" ] || fail \
    "Usage: bash deploy/set-domains.sh <student-domain> <teacher-domain>"

[ -f .env ] || fail ".env is missing."

# Bare hostnames only: a scheme or a trailing slash here silently breaks the
# host comparison that routes people to the right portal.
for host in "$STUDENT" "$TEACHER"; do
    case "$host" in
        *://*|*/*) fail "Give the hostname only, with no https:// and no trailing slash: $host" ;;
    esac
done

[ "$STUDENT" != "$TEACHER" ] || fail "The two portals need different hostnames."

BACKUP=".env.backup.$(date +%Y%m%d-%H%M%S)"
cp .env "$BACKUP"
say "Backed up .env to $BACKUP"

# Replace the line if present, append it if not
set_env() {
    local key="$1" value="$2"

    if grep -qE "^${key}=" .env; then
        # The pipe delimiter avoids escaping the slashes in a URL
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

say "Setting portal hosts"
set_env APP_URL "https://${STUDENT}"
set_env STUDENT_PORTAL_HOST "$STUDENT"
set_env TEACHER_PORTAL_HOST "$TEACHER"
# Must be false in production, or the wrong-door redirect never fires
set_env PORTAL_ALLOW_SHARED_HOST false

grep -E "^(APP_URL|STUDENT_PORTAL_HOST|TEACHER_PORTAL_HOST|PORTAL_ALLOW_SHARED_HOST)=" .env

say "Rebuilding caches"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

say "What the application now sees"
$PHP artisan tinker --execute="
echo 'student: '.config('portals.student.host').PHP_EOL;
echo 'teacher: '.config('portals.teacher.host').PHP_EOL;
echo 'app url: '.config('app.url').PHP_EOL;
echo 'shared host allowed: '.(config('portals.allow_shared_host') ? 'yes — WRONG for production' : 'no').PHP_EOL;"

printf '\n\033[1;32mDone.\033[0m Check https://%s and https://%s\n' "$STUDENT" "$TEACHER"
printf 'If login bounces back to the login page, AutoSSL has not finished yet.\n\n'
