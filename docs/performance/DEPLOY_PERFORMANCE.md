# Performance deployment steps

## 1. Redis

```bash
sudo apt install redis-server
sudo systemctl enable --now redis-server
redis-cli ping    # expect PONG
```

In `.env`:

```
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=your-password-here
```

Set a password in `/etc/redis/redis.conf` even on a single server. An open Redis
is one of the most commonly exploited services on the internet.

## 2. Horizon under Supervisor

`/etc/supervisor/conf.d/dx-horizon.conf`:

```ini
[program:dx-horizon]
process_name=%(program_name)s
command=php /var/www/dx/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/dx/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start dx-horizon
```

On every deploy: `php artisan horizon:terminate` so workers pick up new code.

## 3. Scheduler

```bash
crontab -e -u www-data
* * * * * cd /var/www/dx && php artisan schedule:run >> /dev/null 2>&1
```

Without this, requests are never escalated, resolved requests never close, and
review reminders never send.

## 4. Caches on deploy

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Clear them before any `.env` change takes effect.

## 5. Octane, when ready

```bash
php artisan octane:install --server=swoole
```

Supervisor program running `php artisan octane:start --server=swoole --workers=4
--task-workers=2 --max-requests=500`, with Nginx proxying to it.

Only add Octane once Redis, Horizon and the scheduler are stable. It changes how
the application boots, so debugging two new things at once is avoidable pain.

## 6. MySQL

```ini
innodb_buffer_pool_size = 2G     # roughly 60% of available RAM
max_connections = 300            # raise alongside Octane
slow_query_log = 1
long_query_time = 1
```
