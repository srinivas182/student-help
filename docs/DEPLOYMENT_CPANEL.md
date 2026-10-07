# Deploying on WHM / cPanel

Target: `x-student-help.mcs.bz` (students) and `x-teacher-help.mcs.bz` (teachers).
Both domains serve the **same application, same database, same files**. The host
header decides which portal a visitor sees.

Two roles are needed: **root in WHM** for server-wide pieces (PHP extensions,
Redis, Supervisor) and the **cPanel user** for the application itself.

---

## Part A — as root in WHM

### A1. PHP 8.3 with the right extensions

WHM → Software → **EasyApache 4** → Customise → PHP Versions → tick **PHP 8.3**.

Then under Extensions, ensure these are ticked for `ea-php83`:

```
bcmath  ctype  curl  dom  fileinfo  gd  intl  mbstring
mysqlnd  openssl  pdo  pdo_mysql  soap  tokenizer  xml  zip
```

Also set, in WHM → MultiPHP INI Editor → `ea-php83`:

```
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
```

Then WHM → MultiPHP Manager → set the cPanel account's PHP to **ea-php83**.

### A2. Remove the function restrictions cPanel sets by default

cPanel disables `proc_open`, `proc_get_status`, `exec` and others. Composer and
Laravel need them. In **MultiPHP INI Editor → Editor mode → ea-php83**, make
`disable_functions` empty or remove at least:

```
proc_open, proc_close, proc_get_status, proc_nice, exec, shell_exec, popen
```

Without this, `composer install` fails with a confusing error about
`proc_open()`.

### A3. Redis

```bash
dnf install -y redis        # or: yum install -y redis
systemctl enable --now redis
```

Set a password — on a shared server this is not optional:

```bash
# /etc/redis.conf
requirepass "a-long-random-string"
bind 127.0.0.1
```

```bash
systemctl restart redis
redis-cli -a 'a-long-random-string' ping     # expect PONG
```

The application uses **predis**, a pure-PHP client, so no `phpredis` extension
is needed.

### A4. Supervisor, for the queue workers

```bash
dnf install -y supervisor
systemctl enable --now supervisord
```

Configuration comes later, in step B9, once the app directory exists.

### A5. Node.js, for building assets

```bash
dnf module install -y nodejs:20
node -v     # expect v20.x
```

Alternatively build assets locally and upload `public/build` — then Node is not
needed on the server at all.

---

## Part B — as the cPanel user

### B1. Create the subdomains with the right document root

cPanel → **Domains** → Create a Domain.

| Domain | Document root |
|---|---|
| `x-student-help.mcs.bz` | `/home/CPANELUSER/student-help/public` |
| `x-teacher-help.mcs.bz` | `/home/CPANELUSER/student-help/public` |

**Both point at the same `public` directory.** That is deliberate, not a
mistake. Pointing the document root at `public/` keeps `.env`, `vendor/` and
`storage/` outside the web root entirely.

If cPanel will not accept the same root twice, create the student domain
normally and add the teacher domain as an **Alias**.

### B2. Database

cPanel → **MySQL Databases**:

1. Create database, e.g. `dxsh_app`
2. Create user, e.g. `dxsh_user`, with a generated password
3. Add the user to the database with **All Privileges**

cPanel prefixes both with the account name. Note the full names.

### B3. Get the code

Terminal (cPanel → Terminal), as the cPanel user:

```bash
cd ~
git clone https://github.com/srinivas182/student-help.git student-help
cd student-help
```

The repository is private, so use a personal access token when prompted, or:

```bash
git clone https://USERNAME:TOKEN@github.com/srinivas182/student-help.git student-help
```

### B4. Composer

```bash
cd ~
curl -sS https://getcomposer.org/installer | /usr/local/bin/ea-php83
# leaves composer.phar in ~
```

### B5. Environment

```bash
cd ~/student-help
cp deploy/.env.production.example .env
nano .env
```

Fill in: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_PASSWORD`.
Leave `APP_KEY` empty for now.

```bash
/usr/local/bin/ea-php83 ~/composer.phar install --no-dev --optimize-autoloader
/usr/local/bin/ea-php83 artisan key:generate
```

### B6. Permissions

```bash
cd ~/student-help
chmod -R 775 storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
chmod 600 .env
```

`.env` at 600 means only the cPanel user can read it.

### B7. Migrate and seed

```bash
/usr/local/bin/ea-php83 artisan migrate --force
/usr/local/bin/ea-php83 artisan db:seed --force
/usr/local/bin/ea-php83 artisan storage:link
```

The seed creates the curriculum, languages, roles, plans, settings, the seven
demo lessons and the demo accounts.

**Change the demo passwords before the site is public.** See step C4.

### B8. Build the front end

```bash
npm ci
npm run build
```

If Node is unavailable, build on your own machine and upload `public/build`.

### B9. Queue workers

As **root**, with the real values substituted:

```bash
sed -e 's/CPANELUSER/youruser/g' -e 's/APPDIR/student-help/g' \
    /home/youruser/student-help/deploy/supervisor-queue.conf \
    > /etc/supervisord.d/dx-queue.ini

supervisorctl reread
supervisorctl update
supervisorctl status dx-queue:*
```

### B10. Scheduler

cPanel → **Cron Jobs** → add, running every minute:

```
* * * * * /usr/local/bin/ea-php83 /home/CPANELUSER/student-help/artisan schedule:run >> /dev/null 2>&1
```

One entry. Laravel decides what runs when.

### B11. SSL

cPanel → **SSL/TLS Status** → select both subdomains → **Run AutoSSL**.

Wait for both to show a valid certificate, then confirm `https://` works before
going further. `SESSION_SECURE_COOKIE=true` means **login will not work over
plain http** — that is intended.

### B12. Cache and go

```bash
cd ~/student-help
/usr/local/bin/ea-php83 artisan config:cache
/usr/local/bin/ea-php83 artisan route:cache
/usr/local/bin/ea-php83 artisan view:cache
/usr/local/bin/ea-php83 artisan platform:security-check
/usr/local/bin/ea-php83 artisan platform:health
```

Both commands must pass before you call it live.

---

## Part C — testing it live

### C1. The two front doors

| Check | Expected |
|---|---|
| `https://x-student-help.mcs.bz` | Student landing page, indigo |
| `https://x-teacher-help.mcs.bz` | Teacher landing page, teal |
| Student site on a phone | Bottom tab bar: Home, Ask, Learn, Community, Me |
| Log in as a tutor on the student domain | Redirected to the teacher domain |

### C2. The student journey

1. Register a new student account, Grade 11, Mathematics
2. Dashboard shows **what to do next**, not just a greeting
3. Open **Learn** → the Grade 11 Maths lesson → switch to isiZulu
4. Finish the lesson → **Finish and test yourself** → Basic level
5. Get one wrong on purpose → the result names the mistake
6. Ask a question → it appears in the tutor queue

### C3. The tutor journey

1. Log in at the teacher domain as `tutor@dxstudenthelp.co.za`
2. The student's question is in the queue → accept it → reply
3. Back as the student, the dashboard leads with **Your question was answered**

### C4. Secure the demo accounts

Before sharing the URL with anyone outside the team:

```bash
/usr/local/bin/ea-php83 artisan tinker
```

```php
User::whereIn('role', ['admin','super_admin','moderator'])->get()
    ->each(fn ($u) => $u->update(['password' => Hash::make('a-strong-unique-password')]));
```

Then set up two-factor on the super admin account: **Profile → Two-factor**.
`platform:security-check` fails until at least one has it.

### C5. Gateways

Admin → **Gateways** → configure email → **Send me a test email**. Guardian
consent emails do not send until this is done.

---

## Deploying an update

```bash
cd ~/student-help
bash deploy/deploy.sh
```

Pulls, installs, builds, runs the security check, migrates behind maintenance
mode, re-caches, restarts workers. It refuses to continue if the security check
fails.

---

## When something is wrong

| Symptom | Cause |
|---|---|
| 500 on every page | `storage/logs/laravel.log`; usually permissions or a missing `.env` |
| `proc_open() has been disabled` | Step A2 not done |
| Blank page, no styling | `npm run build` not run, or `public/build` missing |
| Login redirects back to login | AutoSSL not finished; the secure cookie needs https |
| Nothing automated happens | Cron entry missing — check `platform:health` |
| Jobs queue up and never run | `supervisorctl status dx-queue:*` |
| Wrong portal shown | `PORTAL_ALLOW_SHARED_HOST` must be `false`, hosts must match `.env` |

Always after changing `.env`:

```bash
/usr/local/bin/ea-php83 artisan config:cache
```

Cached config ignores `.env` until you do.
