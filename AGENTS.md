# Aventura — SaaS Restaurant Management System

## Stack

- **Backend**: Laravel 13, PHP 8.3+
- **Frontend**: Vue 3 SPA via Inertia, Tailwind CSS v4, TypeScript
- **Build**: Vite 8, vue-tsc (type-check)
- **DB**: SQLite (dev/testing), MySQL (production), Redis (cache/queue)
- **Python microservices**: `services/analytics_service/`, `services/chatbot_service/`, `services/email_service/`

## First-time setup

```bash
composer install
npm install          # .npmrc has ignore-scripts=true, so no postinstall hooks
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run dev          # vite dev server
php artisan serve    # or: composer run dev (runs serve + queue:listen + vite concurrently)
```

## Key commands

| Command | What it does |
|---|---|
| `composer run dev` | Runs `php artisan serve` + `queue:listen --tries=1` + `npm run dev` concurrently |
| `composer run lint` | `pint --parallel` (auto-fix PHP style) |
| `composer run lint:check` | `pint --parallel --test` (check only) |
| `composer run test` | `php artisan config:clear` → `lint:check` → `php artisan test` |
| `npm run format` | Prettier on `resources/` |
| `npm run format:check` | Prettier check-only on `resources/` |
| `npm run lint` | ESLint auto-fix on `.` |
| `npm run lint:check` | ESLint check on `.` |
| `npm run types:check` | `vue-tsc --noEmit` (TypeScript type-check) |
| `npm run build` | `vite build` (uses PowerShell to prepend PHP path on Windows) |
| `./vendor/bin/pest` | Run tests (CI uses pest, not phpunit) |

**Full CI pipeline**: `npm run lint:check` → `npm run format:check` → `npm run types:check` → `composer run test` (in `ci:check` script).

## Multi-tenant architecture

- Shared DB / Shared Schema — isolated by `restaurant_id` column.
- `app/Models/Concerns/BelongsToRestaurant.php` applies a global scope on all queries + auto-fills `restaurant_id` on create.
- `app/Support/Tenant/TenantContext.php` is a singleton that holds the current `restaurant_id`, set by `SetTenantContext` middleware.
- Never bypass the global scope. Use `->withoutGlobalScope('restaurant')` explicitly when needed (e.g. super-admin).
- Soft deletes on: `restaurants`, `products`, `employees`, `work_shifts`, and most business entities.

## Route structure

| File | Purpose | Middleware |
|---|---|---|
| `routes/web.php` | Main app routes (public + tenant) | auth, verified, tenant.subscription |
| `routes/settings.php` | Profile, restaurant, security settings | auth, verified |
| `routes/super-admin.php` | SaaS admin panel | auth, verified, role:super_admin, 2FA |
| `routes/channels.php` | WebSocket broadcasting channels | — |
| `routes/console.php` | Artisan console commands | — |

## Frontend conventions

- Pages auto-resolve from `resources/js/pages/**/*.vue` via Inertia.
- **Layouts**: `AppLayout` (default), `AuthLayout`, `BareLayout`, `GuestLayout`, `SettingsLayout`, `AppTopbarLayout`.
- Page layout resolved in `app.ts` by page name prefix convention (settings/, auth/, etc.).
- shadcn-vue components live in `@/components/ui/*` (New York v4 style).
- Tailwind utility: `cn()` from `@/lib/utils` (wraps `clsx` + `tailwind-merge`).
- Icons: `lucide-vue-next`.
- State management: Pinia.
- Realtime: `laravel-echo` + Pusher (or Reverb).
- Path alias: `@/` → `resources/js/`.
- Generated files (gitignored): `resources/js/routes/`, `resources/js/wayfinder/`, `resources/js/actions/`.

## Code style

- **PHP**: Laravel Pint preset (`pint.json`), 4-space indent.
- **JS/TS/Vue**: ESLint + Prettier. Rules:
  - `consistent-type-imports` with `prefer-top-level` specifier style (error).
  - `import/order`: builtin → external → internal → parent → sibling → index, asc.
  - `brace-style`: 1tbs, no single-line.
  - Padding around control statements (if, for, while, try, return, etc.).
  - `curly`: all (always require braces).
- Files excluded from ESLint: `vendor/`, `node_modules/`, `public/`, `bootstrap/ssr/`, `vite.config.ts`, `actions/`, `components/ui/`, `routes/`, `wayfinder/`.
- **Prettier**: semi, singleQuote, tabWidth 4 (2 for YAML), `prettier-plugin-tailwindcss` (recognizes `clsx`, `cn`, `cva`).

## Testing

- Tests use Pest (run with `./vendor/bin/pest`).
- Test DB: SQLite `:memory:` (in-memory, migrations run per test).
- `Tests\TestCase` provides `skipUnlessFortifyHas()` helper.
- CI matrix: PHP 8.3, 8.4, 8.5.

## Important gotchas

- `.npmrc` has `ignore-scripts=true` — npm postinstall scripts don't run.
- `build:ssr` runs Vite build twice (client + SSR).
- ESLint ignores `vite.config.ts` — lint changes there won't be checked.
- `composer run dev` uses `npx concurrently` and disables process timeout.
- Python microservices require separate `pip install -r requirements.txt` per service dir, started independently via uvicorn.
- The `.env` file currently uses SQLite — switch to MySQL + Redis for production.
- `DB::prohibitDestructiveCommands()` is enabled in production (blocks `migrate:fresh`, `db:wipe`, etc.).
- Password rules: min 12 chars + mixed case + symbols + uncompromised in production.
- `tenant.subscription` middleware checks `Restaurant::currentAccessStatus()` — suspended/expired tenants get blocked.

## Python microservices (separate processes)

| Service | Port | Dir |
|---|---|---|
| Email | 8001 | `services/email_service/` |
| Chatbot AI | 8002 | `services/chatbot_service/` |
| Analytics | — | `services/analytics_service/` |

Each needs its own `.env` and `requirements.txt`, run with `uvicorn main:app --port N`.
