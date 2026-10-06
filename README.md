# DX Student Help

Web and mobile learning-support platform connecting South African students (Grade 8 to university)
with verified tutors through structured help requests, curriculum-aware study resources,
targeted announcements and a moderated learning community.

**Client:** The X Student Help (PTY) LTD, Durban, South Africa
**Delivery partner:** Mayura Consultancy Services (MCSpoorthi IT Solutions Private Limited)
**Status:** Sprint 1 — Discovery & Requirements

---

## What the platform does

Every student selects an academic pathway during onboarding. Everything they then see —
tutors, resources, announcements, community boards — is filtered to that context.

| Pathway | Institution | Personalisation |
|---|---|---|
| School | Public / Private | Grade 8–12, then challenging subjects |
| College | Public (NCV / NATED) or Private | Private: academic year + faculty |
| University | — | Qualification, year of study, faculty |

Core modules: identity and guardian consent, curriculum engine, tutor verification,
help requests and matching, safeguarded messaging, ratings, resource library with offline
downloads, announcements, community with moderation, progress and tutor recognition,
admin console and reports, monetisation engine, payments, PWA and store apps.

---

## Technology

| Layer | Technology |
|---|---|
| Backend / API | Laravel 11, PHP 8.3, versioned REST API, queues, scheduler |
| Front end | React 19 + TypeScript, Inertia.js, Tailwind CSS, shadcn/ui, Vite |
| Mobile | PWA + Android/iOS apps packaged with Capacitor from the same React build |
| Data | MySQL 8 / MariaDB 10.6+, Redis (cache, sessions, queues), off-server object storage |
| Infrastructure | Client VPS (WHM), Nginx, Supervisor, Cloudflare, encrypted off-server backups |
| CI/CD | GitHub Actions (test, build, deploy), Codemagic (signed mobile builds) |
| Quality | Pest, PHPStan, OWASP security testing, load testing |

---

## Repository layout

Single repository: the web app, API and mobile wrapper share one code base and one version.

```
app/                    Laravel domains, services, controllers
database/               migrations, seeders, factories
resources/js/           React 19 + TypeScript (web, PWA and mobile UI)
routes/
public/                 built assets, PWA manifest, service worker
mobile/                 Capacitor projects (android/, ios/) — from Sprint 13
docs/                   requirements, sprint plan, deployment guide
.github/workflows/      CI and deployment pipelines
```

## Branches

| Branch | Deploys to | Rule |
|---|---|---|
| `main` | Production | Release only. Tagged `v1.0.0`, etc. Mobile builds are cut from tags |
| `develop` | Staging | Integration branch shown at each sprint review |
| `feature/*` | — | Branch from `develop`, merge back by pull request with CI green |

---

## Local setup

The Laravel + React skeleton lands in Sprint 3. From then on:

```bash
git clone https://github.com/srinivas182/student-help.git
cd student-help
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run dev          # Vite
php artisan serve
php artisan queue:work
```

---

## Documentation

| Document | Location |
|---|---|
| Business Requirements (BRD) | `docs/DX_Student_Help_BRD.docx` |
| Software Requirements (SRS) | `docs/DX_Student_Help_SRS.docx` |
| Sprint Delivery Plan (14 sprints) | `docs/DX_Student_Help_Sprint_Plan.docx` |
| Deployment guide | `docs/DEPLOYMENT.md` |
| Contributing standards | `docs/CONTRIBUTING.md` |

---

## Security

Never commit `.env`, credentials, signing keys, keystores or API tokens.
Secrets live in GitHub Actions secrets, Codemagic encrypted variables, or on the server only.
Report a suspected exposure immediately so the credential can be rotated.
