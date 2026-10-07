# Security

## Pre-deployment check

```bash
php artisan platform:security-check
```

Fifteen checks covering debug mode, environment, session cookies, HTTPS,
drivers, default passwords, two-factor on super admins, open bypasses, PayFast
sandbox mode, file permissions, a stray `.env` in public, and leftover
load-test accounts. Exits non-zero on any failure, so it belongs in the
deployment script before the app goes live.

## Audit findings, October 2026

Audited the whole codebase. What was already right, and what was not.

### Already in place

| Area | State |
|---|---|
| Dependencies | `composer audit` and `npm audit` both clean |
| SQL injection | Eloquent and the query builder throughout; no raw interpolation |
| Password policy | 10 characters, mixed case, numbers, checked against known breaches |
| Login throttling | Rate limited per email and IP |
| Two-factor | Authenticator app, recovery codes, time-limited bypass, break-glass rule |
| Admin routes | Behind `staff` middleware, with per-permission gates on top |
| Uploads | Mime and size validated on every path; files on the private disk, streamed with an audit entry |
| Mass assignment | Every model uses `$fillable`; none use `$guarded = []` |
| Secrets | API keys, gateway credentials and 2FA secrets encrypted at rest and hidden from serialisation |
| Payments | Signature, source IP, amount and a confirmation call to PayFast, all four required |

### Fixed in this sprint

**1. No security headers.** The app sent none. Added `SecurityHeaders`
middleware: CSP, `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` and HSTS
over HTTPS. The CSP matters most: if a contact-filter bypass ever let a script
tag through a message, the browser refuses to run it.

**2. No rate limiting beyond login.** The study assistant could be called in a
loop, costing real money; search and uploads were unbounded. Seven named
limiters now cover the assistant, search, request creation, messaging, one-time
codes, uploads and webhooks. Set generously: no honest learner meets them.

**3. `url` validation accepted any scheme.** Laravel's `url` rule passes
`javascript:` and `data:`, and those values ended up in an `href` a student
could click. New `SafeUrl` rule allows only http and https, and blocks
loopback, private ranges and cloud metadata addresses so a stored link cannot
probe the server's own network from an administrator's browser.

**4. External links missing `noopener`.** Links used `rel="noreferrer"` only.
Now `rel="noopener noreferrer"` for older browsers.

**5. No pre-deployment verification.** Nothing stopped a deploy with
`APP_DEBUG=true` or a staff account still on the demo password. That is what
`platform:security-check` is for.

### Accepted, not fixed

**Moderators can read any conversation.** This is deliberate and disclosed on
the landing page and in the privacy notice. Child safety requires it. Every
read is written to the audit log.

**Tutor documents are visible to administrators.** Necessary for verification.
Access is logged with the administrator's identity and timestamp.

## Before going live

- [ ] `platform:security-check` passes
- [ ] Database and storage backups verified by actually restoring one
- [ ] Super admin accounts have two-factor set up
- [ ] Demo accounts removed or their passwords changed
- [ ] PayFast switched out of sandbox, live credentials entered in admin
- [ ] `PORTAL_ALLOW_SHARED_HOST=false`
- [ ] TLS certificate installed and auto-renewing
- [ ] Error reporting configured so failures are seen, not silently logged

## Reporting a vulnerability

Email the address in the privacy notice. Please allow time to fix before
disclosing publicly.
