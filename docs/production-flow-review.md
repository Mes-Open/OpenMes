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

- Removed newest-order/newest-batch guessing from machine counting. Channels require explicit
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

Do not treat the commit alone as approval to deploy. Existing publishers need the explicit
assignment and timestamp/event-ID contract described in
[the deployment guide](production-flow-deployment.md). Rehearse migration and peak ingestion
rate on a production-sized copy; database tests establish correctness, not an ingestion
capacity or retention budget. Counter evidence is retained and can grow with polling volume.
Resolve or separately account for the known full-workspace test failures before release.

Use [the browser guide](machine-counter-browser-tests.md) to repeat the functional checks.
No production deployment or push is part of this session.
