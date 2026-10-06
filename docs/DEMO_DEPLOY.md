# Deploying the demo to student-help.rightally.io

A compact version of `DEPLOYMENT.md` for the client demo environment.

## 1. cPanel setup (WHM)

1. Create the subdomain `student-help.rightally.io` with the application in
   `/home/<user>/student-help`.
2. Create a MySQL database and user, and grant all privileges.
3. Set the PHP version for the subdomain to **8.3** and enable:
   `bcmath, mbstring, intl, gd, zip, pdo_mysql, opcache, exif, fileinfo`.
4. Point the subdomain's document root at `/home/<user>/student-help/public`.
5. Run AutoSSL so the subdomain has a certificate.

## 2. Deploy the code

```bash
cd ~/student-help
git clone https://github.com/srinivas182/student-help.git .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# edit .env: database credentials, mail credentials, APP_URL
php artisan migrate --seed        # --seed loads the curriculum and demo accounts
php artisan storage:link
php artisan config:cache route:cache view:cache
```

Build the front-end assets (locally or in CI, then upload `public/build`):

```bash
npm ci && npm run build
```

## 3. Background processing

Queue worker under Supervisor:

```
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Scheduler cron entry:

```
* * * * * cd /home/<user>/student-help && php artisan schedule:run >> /dev/null 2>&1
```

If Redis is not installed yet, set `SESSION_DRIVER=database`, `CACHE_STORE=database`
and `QUEUE_CONNECTION=database` in `.env`. The demo runs fine without Redis.

## 4. Demo accounts

All demo accounts use the password `password`. Change or remove them before any
public exposure.

| Role | Email | Notes |
|---|---|---|
| Administrator | admin@dxstudenthelp.co.za | Demo admin account |
| Moderator | moderator@dxstudenthelp.co.za | Safeguarding queue |
| Student (minor) | student@dxstudenthelp.co.za | Grade 11, guardian consent approved |
| Student (adult) | student2@dxstudenthelp.co.za | University, 1st year IT |
| Tutor (verified) | tutor@dxstudenthelp.co.za | Maths and Physical Sciences |
| Tutor (pending) | tutor4@dxstudenthelp.co.za | Sits in the verification queue |

## 5. What to show the client

1. Register with a date of birth under 18 — the guardian consent fields appear
   automatically and the account is restricted until the guardian approves.
2. Walk the onboarding wizard: School → Public → Grade 11 → subjects, with the
   selection tracker at the bottom of the screen.
3. Repeat as College (NCV or NATED) and as University, to show the same wizard
   adapting to a completely different curriculum shape.
4. Sign in as the Grade 11 learner to see the personalised dashboard.

## 6. Resetting the demo

```bash
php artisan migrate:fresh --seed
```
