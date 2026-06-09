# numa (Laravel 11)

Production-ready Laravel 11 baseline with:
- Blade
- Vite
- Tailwind CSS
- Alpine.js
- Select2
- PostgreSQL-first configuration
- Queue-first defaults

## Quick Start

1. Install dependencies:
```bash
composer install
npm install
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Generate app key:
```bash
php artisan key:generate
```

4. Create one PostgreSQL database (`numa` by default), then run:
```bash
php artisan migrate
php artisan db:seed
```

5. Start services:
```bash
php artisan serve
npm run dev
php artisan queue:work --queue=default
php artisan queue:work --queue=automation --tries=1 --timeout=600
```

## Environment Files

- `.env.local.example`: local development template.
- `.env.production.example`: production template (`APP_DEBUG=false`).
- `.env.example`: default starter template.

Required env coverage includes:
- App URL: `APP_URL`
- Queue driver: `QUEUE_CONNECTION`
- Queue table: `DB_QUEUE_TABLE` (`queue_jobs`)
- File storage: `FILESYSTEM_DISK`
- Gemini: `GEMINI_API_KEY` (+ `GEMINI_MODEL`)
- Database: `DB_*`
- Mail sending: `MAIL_*`

## Design System Baseline

- Strict light mode only (no dark mode toggle).
- Purple Aura chroma glassmorphism (translucent cards, blur, soft glow).
- Lightweight motion with `prefers-reduced-motion` support.
- Reusable Blade components in `resources/views/components` for:
  - layout
  - cards
  - badges
  - tables
  - modals
  - forms
  - alerts
  - empty states

## Internationalization

- User-facing strings are sourced from translation files:
  - `lang/en/ui.php`
  - `lang/fr/ui.php`
  - `lang/en/errors.php`
  - `lang/fr/errors.php`

## Select2 Standard

- All `<select>` fields are auto-initialized as Select2 unless marked with `data-native-select`.
- Selects inside modal components are also initialized automatically.

## Setup Checklist Guard

If required env variables are missing:
- Admin requests see a friendly setup checklist (variable names only).
- Non-admin requests receive a generic `503` page.

Admin detection:
- Always admin in `local`.
- In non-local, request IP must be in `SETUP_ADMIN_IPS` or user must pass `access-admin-pages` gate.

## Production Safety Defaults

- Use `.env.production.example` as baseline.
- Keep `APP_DEBUG=false` in production.
- Branded Purple Aura error pages are provided for `403`, `404`, `419`, `429`, `500`, `503`.

## Security and Guardrails

- Authorization baseline provider: `app/Providers/AuthServiceProvider.php`.
- Decision and AI guardrails are codified in:
  - `config/guardrails.php`
  - `app/Support/Guardrails/DecisionGuard.php`
  - `app/Support/Guardrails/AiOutputMode.php`
- Tenant-scoped query helper trait:
  - `app/Models/Concerns/BelongsToCompany.php`

## Auth and Tenant Model

- Roles: `admin`, `recruiter`, `manager`, `employee`, `candidate`.
- Tenant isolation uses `company_id` and policies/gates.
- Auth screens included:
  - login
  - password reset request
  - password reset
  - email verification
  - profile settings
- Admin-only user management:
  - `/admin/users`
  - role changes require confirmation and are audit logged.

## Seeded Login

After `php artisan migrate:fresh --seed`:
- Company: `numa Demo`
- Email: `admin@example.com`
- Password: `password`

## Build

```bash
npm run build
```

## Docker Deployment (Phase 5)

This repository includes:
- `Dockerfile` for the main Laravel application.
- `Dockerfile.playwright-worker` for Playwright queue processing with Chromium dependencies.
- `docker-compose.yml` with isolated services for:
  - `app` (HTTP)
  - `queue-worker` (default queue)
  - `automation-worker` (Playwright queue only)
  - `scheduler` (Laravel schedule loop)
  - `postgres`

### Start stack

```bash
cp .env.production.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
```

### Queue isolation for automation

Automation jobs are dispatched to `MULTIPOSTING_AUTOMATION_QUEUE=automation` by default.
This keeps heavy browser sessions off the web request thread and off the default queue worker.

## Multiposting RPA Automation (Phase 4)

For job boards without XML ingestion, this project includes a Playwright worker in `scripts/rpa`.

### Worker setup

```bash
cd scripts/rpa
npm install
npm run install-browser
```

### Automatic session bootstrap (no cookie copy/paste)

Set env values:
- `RPA_LINKEDIN_EMAIL=...`
- `RPA_LINKEDIN_PASSWORD=...`
- `RPA_LINKEDIN_SESSION_STATE_PATH=storage/app/private/rpa_sessions/linkedin.json`
- `RPA_INDEED_EMAIL=...`
- `RPA_INDEED_PASSWORD=...`
- `RPA_INDEED_SESSION_STATE_PATH=storage/app/private/rpa_sessions/indeed.json`

The worker automatically:
1. Reuses saved Playwright session state if present.
2. Falls back to credential login if session is missing/expired.
3. Saves refreshed session state for next runs.

Optional one-time manual fallback when LinkedIn forces challenge/2FA:
- `MULTIPOSTING_AUTOMATION_HEADLESS=false`
- `RPA_ALLOW_INTERACTIVE_LOGIN=true`

### Enable automation

Set env values:
- `MULTIPOSTING_AUTOMATION_ENABLED=true`
- `MULTIPOSTING_AUTOMATION_PLATFORMS=linkedin,indeed`
- `MULTIPOSTING_AUTOMATION_SCRIPT_PATH=scripts/rpa/post-job.mjs`
- `RPA_LINKEDIN_SELECTORS_PATH=scripts/rpa/selectors/linkedin.json`
- `RPA_INDEED_SELECTORS_PATH=scripts/rpa/selectors/indeed.json`

When enabled, publishing for configured platforms queues a worker job instead of immediate manual completion.

### Manual runner command

```bash
php artisan multiposting:automation-run {job_posting_id} --sync
```

Without `--sync`, it queues `RunJobBoardAutomationJob`.

