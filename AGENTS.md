# AGENTS.md — NEUST Faculty Evaluation System

## Stack
- **Backend:** Laravel 12, PHP 8.2+, Spatie Permission, Sanctum, SQLite (dev)
- **Frontend:** Vue 3 SPA + Vue Router, Vite, Tailwind 4, Sass
- **AI:** Gemini API via `App\Services\AiService` (`gemini-2.5-flash`, key in `GEMINI_API_KEY`)
- **Auth:** email/password + Google OAuth (Laravel Socialite)

## Commands
```bash
composer setup        # full first-time setup (install, .env, key, migrate, npm install, build)
composer dev          # runs server + queue listener + logs + Vite concurrently
composer test         # config:clear → php artisan test
npm run dev           # Vite dev server
npm run build         # Vite production build
```

## Architecture
- **SPA:** Laravel serves `resources/views/app.blade.php` for all non-`/api` routes (`routes/web.php`). Vue Router with `createWebHistory` handles client-side routing.
- **Base path:** Vue router checks `window.location.pathname`; may be `/evaluation_system/public/` if deployed under subdirectory.
- **API:** All routes in `routes/api.php`. Authenticated via `auth:sanctum`.
- **Permissions:** Route-level via `permission:xyz` middleware (Spatie). Frontend guards via `meta.permission` in Vue Router. Admins bypass permission checks client-side.
- **UUIDs:** All models use `HasUuids` trait.
- **Evaluatee types:** Faculty and staff (plus registrar/guidance/library/it planned). Evaluations use `evaluatee_type` + `evaluatee_id` polymorphic pattern (with legacy `faculty_id` fallback — see `Evaluation::scopeForFacultyMember` and `getEvaluatee()`).
- **Students:** Split into "regular" / "irregular" via `student_type` column. Separate Vue routes at `/students/regular` and `/students/irregular`. Enrollments only apply to irregular students.

## Testing
- `composer test` (runs `php artisan test` after `config:clear`)
- Tests use SQLite `:memory:` — no external DB needed
- Test locations: `tests/Unit/`, `tests/Feature/`

## Notable conventions
- Auto-merge `firstname`/`middlename`/`lastname` into `name` on User save (see `User::boot()`)
- Queue driver is `database` by default; `composer dev` runs `queue:listen`
- AI analysis uses `responseMimeType: application/json` to get structured JSON from Gemini
- `.prettierrc`: tabWidth 2, singleQuote false, trailingComma all, printWidth 120
- `.editorconfig`: indent 2 spaces, LF endings
