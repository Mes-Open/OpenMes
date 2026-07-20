# OpenMES — AI Assistant Guidelines

Project conventions for AI coding assistants (Claude Code, Copilot, Cursor, …).
Human-oriented docs live in [`docs/`](docs/index.md); contributor workflow in [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md).

## Stack

- **Backend:** Laravel 12 (`backend/`), PostgreSQL 17, served by Octane/RoadRunner in Docker
- **Frontend:** React 19 + Inertia.js (`backend/resources/js/`), Tailwind, built by Vite
- **Live sync:** Laravel Reverb (Pusher-protocol WebSockets) + TanStack DB — read-path sync from Laravel to the browser (see "Synced collections" below); writes always go through Laravel controllers
- **Infra:** `docker-compose.yml` (postgres, backend, reverb, caddy + optional connectivity daemons)

## Commands

```bash
# Backend (run inside backend/)
composer install            # sync vendor after pulling
php artisan test            # full test suite — must pass before PR
./vendor/bin/pint <files>   # code style (PSR), run on changed PHP files
composer audit              # security advisories

# Frontend (run inside backend/ — package.json lives there)
npm ci && npm run build     # production build (Vite manifest needed by Inertia feature tests)
npm run watch               # rebuild on save during development

# Full stack
docker compose up -d                                          # production-like
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d   # dev overlay: vite watch + bind mounts
```

## Hard rules

1. **English-first** — all code, Blade/JSX text, validation messages, seeders, comments are English. Other languages exist only as translations in `backend/lang/*.json`.
2. **i18n parity** — `lang/en.json` and `lang/pl.json` must contain the same key set. Adding a UI string means adding the key to **both** files (English value = key itself in `en.json`).
3. **Form Requests for validation** — never validate inline in controllers. Frontend validation is UX only; the backend rule set is authoritative.
4. **Never rename migration filenames after merge** — the filename is the migration's identity; renaming breaks every existing database on upgrade (duplicate-table crash in the entrypoint migrate).
5. **Tests are mandatory** for new endpoints/business logic: happy path, validation 422, authorization (guest + wrong role), domain edge cases. Use factories, `RefreshDatabase`, and follow `tests/Feature` / `tests/Unit` conventions.
6. **No raw SQL with user input** — Eloquent/Query Builder only.
7. **No new dependencies without justification** — prefer built-in Laravel/React capabilities. Any new package: latest stable, maintained, not deprecated/archived.
8. **License is AGPL-3.0** — keep headers/notices consistent with it.
9. **Soft deletes on every new CRUD entity** — any new model a user can delete must follow the soft-delete pattern, never hard-delete:
   - model uses `App\Models\Concerns\SoftDeletesWithAudit` (SoftDeletes + `deleted_by_id` audit + cascade),
   - register it in `App\Support\SoftDeleteRegistry::MODELS` (powers the Admin → Trash page, sync filtering and unique/exists validation),
   - migration adds `$table->softDeletes()` + nullable `deleted_by_id` FK to `users` (`nullOnDelete`), and any **unique index must be partial** (`WHERE deleted_at IS NULL`) or the value can't be re-used after deletion,
   - if the table has `cascadeOnDelete` children, mirror them in the model's `softDeleteCascades()` (DB cascades don't fire on soft delete),
   - tests assert deletes with `assertSoftDeleted`, not `assertDatabaseMissing`.

## Synced collections (live data)

- Collections are declared server-side in `backend/app/Sync/ShapeRegistry.php` (table + column allowlist + optional WHERE). The browser never queries Postgres directly.
- Flow: client loads the initial snapshot from `GET /api/collections/{name}` (`CollectionController`, Laravel authorizes and applies the tenant scope) → live deltas then arrive as `CollectionChanged` broadcasts on a private channel (`backend/routes/channels.php`) → Laravel Reverb (Pusher protocol) → `laravel-echo` / `pusher-js`.
- One app-wide multiplexed WebSocket carries every collection channel (`backend/resources/js/lib/echo.js`), so there's no per-connection budget. Client state is TanStack DB (`lib/realtimeCollection.js`).
- **Adding a column to a synced table?** Add it to the collection's `columns` list too, or the UI will never see it.
- Frontend hooks (`lib/useSyncedShape.js`): `useLiveShape(name)` and `useSyncedShape(name)` — identical, both live, both return `{ data, isLoading }`; the two names only signal call-site intent. Hot app-wide collections are shared via `LiveShapesProvider` — don't subscribe to them again per page.
- New pages should use Sanctum SPA cookie auth (session), not tokens.

## Frontend conventions

- New pages are React/Inertia (`backend/resources/js/Pages/...`); legacy Blade+Livewire pages still exist and are being ported — don't add new Blade pages.
- Config-driven CRUD: `ResourceTable` (list, fed by a synced collection) + `ResourceForm` (create/edit via Inertia `useForm`). Custom forms only when those don't fit.
- React escaping is the XSS defense — `dangerouslySetInnerHTML` is effectively banned.

## Workflow

- Branch from `develop`; PRs target `develop`. `main` is release-only (merged from develop, tagged `vX.Y.Z`).
- **Pre-commit hook runs the CI test gate** (`.githooks/pre-commit`, enabled by `composer install` via `core.hooksPath`): the same suite as `.github/workflows/tests.yml`, with `--stop-on-failure`. Skip once with `git commit --no-verify` (all hooks) or `SKIP_TESTS=1 git commit` (tests only) — but CI will still gate the PR.
- Update `CHANGELOG.md` under the unreleased/current version heading for user-visible changes.
- Versioning: PATCH = fixes/UI, MINOR = new feature, MAJOR = breaking change.
- Commits: imperative, conventional-style prefixes (`feat:`, `fix:`, `i18n:`, `chore:`).

## Gotchas

- `php artisan test` needs a Vite manifest for Inertia feature tests — run `npm run build` first if you see `ViteException`.
- A number of legacy `assertSee` tests fail by design after the React migration (rows live-sync to the browser, they aren't server-rendered HTML) — compare against the develop baseline before assuming you broke something.
- `TrustProxies(at: '*')` in `bootstrap/app.php` is required for HTTPS behind Caddy — don't remove it.
- Modules under `modules/` are deprecated in favor of core (`.gitignore` documents this) — don't add new module code there.
