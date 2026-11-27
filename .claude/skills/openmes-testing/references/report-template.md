# Run report skeleton

Copy into `docs/review-evidence/<topic>-<YYYY-MM-DD>/report.md` and fill in. The
findings style mirrors `docs/production-readiness-review-2026-09-05.md`: stable id,
severity, one-line title, then what was expected against what happened.

Severities: **P1** blocks a presentation or corrupts data · **P2** wrong but workable ·
**P3** cosmetic.

```markdown
# OpenMES <area> walkthrough — <D Month YYYY>

## Scope

- Instance: <base URL> · branch `<branch>` · commit `<sha>`
- Driven as: admin `<username>`, operator `<username>` (created by the run)
- Specs run: `tests/e2e/<spec>.spec.ts`
- Artefacts: `video.webm`, `<step>.png` in this folder

## Result

<One sentence: did the flow complete, and where did it stop if not.>

| Stage | Outcome |
|---|---|
| Line + workstation setup | pass |
| Product type + process template | pass |
| Work order | pass |
| Operator: line select → queue → batch → step | **blocked — F02** |
| Quality control fired in production | not reached |
| Pallet packed and shipped | not reached |

## Roles walked

Every role with access, plus one without. "pass" means the writes worked, not that the
page rendered.

| Screen | Admin | Supervisor | Operator | No access | Guest |
|---|---|---|---|---|---|
| `<screen>` | pass | pass | n/a — no access by design | 403 page | → `/login`, returns after login |

## Scenarios

Green path plus the attempts to break it. Outcome is **refused clearly**, **guided**, or
a finding id.

| # | Scenario | Outcome |
|---|---|---|
| 1 | Green path | pass |
| 2 | Required fields blank, then whitespace only | refused clearly |
| 3 | Quantity 0 / negative / above work order quantity | **F01** — accepted silently |
| 4 | Duplicate work order number, incl. one from a soft-deleted row | refused clearly |
| 5 | `<script>alert(1)</script>` in a name field | rendered as text — pass |
| 6 | Out-of-order: ship a pallet whose QC never passed | guided |
| 7 | Stale screen: deleted in another tab, then acted on | **F03** |
| 8 | Backend-only check: submit past the frontend validation | refused clearly |

## Schema

- Migration added by this work: `<filename>` / none
- Ran against a database holding existing data: yes / no
- `migrate:status` pending after: 0
- Synced-table column added to `ShapeRegistry`: yes / n/a
- Breaking change to an API shape, route, permission name or export format: no / **yes —
  needs a MAJOR bump and a CHANGELOG entry**

## Blank screens

Every place the walkthrough saw nothing, and whether the screen said why.

| Screen | What was empty | Did it explain? |
|---|---|---|
| `<screen>` | BOM table on a template with no components | **F04** — bare "Nothing here yet.", should list linkable materials |

## Findings

### F01 — P1: <one-line title>

- **Where:** `<url or screen>`
- **Expected:** <what the screen should have done>
- **Actual:** <what it did, verbatim error if there was one>
- **Evidence:** `<screenshot>.png`, video at <mm:ss>
- **Reproduce:** <numbered clicks, or the spec and step name>

### F02 — P2: <one-line title>

…

## Walls

Places the flow could not continue and no error explained why — a missing number, a
disabled button, an empty list. These are findings even when nothing visibly breaks.

## Not covered by this run

<What a reader should not assume from a green result.>
```
