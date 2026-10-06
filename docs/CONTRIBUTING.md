# Contributing standards — DX Student Help

## Branching

- Branch from `develop`: `feature/REQ-07-matching-engine`, `fix/AUTH-06-lockout`.
- Reference the SRS requirement ID in the branch name and pull request title.
- Merge to `develop` by pull request only, with CI green. `main` receives releases from `develop`.

## Definition of done

1. Acceptance criteria met and demonstrable on staging.
2. Tests cover the behaviour (Pest: feature tests for endpoints, unit tests for domain logic).
3. `composer test` and `vendor/bin/phpstan analyse` pass locally and in CI.
4. Authorisation enforced on every new route and screen — never only in the UI.
5. Works from 360 px width; meets WCAG 2.1 AA contrast and labelling.
6. Documentation and the OpenAPI specification updated.

## Code standards

- PSR-12, Laravel conventions, PHPStan level 8.
- Controllers stay thin. Business logic lives in domain services under `app/Domains/*`.
- Form Requests for validation, API Resources for responses, Policies for authorisation.
- No raw queries without a documented reason; guard against N+1 with eager loading.
- React: TypeScript everywhere, function components and hooks. Shared UI in `resources/js/components/ui`.
- Tailwind utilities with design tokens — no ad-hoc hex colours.

## Safety-critical rules

This platform is used by minors. The following are not optional.

- A user under 18 without recorded guardian consent must not be able to message, raise a
  help request or post publicly. Enforce server-side in policies, not in the UI.
- All student–tutor communication stays inside the platform. Contact details are masked
  on the server before storage and display.
- Moderators can read any conversation; every such access is written to the audit log.
- All administrative, moderation and security-relevant actions are audit-logged.
- Tutor verification documents live in private storage, served by time-limited URLs only.

## Commits

Conventional commits: `feat(requests): escalate unmatched requests after 24h`,
`fix(auth): lock account after five failed attempts`, `docs(srs): update consent rules`.

## Never commit

`.env` files, credentials, keystores, signing certificates, API tokens, client data,
or real user data in seeders.
