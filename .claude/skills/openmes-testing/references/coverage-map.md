# What the E2E suite covers

30 specs in `tests/e2e/`. Grouped by the area they exercise, with the flow each one
actually walks. Check here before writing a new spec.

## Full cross-area flows

| Spec | Flow |
|---|---|
| `full-production-run.spec.ts` | Produce a product end to end: admin builds line + product + active process template with a step + work order + in-production QC trigger + EAN, creates an Operator and assigns it to the line; operator selects the line, opens the WO, creates a batch, starts a step, packs a piece onto a pallet, closes it; admin performs the fired QC and ships the pallet. The reference implementation. |
| `computer-production-buildout.spec.ts` | Build a Desktop PC production configuration from zero and run it. |
| `traceability-flow/traceability-flow.spec.ts` | Serialised unit from configuration to shipped pallet, one screenshot per stage. |
| `full-smoke.spec.ts` | Every recently added page renders after a rebuild (shallow — render only, no writes). |

## Install and modules

| Spec | Flow |
|---|---|
| `install-wizard.spec.ts` | Full web install with module selection (HR + Connectivity off). |
| `module-selection.spec.ts` | Disable a module → tab hidden and route 404s → re-enable. |
| `updater-banner.spec.ts` | Updater banner shows "update available" when the remote is newer. |

## Production, planning, materials

| Spec | Flow |
|---|---|
| `work-orders-live-list.spec.ts` | A created and deleted work order appears and disappears live in every open list. |
| `hourly-planner.spec.ts` | Hourly planner renders, cards visible. |
| `component-planner.spec.ts` | BOM defaults and manual selection stay stable in page and drawer. |
| `mrp-net-requirements.spec.ts` | MRP net requirements report shows the shortage. |
| `material-hold-release.spec.ts` | Material hold / release flow. |
| `engineering-documents.spec.ts` | Engineering panel on the product-revision page, with a real upload. |

## Quality

| Spec | Flow |
|---|---|
| `inspection-full-flow.spec.ts` | Start inspection, force a fail, verify the non-conformance is raised automatically. |
| `verify-issues.spec.ts` | `admin/issues` renders inbound NCs with no work order (regression: `UrlGenerationException`). |
| `traceability-recall.spec.ts` | Traceability console resolves a customer order number. |
| `inspection-screenshots.spec.ts` | Screenshot capture only. |

## OEE and dashboards

| Spec | Flow |
|---|---|
| `oee-smoke.spec.ts` | OEE feature smoke (issue #12). |
| `oee-pdf-download.spec.ts` | OEE PDF download. |
| `oee-trend-modes.spec.ts` | Combined and per-line trend modes. |
| `oee-screenshots.spec.ts`, `oee-print-screenshot.spec.ts` | Screenshot capture only. |

## Maintenance

| Spec | Flow |
|---|---|
| `maintenance-index.spec.ts` | Index: groups, badges, actions render. |
| `maintenance-forms.spec.ts` | Create form has all 5 sections. |
| `maintenance-schedules.spec.ts` | Schedules index renders, create form has sections. |
| `maintenance-screenshots.spec.ts` | Screenshot capture only. |

## Logs, alerts, audit

| Spec | Flow |
|---|---|
| `activity-logs.spec.ts` | Activity logs page renders and the middleware records requests. |
| `logs-filters-demo.spec.ts` | Activity logs full filter UI. |
| `system-logs.spec.ts` | System logs, 3 tabs. |
| `audit-logs.spec.ts` | Audit logs page. |
| `alerts-2col.spec.ts` | Alerts page: 2-column desktop, stacked mobile. |

## Screenshot-only specs

`*-screenshots.spec.ts`, `oee-print-screenshot.spec.ts`, `logs-filters-demo.spec.ts`,
`traceability-flow/doc-screenshots.spec.ts` capture images for docs. They prove a page
renders; they assert almost nothing. Do not count them as coverage of a flow.

## Gaps — no end-to-end coverage today

These came out of the 20.10.2026 stability meeting as the areas nobody trusts. None has
a spec that walks it:

- **Operator screen switching.** `operator/select-line`, `operator/queue`,
  `operator/workstation`, `operator/work-order/{id}`, `operator/packaging`,
  `operator/unit-labels/{station,units,components}` — `full-production-run` touches some
  of these in passing, but moving *between* operator screens is undefined and untested.
- **Behaviour on a backend error.** What the operator station shows when a PHP error
  hits during a data fetch. No spec forces a failure and checks the screen.
- **Process templates as a subject in their own right.** Covered only as setup inside
  the full run — `admin/product-types/{id}/process-templates` and its steps / BOM /
  checklist / outputs / media sub-pages have no dedicated spec.
- **Error reporting follow-through.** Raising an issue from the station is walked, but
  what happens to it downstream in production is not.
- **Scheduler.** No spec. The newest tables (`schedule_simulations`,
  `scheduled_operations`, `changeover_rules`) have no browser coverage at all, and there
  are no in-page alerts to assert against yet.
- **HR / employees tab.**
- **User management.**
- **Connectivity modules** — MQTT and OPC UA, and the enterprise module install path
  (`install.sh`, `install.ps1` on Windows).
