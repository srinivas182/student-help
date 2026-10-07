# Deploying on a cPanel / WHM VPS

Target: `x-student-help.mcs.bz` (students) and `x-teacher-help.mcs.bz`
(teachers), both served by one application from one folder.

Everything below assumes root access to WHM and SSH, and a cPanel account to
host the files.

---

## 1. Create the cPanel account and subdomains

**WHM → Account Functions → Create a New Account**

| Field | Value |
|---|---|
| Domain | `mcs.bz` (or use an existing account) |
| Username | e.g. `dxhelp` |
| Package | one with shell access enabled |

If `mcs.bz` already has a cPanel account, use it rather than creating another.

**cPanel → Domains → Create A Domain**, twice:

- `x-student-help.mcs.bz` → document root `/home/dxhelp/dxapp/public`
- `x-teacher-help.mcs.bz` → document root `/home/dxhelp/dxapp/public`

Both point at **the same** document root. That is deliberate: one application,
two front doors. Uncheck "Share document root" if cPanel offers it, and set the
path manually.

---

## 2. Set the PHP version

**WHM → Software → MultiPHP Manager** — set the account to **PHP 8.3**.

**WHM → EasyApache 4** — make sure these extensions are installed for 8.3:

```
bcmath  ctype  curl  dom  fileinfo  gd  intl  mbstring
mysqlnd  openssl  pdo_mysql  sodium  tokenizer  xml  zip
```

`sodium` matters — it signs update packages. `intl` matters for currency and
date formatting.

Verify over SSH:

```bash
php -v
php -m | sort | tr '\n' ' '
```

---

## 3. Create the database

**cPanel → MySQL Databases**

1. Database: `dxhelp_prod`
2. User: `dxhelp_app` with a long generated password
3. Add the user to the database with **ALL PRIVILEGES**

Keep the exact names and the password; they go into `.env` next.

---

## 4. Get the code onto the server

```bash
ssh dxhelp@your-server-ip
cd ~
git clone https://github.com/srinivas182/student-help.git dxapp
cd dxapp
```

If the repo is private, use a personal access token:

```bash
git clone https://USERNAME:TOKEN@github.com/srinivas182/student-help.git dxapp
```

---

## 5. Install dependencies

Composer, if it is not already installed:

```bash
cd ~
curl -sS https://getcomposer.org/installer | php
mkdir -p ~/bin && mv composer.phar ~/bin/composer
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc && source ~/.bashrc
```

Then:

```bash
cd ~/dxapp
composer install --no-dev --optimize-autoloader
```

Front-end assets. Easiest is to build them on your own machine and upload
`public/build`, because Node on a cPanel box is fiddly. If you prefer to build
on the server:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install 20
npm ci
npm run build
```

---

## 6. Redis

Redis needs root. Over SSH as root:

```bash
# AlmaLinux / CloudLinux / CentOS
dnf install -y redis
systemctl enable --now redis
redis-cli ping     # expect: PONG
```

Set a password in `/etc/redis/redis.conf`:

```
requirepass a-long-random-string
```

Then `systemctl restart redis` and put that string in `.env` as
`REDIS_PASSWORD`.

**If Redis cannot be installed**, the platform still runs. In `.env` use:

```
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

and run `php artisan queue:table && php artisan migrate`. Slower, and
`platform:security-check` will flag it, but nothing breaks.

---

## 7. Configure the environment

```bash
cd ~/dxapp
cp .env.production.example .env
nano .env
```

Fill in `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_PASSWORD`. Then:

```bash
php artisan key:generate
```

Check the two portal hosts are exactly your subdomains, and that
`PORTAL_ALLOW_SHARED_HOST=false`.

---

## 8. Permissions

```bash
cd ~/dxapp
chmod -R 755 storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 755 {} \;
chmod 600 .env
```

`.env` must not be world-readable. On cPanel the account user owns everything
already, so no `chown` is needed.

---

## 9. First deployment

```bash
cd ~/dxapp
php artisan migrate --force
php artisan db:seed --force          # curriculum, languages, roles, plans, demo lessons
php artisan storage:link
./deploy.sh
```

`deploy.sh` backs up the database, migrates, caches config and routes, restarts
workers, and runs the security check. It refuses to bring the site up if the
check fails.

---

## 10. Queue worker and scheduler

**cPanel → Advanced → Cron Jobs.**

Scheduler, every minute:

```
* * * * * cd /home/dxhelp/dxapp && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1
```

Queue worker. Supervisor is better, but needs root; this keeps a worker alive
without it:

```
* * * * * cd /home/dxhelp/dxapp && pgrep -f "queue:work" > /dev/null || /usr/local/bin/ea-php83 artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> storage/logs/worker.log 2>&1
```

With root, use Supervisor instead:

```ini
; /etc/supervisord.d/dxhelp-worker.ini
[program:dxhelp-worker]
command=/usr/local/bin/ea-php83 /home/dxhelp/dxapp/artisan queue:work --sleep=3 --tries=3
directory=/home/dxhelp/dxapp
user=dxhelp
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/home/dxhelp/dxapp/storage/logs/worker.log
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl status
```

---

## 11. SSL

**WHM → SSL/TLS → Manage AutoSSL** — run AutoSSL for the account. Both
subdomains must already resolve to the server, or issuance fails.

Then force HTTPS. cPanel's **Domains** screen has a "Force HTTPS Redirect"
toggle per domain; turn it on for both.

Confirm:

```bash
curl -sI https://x-student-help.mcs.bz | head -5
```

---

## 12. Protect the application folder

The document root is `~/dxapp/public`, so the rest of the app is already
outside it. Confirm nothing above `public` is reachable:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://x-student-help.mcs.bz/.env
curl -s -o /dev/null -w "%{http_code}\n" https://x-student-help.mcs.bz/storage/logs/laravel.log
```

Both must return **403** or **404**. A 200 on either means the document root is
pointing at the wrong folder — fix it before going further.

---

## 13. Live tests

Work through these in order. Each one should take under a minute.

### Routing and portals

```bash
curl -sI https://x-student-help.mcs.bz | grep -i "HTTP/\|content-security"
curl -sI https://x-teacher-help.mcs.bz | grep -i "HTTP/"
```

- Student landing page loads at the student domain
- Teacher landing page loads at the teacher domain, visibly different
- Signing in as a tutor on the student domain redirects to the teacher domain

### Security headers

```bash
curl -sI https://x-student-help.mcs.bz | grep -iE "content-security|x-frame|strict-transport|referrer"
```

Expect CSP, `X-Frame-Options: SAMEORIGIN`, `Strict-Transport-Security` and
`Referrer-Policy`.

### Core journeys

| # | Test | Expected |
|---|---|---|
| 1 | Register a student, Grade 11, Maths | Onboarding completes, dashboard shows next actions |
| 2 | Open a lesson in Learn | Eight segments, narration button, notes and flashcards |
| 3 | Take the Basic assessment | Only Basic unlocked; 80% unlocks Easy |
| 4 | Get one wrong deliberately | Result names the misconception |
| 5 | Ask a tutor a question | Appears in the tutor queue |
| 6 | Accept it as the tutor, reply | Student sees the reply, contact details stripped |
| 7 | Search "trinomial" | Results grouped by kind |
| 8 | Register a learner under 18 | Guardian email sent, self-study still open |
| 9 | Upload a resource as a tutor | Held for approval, visible to admin |
| 10 | Admin → Gateways → send test email | Arrives |

### Background work

```bash
cd ~/dxapp
php artisan schedule:list
php artisan queue:work --once        # should process or report an empty queue
tail -20 storage/logs/laravel.log
```

### Performance

```bash
php artisan platform:benchmark
php artisan platform:capacity
```

---

## 14. Before telling anyone the URL

```bash
php artisan platform:security-check
```

Every check must pass. Then:

- Change the demo account passwords, or delete the demo accounts
- Set up two-factor on your super admin account
- Configure a real email provider in **Admin → Gateways** and send a test
- Enter PayFast live credentials if payments should work, and switch off
  sandbox
- Take a fresh backup and **restore it somewhere else to prove it works**

---

## Routine operations

| Task | Command |
|---|---|
| Deploy an update | `cd ~/dxapp && git pull && ./deploy.sh` |
| View recent errors | `tail -100 storage/logs/laravel.log` |
| Clear caches | `php artisan optimize:clear` |
| Restart workers | `php artisan queue:restart` |
| Database backup | `mysqldump -u USER -p DB > backup.sql` |
| Check security config | `php artisan platform:security-check` |

---

## If something goes wrong

**500 error, blank page** — `tail -50 storage/logs/laravel.log`. Usually
permissions on `storage/` or a missing `.env` value.

**"No application encryption key"** — `php artisan key:generate`, then
`php artisan config:cache`.

**Styles missing** — `public/build` was not uploaded or `npm run build` was not
run.

**Wrong portal served** — check `STUDENT_PORTAL_HOST` and
`TEACHER_PORTAL_HOST` match exactly, with no `https://` and no trailing slash,
then `php artisan config:cache`.

**Changes not taking effect** — config is cached. `php artisan config:cache`
after every `.env` edit.

**Queued work not happening** — the worker is not running. Check the cron job
or `supervisorctl status`.
