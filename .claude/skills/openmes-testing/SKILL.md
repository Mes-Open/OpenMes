---
name: openmes-testing
description: Walking OpenMES through a real browser with Playwright to prove a flow works end to end — before a release, a client presentation, or when a PR touches production, operator or admin screens. Covers the preflight the stack needs, the credential rule, testing every role that can reach a screen, the green path plus the scenarios that try to break it, when a change needs a migration, the rule that no screen may be blank without saying why, and the evidence a run has to leave behind.
---

# Testing OpenMES end to end

This skill is about the question "is this actually working?", answered by driving the
real UI in a browser and leaving evidence behind. It is not about `php artisan test` —
the PHP suite is the pre-commit gate (see CLAUDE.md) and runs on its own.

The suite lives at the **repository root**, not in `backend/`:

- `playwright.config.ts` — root config
- `tests/e2e/*.spec.ts` — 30 specs, one flow each
- `@playwright/test` is a devDependency of the **root** `package.json`

Run from the repo root with `npx playwright test`. Running it from `backend/` picks up
the frontend package and finds no tests.

## Preflight — do this before blaming the app

Four things break a run for reasons that have nothing to do with the code under test.
Check them in this order:

```bash
docker compose ps                                        # all 8 services Up, postgres healthy
docker compose exec -T backend php artisan migrate:status | grep -c Pending   # must be 0
cd backend && npm run build                              # Vite manifest — else ViteException
docker compose exec -T backend sh -c 'php artisan route:clear && php artisan route:cache && php artisan octane:reload'
```

- **Pending migrations** are the most common cause of a "broken" page: the DB is behind
  the checked-out branch and the page queries a column or table that does not exist yet.
  The backend mounts `app/`, `routes/`, `resources/`, `database/` from the working tree,
  so the container always runs current code against a possibly older schema.
- **New routes 404 or 405** until `route:clear` + `octane:reload`. Octane holds the
  cached route table in long-lived workers.
- `APP_ENV=production` locally, so every artisan command that writes needs `--force`.

## Credentials — the one hard rule

Every spec reads the admin account from the environment:

```ts
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
```

That `Admin1234!` fallback is a **stock value from a fresh install and will not match a
configured instance.** Export the real credentials for the session instead:

```bash
export ADMIN_USERNAME=admin ADMIN_PASSWORD='<the password for this instance>'
```

**Never reset or rewrite a user's password to make a test pass.** Rewriting the admin
hash to a known string silently breaks the instance for whoever owns it, and the next
person to log in cannot tell why. If you cannot authenticate, stop and ask for the
credential — do not "fix" the account. For API-level checks, issue a token instead:

```bash
docker compose exec -T backend php artisan mcp:token --help     # long-lived API token for a given user
```

## Environment variables the specs honour

| Variable | Default | What it does |
|---|---|---|
| `E2E_BASE_URL` | `http://localhost:8080` | Target instance. Caddy also serves `https://localhost` (`ignoreHTTPSErrors` is on). |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | `admin` / `Admin1234!` | Admin login. See above. |
| `OPENMES_NAME_PREFIX` | `openmmes` | Container-name prefix for `docker exec …-backend` lookups. |
| `OPENMES_BROWSER_STORAGE_STATE` | — | Path to a saved session; the spec skips the login form. |
| `E2E_NO_REALTIME` | — | Set to `1` when Reverb is unreachable: the spec reloads instead of waiting for a broadcast. |
| `E2E_BACKEND_CONTAINER` | — | Explicit backend container name (traceability flow). |

The config pins `workers: 1`, `fullyParallel: false`, `retries: 0` — these specs share
one database and one shop floor, so they must not overlap. Do not raise the worker
count to make a run faster.

## Writing a new spec — the house conventions

Read `tests/e2e/full-production-run.spec.ts` first; it is the reference implementation
and its header comment documents the roles a full run needs.

1. **Unique names per run.** `const TS = Date.now().toString().slice(-6);` then
   `` `Run Line ${TS}` ``. Unique indexes on soft-deleted entities are partial
   (`WHERE deleted_at IS NULL`), so a name *can* be re-used — which means a fixed name
   leaves several same-named rows behind across runs and a selector matching on text
   starts picking the wrong one. That failure reads like an app bug and is not.
2. **Serial, with a step per phase.** `test.describe.configure({ mode: 'serial' })` and
   one `test.step` per production stage, so a failure names the stage that blocks rather
   than "the flow broke".
3. **Every write goes through the UI.** The point is to prove the forms work. Reach into
   the database only to *read* an id the UI does not put in a URL — the `dbValue()`
   helper in `full-production-run.spec.ts` does this via
   `docker exec … php artisan tinker`. Never seed state through tinker to skip a screen
   you did not want to click; that is the hole these tests exist to close.
4. **Raise the timeout for real flows.** The default is 30 s; a full production run sets
   `test.setTimeout(240_000)`.
5. **Account for roles.** The operator console lists only lines the signed-in user is
   **assigned** to, and only users with the Operator role can be assigned — so the admin
   account alone cannot drive the operator screens. A full run has to create an operator
   user and assign it to the line. This is a real finding already baked into the suite,
   not a quirk of the test.
6. **Expect the wall.** Where a flow cannot continue (a missing number, a disabled
   button, no visible error), that is a finding — record it in the report rather than
   working around it in the spec.

## Every account type that can reach the screen

A flow proven as admin is not proven. The admin sees every field, every tab and every
button, so an admin-only pass hides exactly the failures that show up at a client: a
missing permission rendering a broken page, a button that posts and 403s, a tab that
opens empty.

The roles are seeded by `RolesAndPermissionsSeeder`: **Admin**, **Supervisor**,
**Operator**.

For each screen in the flow, walk it as **every role that is supposed to have access**,
and then as **one role that is not**:

| Case | What must happen |
|---|---|
| Role with access | The screen does its job. Not "renders" — the writes work. |
| Role with partial access | Controls it may not use are absent or disabled **with a reason**, never present-and-then-403. |
| Role without access | Clean redirect or 403 page. Never a 500, never a half-rendered layout, never an empty table that looks like "no data". |
| Guest | Redirect to `/login`, and after logging in, land back on the intended page. |

Create the extra accounts through the UI as part of the run, the way
`full-production-run.spec.ts` creates its operator — not with tinker. Account creation
is itself one of the flows nobody trusts.

Two constraints that bite here:

- The operator console lists only lines the user is **assigned** to, and only
  Operator-role users can be assigned. An admin cannot stand in for an operator.
- In PHP feature tests the test database already has the roles seeded, so use
  `Role::findOrCreate(...)` rather than `Role::create(...)`, which throws on the second
  run.

## Scenarios per flow: the green path, then the attempts to break it

One happy path is a demo, not a test. Each owned flow needs **at least five scenarios**:
one green path and four that try to trick the system. This is an MES — it holds the
record of what was physically produced. Either it refuses bad input with a message that
says what to do, or it walks the user through. There is no third acceptable outcome.

The repertoire, in rough order of how often it finds something:

1. **Empty and whitespace.** Submit required fields blank, then with only spaces.
2. **Quantities.** Zero, negative, fractional where only integers make sense, more than
   the work order quantity, more than the material on hand. Over-reporting production is
   the single most damaging thing an operator can do by accident.
3. **Duplicates.** The same work order number, EAN, lot number or product code twice —
   including re-using one that belongs to a **soft-deleted** row, which partial unique
   indexes permit.
4. **Hostile strings.** Emoji, 500-character names, right-to-left text, `'; DROP TABLE`,
   `<script>alert(1)</script>`. React escaping is the XSS defence, so the correct result
   is the payload rendered as visible text. If it executes or the page breaks, that is a
   P1.
5. **Out-of-order operations.** Complete a step that was never started. Close a batch
   with no output. Ship a pallet whose quality check never passed. Issue material that is
   on hold or quarantined. Each of these has a guard somewhere; the test is whether the
   guard *speaks*.
6. **Stale screen.** Open the same record in two tabs, delete or advance it in one, then
   act in the other. Live collections make this ordinary, not exotic.
7. **Double submit.** Double-click the save button; press browser Back after a submit and
   submit again. Nothing may be written twice.
8. **Refresh mid-wizard.** Reload halfway through a multi-step form. Either the state
   survives or the user is told it did not.
9. **Direct URL access.** Paste the id of a record belonging to another line or tenant
   into the URL. It must not render.
10. **Boundaries.** A step that spans midnight or a shift change; a date in the past; a
    planned end before its start.

Verdict for every one of them:

- **Good:** refused with a specific message naming the field and the fix, or the UI
  prevented the state from being reachable at all.
- **Finding:** a 500, a stack trace, a silent no-op, a success message with nothing
  written, a generic "Something went wrong", or a validation error that does not say
  which field.

Validation lives in Form Requests (CLAUDE.md hard rule 3) — the frontend check is UX
only. So also submit at least one scenario **past** the frontend: the backend must refuse
it on its own.

## Schema changes, breaking changes and migrations

Testing a flow often turns up a missing column. The rules on fixing it:

- **Every schema change is a migration file** in `backend/database/migrations/`. Never
  edit or rename a migration that is already merged — the filename is its identity, and
  renaming it breaks every existing database on upgrade (CLAUDE.md hard rule 4). Need a
  correction? Add a new migration.
- **Prove the upgrade, not the install.** Run the migration against a database that
  already holds data, then walk the flow again. A migration that only works on a fresh
  install is the breaking change people discover at a client site. `migrate:status` must
  report 0 pending afterwards.
- **New column on a synced table?** Add it to the collection's `columns` allowlist in
  `app/Sync/ShapeRegistry.php` too, or the UI will never see it — and the symptom is a
  blank field, not an error.
- **New deletable entity?** Soft deletes are mandatory: `SoftDeletesWithAudit`, an entry
  in `SoftDeleteRegistry::MODELS`, `softDeletes()` + nullable `deleted_by_id` in the
  migration, and any unique index made **partial** (`WHERE deleted_at IS NULL`) so the
  value can be re-used after deletion. Then test deleting *and* re-creating with the same
  name.
- **Flag the compatibility break explicitly.** If a change alters an API response shape,
  a route, a permission name or an exported file format, say so in the report and in
  `CHANGELOG.md`. Breaking change = MAJOR version bump.

## Nothing may be invisible without a reason

An empty list is the worst possible answer, because it is indistinguishable from a
broken query. Whenever this skill's walkthrough hits a screen that shows nothing, that is
a finding unless the screen explains itself.

The rule: **say why it is empty, and what to do about it.**

- `ResourceTable` takes an `emptyText` prop (it defaults to `'Nothing here yet.'`, which
  is only acceptable for a genuinely top-level list). Any list whose contents depend on a
  parent record, a filter or a configuration step must override it with the reason —
  "No BOM items on this template yet. Add the first component.", "No results for this
  filter.", "This line has no workstations — add one before planning."
- **When items exist but are not attached, show the unattached items**, not an empty
  list. A BOM page with nothing linked should list the materials available to link, with
  the control to link them, rather than an empty table that reads as "the data is gone".
  Same for a work order with no batches, a line with no operators, a template with no
  steps.
- A **hidden or disabled control needs a visible note why** — "needs an EAN before it can
  be printed", "assign an operator to enable". A greyed-out button with no explanation is
  the wall people reported in the stability meeting, and the operator cannot tell it
  apart from a bug.
- Distinguish the three empty states, because they need three different messages: nothing
  exists yet / nothing matches the filter / nothing is attached to this parent.
- Any new string goes into **both** `backend/lang/en.json` and `pl.json` with the key set
  kept identical (CLAUDE.md hard rule 2; use the `i18n-lang-files` skill).

## Evidence a run must leave behind

A green terminal convinces nobody a week later. Playwright is already configured to
record it: `video: 'on'`, `trace: 'retain-on-failure'`,
`screenshot: 'only-on-failure'` — artefacts land in `test-results/`.

**The video recording is silent by design, which is exactly the artefact the team agreed
an owned area needs** — no separate screen capture is required, just keep the file.

For videos meant to be watched rather than debugged, `tests/e2e/recordings/` films each
section the way a person works it — visible cursor, typing at human pace, a caption per
step, a title card — and every section is also an assertion, so re-recording re-tests.
`RECORD=1 npx playwright test tests/e2e/recordings` writes one MP4 per section to
`recordings/` (git-ignored). Add a section there when you take ownership of an area;
`recorder.ts` holds the helpers (`cover`, `caption`, `click`, `type`, `choose` with a
search so other data on the instance stays off screen).

For a reviewed run, copy the artefacts into a dated evidence folder alongside the
existing ones:

```
docs/review-evidence/<topic>-<YYYY-MM-DD>/
```

and write the findings up in the style of `docs/production-readiness-review-2026-09-05.md`
— stable id, severity, one-line title (`R07 — P1: Step execution bypasses production
change holds`). `references/report-template.md` holds the skeleton.

## What is already covered, and what is not

`references/coverage-map.md` maps all 30 specs to the flow each one walks, and lists the
areas with no end-to-end coverage at all. Check it before writing a new spec — several
flows people assume are untested already have one.

## Known traps

- A number of legacy `assertSee` PHP tests fail **by design** after the React migration
  (rows live-sync to the browser instead of being server-rendered). Compare against the
  `develop` baseline before assuming a change broke them.
- A live list that does not update may be a Reverb/WebSocket problem rather than a data
  problem. Confirm with `E2E_NO_REALTIME=1`: if the reload shows the row, the write
  worked and the broadcast is the fault.
- `php artisan test` needs the Vite manifest too — `npm run build` in `backend/` first.
