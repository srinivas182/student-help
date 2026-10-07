# Performance and capacity

Measured, not guessed. Re-run these before every release.

## Commands

```bash
php artisan platform:seed-scale          # production-sized data for testing
php artisan platform:benchmark           # time the hot queries
php artisan platform:capacity            # readiness checks + capacity estimate
```

`platform:seed-scale` creates throwaway accounts on `@example.test`. Never run
it against production.

## Measured at 20,000 users and 60,000 help requests

| Query | p95 | Queries |
|---|---|---|
| Curriculum subjects (cached) | 0.55 ms | 2 |
| Curriculum subjects (uncached) | 9.10 ms | 1 |
| Tutor queue | 1.97 ms | 3 |
| Student request list | 0.99 ms | 3 |
| Matching eligible tutors | 8.27 ms | 4 |
| Admin dashboard counts | 7.27 ms | 1 |
| Subject coverage report | 1.53 ms | 1 |
| Student dashboard | 1.09 ms | 4 |
| Unanswered community board | 0.14 ms | 1 |

Measured on SQLite in development. MySQL 8 with proper memory settings is
faster for the aggregate queries and slightly slower for the trivial ones.

### Fixed during measurement

Tutor matching ran one query per tutor to work out their current load. At 800
tutors that was 800 round trips per match. Now a single `withCount`: 11 queries
down to 4, p95 13.8 ms down to 8.0 ms. It would have become unusable at a few
thousand tutors.

## Capacity

With 8 PHP workers and 12 ms of database time per page:

| Setup | Concurrent users |
|---|---|
| Single server, PHP-FPM | ~3,200 |
| Single server with Octane | ~9,600 |
| 4 app servers behind a balancer | ~38,000 |

These are arithmetic from measured query times, not a load test. Treat them as
the right order of magnitude, and confirm with a real load test before quoting
a number to a customer.

For context: at 3% of users online at once, 9,600 concurrent supports roughly
300,000 registered learners.

## What costs money before CPU does

Server capacity is rarely the first wall. Watch these instead:

- **AI assistant** — per question, forever. Capped by the monthly budget in admin.
- **Transcription** — per minute of audio. Same pattern.
- **Storage** — voice notes and resources only grow. Plan for object storage.
- **Email and SMS** — per message. WhatsApp costs more than SMS per conversation.

## Before launch

- [ ] `DB_CONNECTION=mysql`, `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis running; cache, session and queue all on `redis`
- [ ] Horizon running under Supervisor
- [ ] Scheduler cron installed (`* * * * * php artisan schedule:run`)
- [ ] `php artisan config:cache route:cache view:cache` in the deploy script
- [ ] Octane under Supervisor once the above is stable
- [ ] Load test with real traffic patterns
- [ ] Database backups verified by restoring one
