# Unit-level (serial) execution — code review summary

**Branch:** `feat/unit-level-execution` (off `develop`, currently 8 commits ahead of `origin/develop`)
**Issue:** [#290 — Optional unit-level (serial) execution alongside batch-level progression](https://github.com/Mes-Open/OpenMes/issues/290)
**Status:** manually tested end-to-end in a local instance.

## What this adds

Today, every piece in a `Batch` progresses through steps together — one status per
step for the whole batch quantity. This branch adds an **opt-in per-template mode**
where individual pieces progress through steps independently instead — piece 2 can be
on step 2 while piece 3 is still on step 1 — with an operator-facing way to register a
piece and (optionally) give it a serial number as it goes.

**Batch-mode behavior is untouched.** `BatchService`, `BatchStep`, and the existing
operator flow are not modified anywhere in this branch. Every new table, model, and UI
path only activates for a `ProcessTemplate` explicitly switched to Unit mode; the
default for every existing and new template is Batch mode.

## Commits (oldest → newest)

1. `7be60f0d` **fix:** force IPv4 DNS resolution for npm install in backend Dockerfile — unrelated environment fix, needed to get `npm ci` building in a sandbox with no IPv6 route. Small, isolated, one line.
2. `a018bc67` **chore:** ignore local confidential trial notes — adds `project-confidential/` to `.gitignore`. No code change.
3. `3bc99457` **feat:** unit-level execution, Phase 1 — data model + service layer (see below).
4. `02f67268` **feat:** unit-level execution, Phase 2 — operator UI (see below).
5. `e8d513fe` **feat:** admin UI for unit-level execution config — Serial Sequences admin page + Execution Mode toggle (see below).
6. `1a9defe7` **fix:** allow a Unit-mode piece to start work before it has a serial (see below).
7. `e9435b5b` **fix:** register `serial_sequences` in `CollectionBroadcaster` (see below).
8. `75dd3eb0` **feat:** surface work instructions in the Unit-mode operator UI (see below).

## Phase 1 — data model & service layer (`3bc99457`)

- `process_templates.execution_mode` (`'batch'` default | `'unit'`), frozen into
  `WorkOrder.process_snapshot` at batch-creation time (`ProcessTemplate::toSnapshot()`)
  so a batch stays in whichever mode it was released under even if the template
  changes later.
- New `unit_steps` table/model — a per-(serial unit, step) progression gate,
  deliberately **parallel to `BatchStep`, not built on it**: `BatchStep` stays the
  batch-wide record that material consumption, quality checks, and typed outputs book
  against, unchanged for every batch in both modes. `UnitStep` only decides what one
  physical piece may do next.
- New `serial_sequences` table/model + `SerialNumberService` — serial-number
  generation parallel to the existing `LotSequence`/`LotService`, reusing the existing
  `LotPatternFormatter` as-is (it has no lot-specific behavior).
- `UnitProgressionService` — register a unit, start/complete its steps with the same
  sequential-interlock shape `BatchService` uses but scoped per unit; `SerialUnit`
  auto-completes once all its steps are done; `Batch.status` only reaches `DONE` once
  every registered unit on the batch has completed every step.
- API surface (`POST /api/v1/unit-steps/register|{id}/start|{id}/complete`) for
  testing ahead of the operator UI.
- `unit_steps` and `serial_sequences` registered in `SoftDeleteRegistry` (soft-delete
  pattern per project convention).

**Files:** migrations `2026_09_09_100000/1/2`, `app/Models/{UnitStep,SerialSequence}.php`,
`app/Models/ProcessTemplate.php` (+execution_mode), `app/Services/Unit/UnitProgressionService.php`,
`app/Services/Serial/SerialNumberService.php`, `app/Http/Controllers/Api/V1/UnitStepController.php`,
`app/Http/Requests/Api/V1/{Register,Start,Complete}UnitStepRequest.php`,
`database/factories/SerialSequenceFactory.php`, `app/Support/SoftDeleteRegistry.php`, `routes/api.php`.

## Phase 2 — operator UI (`02f67268`)

- `App\Http\Controllers\Web\Operator\UnitStepController` (register/start/complete),
  mirroring `BatchController`'s session/line-selection guard and
  `back()->with('success'|'error', ...)` pattern exactly.
- `WorkOrderController::show()` eager-loads `batches.serialUnits.unitSteps`.
- `WorkOrderDetail.jsx`: `BatchCard` branches on `execution_mode` — Batch-mode batches
  render exactly as before. New `UnitStepList` component for Unit-mode batches: a
  register-unit form and per-unit rows with a step-progress dot strip and a
  Start/Complete button for whichever step that piece is on.

**Files:** `app/Http/Controllers/Web/Operator/UnitStepController.php`,
`app/Http/Requests/Operator/{Register,Start,Complete}UnitStepRequest.php`,
`resources/js/Pages/operator/WorkOrderDetail.jsx`, `routes/web.php`.

## Admin UI (`e8d513fe`)

- **Serial Sequences** admin page (`Admin → Production → Serial Sequences`) —
  `SerialSequenceController` + Create/Edit/Index React pages, mirroring the existing
  LOT Sequences admin page: list + drawer editing, pattern mode with live preview and
  token palette, or legacy prefix/suffix mode. Registered in `ShapeRegistry` (live
  sync) and `TabRegistry` (shares the `production` tab's access rules with LOT
  Sequences).
- **Execution Mode** toggle (Batch/Unit) on the Process Template Create/Edit forms —
  the actual switch the feature needed a home for. Edit carries a note that changing
  it only affects work orders created afterward.

**Files:** `app/Http/Controllers/Web/Admin/SerialSequenceController.php`,
`app/Http/Requests/SerialSequenceRequest.php`,
`resources/js/Pages/admin/serial-sequences/*.jsx`,
`resources/js/Pages/admin/process-templates/{Create,Edit}.jsx`,
`app/Http/Controllers/Web/Admin/ProcessTemplateManagementController.php`,
`app/Sync/ShapeRegistry.php`, `app/Support/TabRegistry.php`, `resources/js/layouts/adminNav.js`.

## Fix: serialize later (`1a9defe7`)

Found during manual testing: a piece had to be serialized *before* it could start
step 1 — no way to start anonymous work and identify the piece later, even though on
a real line a serial is sometimes only known a few steps in.

- `serial_units.serial_no` is now nullable (new migration). `NULL` means "not
  serialized yet"; the existing `(serial_no, tenant_id)` unique index needs no change
  since Postgres treats every `NULL` as distinct.
- `registerUnit()` takes a new `$autoGenerate` flag and has three modes: explicit
  serial, auto-generate, or (both omitted) register unserialized.
- New `assignSerial()` — attaches a serial to an unserialized piece at any point in
  its pipeline (nothing about step progression depends on `serial_no`).
- New endpoints: `POST /api/v1/serial-units/{unit}/assign-serial` and
  `POST /operator/unit/{unit}/assign-serial`.
- UI: an unserialized row shows `Unit #<id> (no serial)`, clickable to open an inline
  assign-serial mini-form.

## Fix: Serial Sequences live-sync (`e9435b5b`)

Found during manual testing: switching a Serial Sequence to Pattern mode and saving
looked like it "didn't stick" — reopening the form showed Simple mode again.

**Root cause:** `serial_sequences` was registered in `ShapeRegistry` (the read path —
what the initial page load fetches) but never added to `CollectionBroadcaster::map()`
(the write/broadcast path — what fires a live update after a save). The DB was never
wrong; the browser's live-synced table row just never got the update pushed to it, so
it kept showing stale pre-save data until a full reload. One-line fix. Confirmed by
reverting it and watching the new regression test fail, then restoring it and
watching it pass.

## Fix: work instructions in Unit mode (`75dd3eb0`)

Found during manual testing: no work instructions, photos, checklists, or typed
outputs appeared anywhere in the Unit-mode operator UI — an oversight in how thin the
Phase 2 first cut was, not a deliberate decision.

- New collapsible "Work Instructions" section in `UnitStepList`, reusing the exact
  same `StepInstructions`/`StepChecklist`/`StepOutputs`/`StepDocuments` components and
  endpoints Batch mode already uses.
- Kept **batch-scoped** (shared by every piece), matching how material consumption
  already works in Unit mode — deliberately does not gate any individual unit's
  Start/Complete button.

## Testing

- New automated tests added across this branch: **~95** (service-level, API feature,
  web feature, admin controller). All new test files listed in the diff stat below.
- Full backend suite as of the last run on this branch: **2767 passed, 0 failed**
  (`php artisan test`).
- Frontend: `npm run build` succeeds cleanly; `npm test` (Vitest): 97 passed.
- Manually tested end-to-end in a local Docker instance: created a Unit-mode
  ProcessTemplate, a Work Order, multiple batches, registered and drove several units
  through steps independently (including two units on different steps
  simultaneously), verified batch/work-order completion rollup, verified the Serial
  Sequences admin page and Execution Mode toggle, verified the serialize-later fix and
  the work-instructions fix.

## Files changed

```
 49 files changed, 3444 insertions(+), 9 deletions(-)
```

New: 3 migrations, 3 models (`UnitStep`, `SerialSequence`, +`ProcessTemplate` field),
2 services (`UnitProgressionService`, `SerialNumberService`), 2 controllers (API +
Web `UnitStepController`), 1 admin controller (`SerialSequenceController`), 8 Form
Requests, 4 admin React pages (`serial-sequences/*`), 1 factory, 4 test files.
Modified: `WorkOrderDetail.jsx` (+371 lines — `UnitStepList`, `UnitStepReferenceCard`,
`UnserializedUnitBadge`), `ProcessTemplate{Create,Edit}.jsx`, routes, `ShapeRegistry`,
`TabRegistry`, `SoftDeleteRegistry`, `CollectionBroadcaster`, `lang/{en,pl}.json`
(+41 keys each, parity verified).

## Open items — not done in this branch, worth deciding on before/after merge

1. **Serial-number entry UI needs real design work.** The current Register
   Unit / Assign Serial forms are functional but minimal — a plain text field, an
   "Auto-generate" checkbox, and (for assignment) a small inline mini-form on the
   unit row. This was built to prove the mechanism, not as a finished shop-floor UX.
   Worth a proper design pass: clearer affordances for manual vs. auto-generate vs.
   serialize-later, better visual treatment for an unserialized piece in the list,
   and probably a dedicated modal instead of the current inline form for assign-serial.

2. **No API path yet for a serial number to originate from an external system.**
   Today a serial is either typed by the operator, auto-generated from a
   `SerialSequence`, or added later via `assignSerial()` — all *within* OpenMES. If
   serials are actually assigned by another system (an ERP, a label-printing/scanning
   station, a separate serialization service) and OpenMES needs to *accept* one at
   registration time rather than mint its own, that's not designed for yet. The
   building blocks exist (`registerUnit()`/`assignSerial()` already accept an explicit
   `serial_no` string, and there's a pre-existing generic `POST /api/v1/serial-units`
   endpoint unrelated to this feature), but there's no dedicated, authenticated,
   idempotent integration endpoint purpose-built for "an external system pushes a
   serial for a specific batch/work order" — things like how the external system
   identifies *which* piece/batch it's assigning to, what happens on a duplicate
   push, and what auth model (API token scope) it uses are all undecided. Needs a
   proper design pass, likely its own small scope-and-plan cycle before implementation.

3. **Traceability integration not fully verified.** `SerialTraceService`/
   `TraceabilityService` weren't modified by this branch. A unit's `unit_steps`
   history is intact regardless of when it's serialized (nothing is deleted), but I
   have not click-tested that Traceability's serial search surfaces a unit's
   pre-serialization steps once a serial is assigned after the fact. Worth a manual
   check before relying on it for a real recall/audit scenario.

4. Minor, pre-existing, unrelated to this branch: the production JS bundle already
   exceeds Vite's 500 kB chunk-size warning threshold before any of these changes;
   this branch adds to that total but didn't introduce the warning.
