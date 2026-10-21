# AGENTS.md — NEUST Faculty Evaluation System

## Environment quirk (read first)
- **PHP is Windows-only (Laragon). From this WSL workspace `php` is NOT on PATH.**
  ```bash
  export PATH="/mnt/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:$PATH"
  php.exe artisan test            # version dir changes if Laragon PHP is upgraded
  ```
- Bare `composer` fails from WSL (`php: not found`). Either run composer from a Windows terminal or:
  ```bash
  php.exe 'C:\ProgramData\ComposerSetup\bin\composer.phar' test
  ```
- `node` / `npm` run natively in WSL — no workaround needed.

## Commands
```bash
composer setup   # install, .env, key, migrate, npm install, npm build (run from Windows)
composer dev     # serve + queue:listen + pail + Vite concurrently
composer test    # config:clear → php artisan test
php.exe artisan test --filter=YearLevelTest   # single test (from WSL)
npm run dev | npm run build                   # Vite only
```
- **No lint/format is wired up.** No CI workflows, no `lint` script. Pint is installed (default preset, no `pint.json`): run `vendor/bin/pint` via php.exe if needed. `.prettierrc` exists but prettier is **not** an npm dependency — editor-only.
- Tests: SQLite `:memory:` (phpunit.xml forces sync queue, array cache/session/mail) — no external services. Feature tests use `RefreshDatabase`. Locations: `tests/Feature/`, `tests/Unit/`.

## Stack
- **Backend:** Laravel 12, PHP 8.2+, Spatie Permission, Sanctum (bearer token in localStorage — **not** cookie/session SPA mode), Socialite (Google OAuth), SQLite (dev).
- **Frontend:** Vue 3 SPA + Vue Router (lazy views in `resources/js/views/`), Vite 7, **Bootstrap 5** (CSS + JS via `window.bootstrap`, composables in `resources/js/composables/`), custom CSS design tokens in `resources/css/app.css`. Also Chart.js, SweetAlert2, date-fns.
  - `tailwindcss` and `sass-embedded` appear in package.json but are **unused in source** — no Tailwind directives, no `.scss` files. Don't reach for them.
- **AI:** Gemini via `App\Services\AiService` (`responseMimeType: application/json`; key `GEMINI_API_KEY`, model override `GEMINI_MODEL`).

## Architecture
- **SPA:** `routes/web.php` catch-all serves `resources/views/app.blade.php` for all non-`/api` routes; Vue Router (`createWebHistory`) owns client routing.
- **Subdirectory deploy:** router (`getBasePath()`) and axios (`getBaseURL()` in `resources/js/services/api.js`) detect the `/evaluation_system/public` prefix from `window.location.pathname`. Preserve this when editing either file.
- **API:** everything in `routes/api.php`, `auth:sanctum`. Authorization is **fail-closed** via `permission:` middleware — backend is the only real enforcement; the frontend only mirrors capability names for UI visibility.
- **Permission catalog:** `config/authorization.php` is the single source of truth. Adding a permission = add entry + `php artisan permissions:sync`. Data-scope permissions (`.own` / `.team` / `.all`, bare base = own) are resolved twice in parallel: `AuthorizationService::scope()` (backend) and `resources/js/helpers/permissions.js` `scope()` (frontend) — keep them in sync.
- **Auth flow:** token/user/permissions live in localStorage; `refreshPermissions()` runs at SPA boot; axios 401 interceptor clears storage and redirects — except public `/qr/` pages (QR feedback endpoints are intentionally unauthenticated).
- **UUIDs:** all models use `HasUuids`.
- **Evaluations:** polymorphic `evaluatee_type` + `evaluatee_id` with a legacy `faculty_id` fallback — `Evaluation::scopeForFacultyMember()` and `getEvaluatee()` must keep matching both.
- **Students:** `student_type` enum (`regular` / `irregular`); separate Vue routes at `/students/regular` and `/students/irregular`.
- **User names:** `firstname`/`middlename`/`lastname` auto-merge into `name` as "Lastname, Firstname Middlename" on save (`User::boot()`).

## Queues, scheduling, deploy
- Dev defaults: queue/cache/session drivers = `database` (`.env.example`); `composer dev` runs `queue:listen`.
- Schedule lives in `bootstrap/app.php`: `db:backup` daily; `online-peak:sample` and `evaluation-schedules:tick` every minute.
- Deployed on Railway via `nixpacks.toml` (no cron): a one-off `evaluation-schedules:tick` (seeds/catches up the notification snapshot), then `schedule:work` + `queue:work` are backgrounded after `migrate --force`; foreground is `artisan serve`.
- Evaluation-window notices are **intentionally** dispatched `onConnection('sync')` in `EvaluationScheduleService::syncNotifications()` so they don't depend on queue workers — don't "fix" this.
- DB backups go to the local disk; point `LOCAL_DISK_ROOT` at a mounted volume in production.
- App timezone is `Asia/Manila` — evaluation window date logic depends on it.

## Style
- PHP: Pint (Laravel default preset). JS/Vue: `.prettierrc` — 2 spaces, double quotes, trailing commas, width 120. `.editorconfig`: 2-space indent, LF endings.
