# Capacity and scaling

What the platform handles, what it costs, and what to add when.

## Where we are

The application is stateless: no session state on the app server, queued
background work, cached reference data, and indexed queries. That is what makes
everything below possible without rewriting anything.

Installed and configured:

- **Redis** for cache, sessions and queues (`predis/predis`)
- **Horizon** with three queue tiers, so a ten-minute lesson generation never
  delays a guardian consent email
- **Octane** available for the throughput step
- **Scheduler** running request escalation, housekeeping and review reminders
- **Composite indexes** on the hot query paths

## Queue tiers

| Queue | What runs there | Why separate |
|---|---|---|
| `urgent` | Guardian consent, invitations, one-time codes, security alerts | A person is waiting for these |
| `notifications`, `default` | Platform notifications, routine email | Normal volume, normal priority |
| `generation`, `transcription` | AI lesson generation, voice transcription | Slow and expensive; must never block the rest |

## Measured query performance

From `php artisan platform:benchmark` against the demo dataset:

| Query | p95 | Queries |
|---|---|---|
| Curriculum subjects (cached) | under 1 ms warm | 0 after first read |
| Curriculum subjects (uncached) | ~6 ms | 1 |
| Tutor queue | ~5 ms | 3 |
| Student request list | under 1 ms | 3 |
| Matching eligible tutors | ~9 ms | 9 |

Run this again against production-sized data before launch, and after any schema
change. The matching query is the one to watch: it grows with tutor count.

## Capacity by configuration

These are engineering estimates, to be replaced with measured numbers from the
k6 script once staging exists.

| Configuration | Concurrent users | Monthly infrastructure |
|---|---|---|
| Single VPS, no Redis, no workers | 200–500 | existing VPS |
| + Redis, Horizon workers, MySQL tuning | 2,000–5,000 | same server, larger |
| + Octane (Swoole) | 10,000–15,000 | same server |
| + load balancer, 3 app servers, read replica | 50,000–100,000 | materially more |

Launch sits comfortably in the second tier. Nothing above it is a code change —
it is a provisioning decision, taken when traffic demands it rather than before.

## Load testing

```bash
k6 run -e BASE_URL=https://student-help.rightally.io docs/performance/load-test.js
```

Ramps to 500 virtual users over nine minutes, modelling an evening peak.
Thresholds: p95 under 800 ms, failure rate under 1%. Run against staging.

## What actually bites first

Server count is rarely the first constraint. In order of likelihood:

1. **AI costs.** The assistant and transcription scale linearly with usage and
   have no ceiling except the budget caps already built. Watch the spend graph
   in admin, not the CPU graph.
2. **File storage.** Voice notes, resources and verification documents only ever
   grow. Plan object storage before the disk fills.
3. **Database connections.** Octane holds connections open; raise MySQL
   `max_connections` alongside it or the gain turns into errors.
4. **Email reputation.** A sudden volume increase on a new sending domain lands
   in spam. Warm the domain gradually before any launch campaign.

## Deployment checklist

- `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- `php artisan horizon` under Supervisor, with restart on deploy
- `php artisan schedule:run` on cron every minute
- `php artisan config:cache route:cache view:cache` on deploy
- `php artisan optimize` after composer install
- Octane only once the above are stable, never as the first change
