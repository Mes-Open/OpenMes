# Production flow and machine counter review

## Result

The change uses an explicit per-step quantity ledger and independently tracked machine sources.
The original transfer-flow plan is consistent: intermediate output becomes downstream input,
scrap stays at its originating step, and only the final effective step contributes finished
order output. Raw device totals are not order totals.

The review covered quantity ownership, production/QC stops, routing changes after recording
output, order/step lock order, raw baselines, reset/retry handling, assignment changes, tenant
and role boundaries, reconciliation, migration rollback and operator live updates.

## Findings corrected

- Removed newest-order/newest-batch guessing from explicit machine counting. Channels require explicit
  workstation and step assignment; payload hints cannot redirect production.
- Replaced cache baselines and order-total subtraction with persistent source state, timestamp
  checks and event-ID deduplication. A reset does not produce guessed output.
- Guarded multiple good-count sources on one step and wildcard MQTT topics mixing machines.
- Revalidate source changes, including register type, transforms, topic and transport endpoint.
  A reset acknowledgement cannot bypass configuration validation. Stale decoder/Modbus transport
  snapshots are rejected even after new configuration was acknowledged.
- Retain unapplied/partial readings and reconcile explicitly, with atomic rollback if the target
  cannot accept the entire remaining good quantity. Preserve original attribution.
- Reject legacy direct MQTT completion/status writes for transfer orders so they cannot bypass
  the production workflow's quantity and completion rules.
- Corrected accessible dropdown names and missing Polish labels found during visible E2E.

## Verification

- Focused production/connectivity tests: **171 tests / 589 assertions** passed on each of
  SQLite and PostgreSQL, including gateway,
  MQTT, counter semantics, ledger safety, rollback, authorization and private line sync.
- Frontend: 134 tests across 11 files passed; Vite production build completed.
- Native headed `playwright-iso`: cumulative baseline and duplicate, reset freeze/recovery,
  two-batch assignment, unassigned-reading reconciliation, missing/duplicate pulse IDs,
  old/future timestamps, quantity caps, unknown/reject quality, and mobile layout verified.
- English/Polish key sets match; new English strings equal their keys. Existing historical
  English aliases were preserved.

The broad workspace run covered 2,697 tests while this work was underway. It reported the
previously known failures in untracked material-type/component-planner tests and the test
image's missing `imagewebp()` function, plus three machine-test expectations that were updated
for explicit configuration/timestamps and then passed in the focused suite. The broad suite
was **not fully green**. Local evidence logs retain that result rather than presenting the
focused run as a clean full-suite pass.

## Release conditions

Existing publishers retain legacy whole-batch counting by default. Migrating a channel to
explicit counting requires the assignment and timestamp/event-ID contract described in
[the deployment guide](production-flow-deployment.md). Rehearse migration and peak ingestion
rate on a production-sized copy; database tests establish correctness, not an ingestion
capacity or retention budget. Counter evidence is retained and can grow with polling volume.
Resolve or separately account for the known full-workspace test failures before release.

Use [the browser guide](machine-counter-browser-tests.md) to repeat the functional checks.
No production deployment or push is part of this session.

## Compatibility follow-up

- Preserved pre-existing tag, MQTT absolute and MQTT pulse behaviour until each channel explicitly
  opts in; opening/registering a channel alone does not alter counting.
- Added an audited return to legacy mode, permitted in whole-batch flow.
- Applied transfer compatibility validation to both web and API settings, with runtime guards
  for legacy writes and protection against losing existing order output during a flow switch.
- Verified legacy Modbus/OPC UA readings without event metadata, old MQTT absolute/wildcard
  payloads, independent channel migration, settings validation and rollback authorization.
- Visible native Playwright walkthrough covered legacy mode surviving reload, transfer rejection
  naming the incompatible source, explicit activation and baseline/duplicate behaviour, and
  successful transfer activation after migration. The original development flow setting was restored.
- Final compatibility regression suite: **214 tests, 699 assertions** on both SQLite and
  isolated PostgreSQL 17; **134 frontend tests** passed. Settings persistence assertions now
  decode saved JSON instead of using PostgreSQL's unsupported JSON equality operator.
- PHP formatting, translation key parity and whitespace checks passed. These are focused
  regression results; the previously reported full-workspace failures remain outside this claim.

## PR #300 review follow-up — 2026-09-16

Validated CodeRabbit findings against the implementation before applying them:

- Preserve omitted flow settings and block transfer → whole-batch while routed orders remain open.
- Exclude flow-mode changes from generic imports; validate API workflow modes.
- Handle machine orders without a line and respect the cross-line routing setting on details.
- Preserve counter configuration on rollback even when no readings exist.
- Reject pulse/increment configuration for built-in Modbus polling; event-aware gateways remain supported.
- Reuse the locked MQTT counter and cached ledger flag. Remove unreachable station folding logic while
  retaining the requested station-only default and explicit show-all control.

Added audited good-total corrections for running manual transfer steps. They require Full edit,
the operator who started the step or a Supervisor/Admin, a reason and the expected current total.
They reject stale values, stopped/closed work, machine orders and impossible downstream quantities.
Timed correction windows still apply only to individual shift entries. Scrap edits and reopening
completed work are outside this action.

Visible `playwright-iso` checks used isolated local order `TEST-CORRECTION-PR300` (#69): 4 → 3,
downstream consumption preventing 3 → 1, downstream-first correction, final-step order rollup,
+1, station-only/show-all views, and policy gating. Audit rows retained the operator, reason and
before/after totals. Admin flow reversal was refused with open routed orders. The counter page
loaded with zero native selects and the history table. The original local correction policy
(`none`) and transfer flow were preserved after testing; the test order remains for inspection.

The new local route initially returned 404 because the server retained its old route cache.
Rebuilding the route cache and reloading workers resolved it. The deployment entrypoint already
rebuilds that cache. An initial clean-checkout test started before frontend assets were built;
its missing-manifest failure was an environment setup issue. Final suites use built assets.

A production-copy migration rehearsal remains pending: no production database copy or staging
location was supplied. Do not treat disposable-database tests as measured migration downtime.
The broader phase-3 admin routing/WIP dashboards and searchable large counter-assignment redesign
remain separate follow-ups.

Final source verification (isolated snapshots, unrelated untracked code excluded):

- SQLite: **2,716 tests / 11,249 assertions**, no errors or failures; five existing PHPUnit
  deprecations, one skipped test and one risky empty test.
- Frontend: **134 tests / 11 files**, production build, PHP formatting and translation parity passed.
- Backend test image included GD WebP support. The repository's deployable image still lacks
  that capability; this follow-up does not change its build or resolve existing dependency advisories.
- PostgreSQL 17: **2,716 tests / 11,227 assertions**, one error and eight failures. All nine
  failing test methods match the already reproduced `cca82cab` baseline: invalid timezone
  JSON fixture, four SQLite-preset installer assumptions, packaging-checklist tenant pruning,
  and three sample-data replacement tests. No new failing test appeared. This is not a clean
  PostgreSQL suite; those existing failures still need resolution or explicit release triage.

### PostgreSQL failures resolved

The nine baseline PostgreSQL failures listed above were subsequently investigated and fixed.
Final full suites pass without errors/failures on both databases (2,718 tests each).
See [the PostgreSQL follow-up report](postgresql-test-review-2026-09-16.md) for the real tenant-pruning
bug, test-fixture corrections, real replacement coverage, remaining warnings and release limits.
