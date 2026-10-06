# Deployment guide — DX Student Help

Drafted in Sprint 1, implemented in Sprint 3, finalised in Sprint 14.

## Environments

| Environment | Host | Branch | Purpose |
|---|---|---|---|
| Staging | Client VPS, separate cPanel account | `develop` | Sprint reviews and UAT |
| Production | Client VPS, cPanel account | `main` | Live platform |

## Server prerequisites (WHM / root)

- PHP 8.3 (`ea-php83`) with bcmath, mbstring, intl, gd or imagick, zip, redis, pdo_mysql, opcache, exif, fileinfo
- Composer 2
- MySQL 8 or MariaDB 10.6+ (decision recorded in Sprint 1)
- Redis — install over SSH; not part of a standard cPanel build
- Supervisor — keeps queue workers alive; cPanel has no equivalent
- Cron: `* * * * * cd /home/<user>/app && php artisan schedule:run >> /dev/null 2>&1`
- Document root pointed at `public/`
- Deploy user with an SSH key held in GitHub Actions secrets
- AutoSSL / Let's Encrypt for every domain
- Recommended: 4 vCPU, 8 GB RAM, 80 GB SSD

## Services outside the VPS

| Service | Purpose |
|---|---|
| Transactional email (SES / Mailgun / Postmark) | Verification, consent, notifications — never cPanel mail |
| Off-server object storage | Encrypted daily backups and, optionally, uploads |
| Cloudflare | DNS, CDN, WAF, TLS |
| Firebase Cloud Messaging + APNs | Push notifications (Sprint 13) |
| Sentry + uptime monitor | Error tracking and alerting |

DNS records required on the client domain: SPF, DKIM and DMARC for the email provider.

## Pipeline

```
push to develop ──> GitHub Actions ──> staging
push/tag on main ──> GitHub Actions ──> production
```

Actions runs: `composer install` → Pest → PHPStan → `npm ci && npm run build` → rsync over SSH.
Assets are built in CI, never on the server.

## Release steps on the server

```bash
php artisan down --render="errors::503"
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache route:cache view:cache event:cache
php artisan storage:link
php artisan queue:restart
php artisan up
```

## Rollback

1. Re-deploy the previous tag.
2. If a migration is involved, restore the pre-deployment database snapshot.
   Every production deployment takes a snapshot first.
3. `php artisan queue:restart`, then verify health checks.

## Backups

- Database dump and uploads to off-server storage, encrypted, daily.
- Retention 30 days. Binary logs enabled for point-in-time recovery (RPO ≤ 1 hour).
- Restore tested before go-live and quarterly after. An untested backup is not a backup.

## Mobile builds (Sprint 13 onwards)

Codemagic builds on a tagged release: `npm ci` → `npm run build` → `npx cap sync` →
signed `.aab` and `.ipa` → upload to Google Play internal testing and TestFlight.
Signing keys live in Codemagic encrypted variables, never in this repository.

## Go-live checklist (Sprint 14)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] HTTPS enforced; HSTS and security headers set
- [ ] Queue workers and scheduler running under Supervisor
- [ ] Backups running and a restore proven
- [ ] Monitoring, error tracking and alerts live
- [ ] Email deliverability verified (SPF, DKIM, DMARC passing)
- [ ] Load test passed at the agreed launch capacity
- [ ] Terms, privacy policy and consent wording published
- [ ] Curriculum data loaded; launch subjects have minimum tutor coverage
