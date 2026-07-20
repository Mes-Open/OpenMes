# Changelog

All notable changes to OpenMES are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/).

---

## [Unreleased]

### Added
- **Multiple BOMs per work order** *([#104](https://github.com/Mes-Open/OpenMes/issues/104))*: a work order can now reference **more than one bill of materials** (variant / alternative BOMs) instead of a single fixed one. On the admin create/edit form a new **"Bills of Materials"** picker lets you select **one or more process templates (BOMs)** for the order; the order's requirements and consumption then follow the **union of the selected BOMs**. Overlapping materials are **summed** (a material listed in several BOMs contributes its combined requirement exactly once - so the allocation engine, which keys consumption per material, never silently drops a line); process structure (steps) comes from the first selected BOM. The selection is recorded on a new `work_order_boms` pivot (`is_active` per link) and can be **switched later** from the edit form - but is **locked once production starts** (batches exist), keeping the frozen snapshot consistent with what was already allocated. **Leaving the picker empty is unchanged single-BOM behaviour**: the order auto-uses the single active template for its product type. New `work_order_boms` table, `WorkOrder::bomTemplates()`, `WorkOrderService::buildProcessSnapshot()` + `updateBomSelection()`; no change to the downstream requirements/consumption engines (they read the same flat, now-merged snapshot `bom`).
- **Elapsed-time ("Age") column on the work-order list** *([#100](https://github.com/Mes-Open/OpenMes/issues/100))*: the admin and supervisor **Work Orders** lists gain an **"Age"** column showing how long each order has been open (now - created_at) as a compact human-readable duration (**"just now", "5m", "3h", "2d", "1y"**), with the exact creation timestamp on hover. It is **sortable** (sorts by `created_at`, which is monotonic with age) and **ticks live** - a shared 30s clock refreshes the value without needing a data change. Implemented as a reusable `elapsed()` formatter in `resources/js/lib/i18n.js` and an opt-in **`live: true`** column flag on the shared `ResourceTable` (a single per-table interval feeds the cells via context, so other tables schedule no timer). `created_at` was already synced on the work-order Electric shapes, so no schema/shape change. Adds a minimal **Vitest** setup (`npm test`) covering the duration formatting.
- **Read-confirmation on critical instructions** *([#95](https://github.com/Mes-Open/OpenMes/issues/95))*: a process step can now be flagged as carrying **critical instructions the operator must explicitly acknowledge having read** before the step can be completed. The flag is set once on the **template step** (Admin → Process Templates editor — "Require operator to confirm they read the instructions"), snapshotted onto the work order and **mirrored onto the runtime batch step**, so it only applies **where configured**. At the operator station a flagged step shows a blocked banner and an **"I have read the instructions"** action under its instructions; **Complete stays disabled until acknowledged**, and the acknowledgement **records who confirmed and when** (idempotent and race-safe — the first acknowledger is kept). The gate lives in `BatchService::completeStep` alongside the existing document/checklist gates, so it applies on every surface. Steps not flagged are unaffected. Reuses the existing `template_steps.requires_confirmation` + `batch_steps.confirmed_at`/`confirmed_by` columns; one additive `batch_steps.requires_confirmation` column, no breaking change. Acknowledge from the operator panel (`POST /operator/batch-step/{step}/confirm-instructions`) or the API (`POST /api/v1/batch-steps/{step}/confirm-instructions`), so API-driven clients can clear the gate the shared service enforces.
- **Planner integration for customers & priority** *(phase 4)*: the production planner is now customer- and priority-aware. The **backlog is ranked by the computed priority score** (not just the coarse 1–5 priority), each **backlog card shows its customer and tier badge**, and the backlog can be **filtered by tier** (and searched by customer name). In the weekly Gantt, scheduled orders carry a small **tier dot** (customer name/tier in the tooltip). A red **banner warns when high-tier (Gold/VIP) customer orders are overdue**, linking straight to them. Backlog ordering, the overdue-banner data and the tier metadata are computed in `SchedulePlannerController`; no schema change.
- **Customer auto-scoring — order counts, revenue & tier promotion** *(phase 3)*: completing a work order now rolls its value into the customer's aggregate metrics. Work orders gain an optional **unit price**; when an order first reaches **DONE**, its **produced qty × unit price** is added to the customer's **total revenue** and its **completed-order count** ticks up (idempotently — a reopen/re-complete never double-counts). Customers are then **auto-promoted** by completed-order count (**≥5 → Silver, ≥20 → Gold, ≥50 → VIP**, upgrade-only; thresholds stored in settings), and because a tier change feeds the phase-2 scoring, the customer's other active orders are re-prioritised automatically. A new **Top Customers** admin-dashboard widget shows the highest-revenue customers (with tier badges) and total revenue. `payment_score` remains a manual 0–100 rating for now (no invoicing module yet). New `work_orders.unit_price` + `customer_totals_counted` columns, `App\Services\Customer\CustomerMetricsService`, `App\Support\TierPromotionRegistry`, and the `top_customers` dashboard widget.
- **Automatic work-order priority scoring** *(phase 2)*: a new **Admin → Orders → Priority Settings** page lets you build **scoring rules** that add (or subtract) points based on the customer or the order — sources are **customer tier**, **payment score**, **total orders**, **planned quantity** and **hours until due**, compared with *equals / greater-than / less-than / between / is-true*. Each order's points are summed into a **priority score** and mapped to a **1–5 priority** via a configurable **score→priority band** table (defaults ≤20→P1 … >80→P5). Scoring is **opt-in and non-destructive**: with no active rule, manual priorities are left untouched; once rules exist the score is recomputed on every work-order **create/update**, when a **customer's tier/payment/order-count changes** (re-scores that customer's active orders), and **hourly** via a scheduled `priority:recalculate` command (so "hours until due" rules stay current). The score shows on the work-order list and detail, and the planner/backlog ranks by it. New `priority_rules` table (soft-deletable), `work_orders.priority_score` column, `App\Services\WorkOrder\PriorityScoringService`, `App\Support\PriorityBandRegistry` (bands stored in `system_settings.priority_bands`), `PriorityRuleController` and the `PriorityRuleSource` / `PriorityCondition` enums. Builds on the phase-1 customer entity.
- **Customers — master data for work orders** *(phase 1)*: work orders can now be attached to a **customer**. A new **Admin → Orders → Customers** section provides full CRUD (list, create, edit, soft-delete, activate/deactivate) for customers carrying a **name**, optional **code**, a loyalty **tier** (bronze / silver / gold / VIP), a manual **payment score** (0–100), and app-maintained **total orders** / **total revenue** aggregates plus free-text notes. Work orders gain a nullable **customer** selector on the admin and supervisor create/edit forms; the customer name shows on the work-order list and detail (with a tier badge) and in the production **planner tooltip**. Deleting a customer never removes its work orders. Customers are soft-deletable (Trash + audit) and live-synced via a new `customers` collection. New `customers` table, `work_orders.customer_id` FK (null-on-delete), `App\Models\Customer`, `App\Enums\Tier`, `CustomerController`. This is the foundation for automatic priority scoring (phase 2).

## [0.16.1] - 2026-06-30

### Fixed
- **Release workflow couldn't build the distributable ZIP** *(CI)*: the GitHub release workflow inlined the commit log into a shell assignment (`LOG="${{ … }}"`), so a commit message containing double quotes broke out of the string and bash ran part of it as a command (`serials: command not found`, exit 127) — failing the release before any `openmmes-*.zip` was attached. The release-notes variables (`VERSION`, `CUSTOM`, `LOG`) now pass through `env:` instead of `${{ }}` interpolation, so commit text is read verbatim.

## [0.16.0] - 2026-06-30

### Added
- **Keep a long-running demo's OEE report fresh** *(demo-only)*: the OEE report recomputes today/yesterday from real DONE production, so once a demo's seeded "today" ages, OEE decays into **N/A**. A new scheduled `demo:refresh-oee` command rolls the (fully idempotent) demo production/OEE data forward to the current day. It is **off by default** and **no-ops on real installs** — gated behind a new `DEMO_MODE` flag (`config('openmmes.demo_mode')`); pass `--force` to run regardless. Wired into the scheduler at 00:30 (just before `oee:calculate`). New `App\Console\Commands\RefreshDemoOeeCommand`; no schema change (reuses `OeeAndDowntimeDemoSeeder`).
- **Backup, restore & system reset** *(Settings → System → Data, admin-only)*: create a **full backup** (database + uploaded files) or a **data-only backup**, both streamed to a `.zip` to keep memory low; **download**, **delete**, **upload** and **restore** from a backup; and a destructive **Reset System** (wipe → re-migrate → re-seed → recreate the admin) gated behind a typed `RESET` confirmation. The reset recreates the administrator from `config('openmmes.admin.*')` (read via config so it survives `config:cache`) and refuses to run unless those are configured — no predictable default account. Backups/restore use pure PHP (no shell-out); download/delete paths are realpath-guarded; backup/reset/upload endpoints are admin-only at both the route and Form-Request layers. New `BackupController`, `ResetSystemRequest`, `UploadBackupRequest`. (The DB restore/backup SQL is PostgreSQL-specific.)
- **Vietnamese (vi) UI localization**: the interface can be switched to **Vietnamese** — a full `lang/vi.json` translation plus i18n wrapping (`__()`) of previously-hardcoded strings across the admin, onboarding, packaging, work-order and inspection pages. English/Polish are unaffected.
- **Rich work instructions on a process step - media & checklists**: a process step can now carry **images, PDFs, videos and checklist items** beyond the plain-text instruction. They are defined once on the **template step** (Admin -> Process Templates editor, per step) and resolved **live** at the operator workstation - like reference photos - so updates reach in-flight work orders without re-snapshotting. At the operator station (tablet-friendly) each step shows its media inline: images tap-to-zoom, PDFs embedded with an "Open" link, videos with native controls; files stream through an **authenticated, Range-enabled** endpoint (videos seek, large PDFs load progressively) with `X-Content-Type-Options: nosniff` and a private cache - never a public URL, and images are re-encoded by the existing sanitizer. **Checklists** render as large tap targets the operator ticks off; each tick **records who and when** and can be un-ticked. **Plain-text instructions keep working** unchanged, and steps without rich content are untouched. New `template_step_media`, `template_step_checklist_items` and `batch_step_checklist_completions` tables; `TemplateStepMediaController` (upload/stream/delete) + `TemplateStepChecklistController`; operator toggle endpoint. Strict per-type upload validation (image/pdf/video), IDOR-scoped routes.
- **Milestone backflush - declare consumption on pallet creation**: a configurable milestone that books component consumption **when a packing pallet is created** instead of continuously through the step flow. When the **"Backflush on pallet creation"** toggle (Settings -> System) is on, creating a pallet computes the BOM consumption implied by the **produced quantity** (`quantity_per_unit x qty x (1 + scrap%)` per component, from the work order's BOM snapshot) and **deducts it from stock** in one shot. Each deduction is a plain `StockMovement` (type `consume`) **linked to the pallet** (`source_type=pallet`), so it stays auditable and traceable - reachable via `Pallet::stockMovements()`. The produced quantity can be passed explicitly at pallet creation (`produced_qty`); without one the batch's BOM is booked **once, at its first pallet** (so splitting a batch across several pallets doesn't double-deduct). A pallet whose work order has no BOM consumes nothing. **Off by default** and independent of the allocation engine, so existing continuous-consumption behaviour is unchanged unless enabled. New `App\Services\Production\PalletBackflushService`; no schema change (reuses `stock_movements` + the settings table).
- **Mandatory document control on the shop floor**: a production step can now carry **documents** (SOP, drawing, work instruction, certificate) marked **mandatory** and **validatable**, and the step **cannot be completed until the operator validates them**. The completion gate lives in `BatchService::completeStep` (alongside the existing quality and routing gates), so the block applies to every surface - the operator panel, the web route and the API all refuse with a clear message naming the outstanding document(s). On the operator step view each document is listed with its status and a **Validate** button; an unvalidated mandatory document **disables Complete** and shows a blocked banner explaining why. Validating **records who validated it and when**. Documents are attached and flagged by Supervisor/Admin via `POST /api/v1/batch-steps/{step}/documents`; validation via `POST /api/v1/batch-step-documents/{doc}/validate` (API) or the operator route. New `batch_step_documents` table (soft-deletable, cascades from its step). No change to steps without documents.
- **Operator production rate per machine**: the system now tracks how fast each worker performs on each workstation, in **units/hour**, to feed proficiency context and scheduling. It is derived **live** from existing production events (no new table): every completed batch step records who ran it, on which machine and for how long, so the rate for a worker x machine pair is the units that flowed through their steps divided by the time spent - and it reflects new production the moment a step completes. A new **"Operator production rate (units/h)"** panel on the **Supervisor dashboard** lists the fastest operator x machine pairs (line-filterable), and the metric is queryable via `GET /api/v1/analytics/operator-rates` (Supervisor/Admin) - the list of all pairs with history, or a single `operator_id` + `workstation_id` lookup that returns an explicit **no-data state** for a machine the worker has never run. Optional time window (`days`, or `date_from`/`date_to`; omitted = all history). New `App\Services\Production\OperatorProductionRateService`. No schema change.
- **Outgoing webhooks — HTTP notifications on events** *([#20](https://github.com/Mes-Open/OpenMes/issues/20))*: OpenMES can now POST to external systems (Slack, an ERP, a monitor) when key events occur. Configure endpoints under **Admin → Webhooks** (an optional module, toggleable in Settings → System → Modules): each endpoint has a **URL**, a **signing secret** and a set of **subscribed events** — currently **work-order status changed**, **issue created** and **batch completed** (the catalog is an extensible registry, so more events are a one-line addition). Every delivery is an HTTP POST carrying `{event, data, timestamp}` and an **`X-OpenMES-Signature: sha256=…` HMAC** header (the secret is stored encrypted and never leaves the server — excluded from the live-sync read-path). Failed deliveries are **retried with exponential backoff** (5 attempts: 10s/30s/1m/5m/15m) by a queued job, and every attempt is recorded in a per-endpoint **delivery log** (status, HTTP code, error) viewable in the UI; a **"Send test"** button verifies an endpoint. Outbound URLs are **SSRF-guarded** (loopback/private/reserved/cloud-metadata addresses are rejected at save time and re-checked at delivery). The Docker entrypoint now runs a background **queue worker** on the primary so deliveries work out of the box. New `webhooks` + `webhook_deliveries` tables (the former soft-deletable); `App\Support\WebhookEventRegistry`, `WebhookDispatcher`, `DeliverWebhookJob` and model observers on WorkOrder/Issue/Batch.
- **Select required modules at installation** *([#144](https://github.com/Mes-Open/OpenMes/issues/144))*: installations can now switch off whole feature areas they don't need, to stay lean. A new **"Select modules"** step in the installer (between Database and Admin) lets you enable/disable **Reports, Company structure, HR, Maintenance & Quality, Connectivity and Packaging** (core areas — Dashboard, Orders, Production, Admin — are always on); the choice is editable later in **Settings → System → Modules**. A disabled module is **hidden from the sidebar** for everyone *and* its routes **return 404** (a deep link behaves as if the area doesn't exist). Persisted system-wide in `system_settings.enabled_modules`; when unset every module is enabled, so existing installs are unaffected. As a bonus, the admin sidebar now also respects the per-role tab-access matrix (previously tabs a role couldn't open still showed in the menu). New `App\Support\ModuleRegistry`; enforced in `TabAccessMiddleware` + `HandleInertiaRequests`. No schema change (reuses the settings table).
- **Trace by pallet number or customer order**: work orders now carry a **customer order number** (the customer's own PO/order reference - editable on the admin/supervisor work-order forms, searchable, synced live), and each **pallet is linked to the batch it holds** (set at the packaging station - auto when the work order has a single batch, otherwise picked - and editable in the admin pallet form). The Admin → Traceability console can now resolve a **pallet number** into the full chain **pallet → batch → consumed lots → machine/line → operator → quality controls**, and a **customer order number** into every matching work order with its pallets and batches (each linking into the deeper trace). Pallet labels can now also print the linked batch's LOT. Built on the existing genealogy (`batch_step_lot_consumption`); two additive columns (`work_orders.customer_order_no`, `pallets.batch_id`), no breaking change. Lots/batches only - serialized-component picking is unchanged.
- **Genealogy extended to the output pallet and customer order**: the traceability chain no longer dead-ends at the finished-goods LOT. Searching a **finished-goods lot** in Admin -> Traceability now shows a **"Forward trace - packed & shipped"** panel with the **output pallet(s)** it was packed onto and the **customer order(s)** it fulfils (each linking into the deeper trace); a finished lot not yet packed onto a pallet is handled gracefully. Tracing a **customer order** now reaches all the way down - every matching work order's batches list both their **output lots** (the finished LOTs produced) and the **components used** (the consumed ingredient lots). The link is derived through the existing pallet/batch genealogy (a pallet pins to one batch, a finished lot to the same batch) - **no schema change**. Read-only, admin-only.
- **WO-time lot picking (ERP-aligned "suggest + override")**: when lot tracking is enabled and an operator starts a batch step that consumes lot-tracked materials, OpenMES now proposes which lots to consume (by the configured FEFO/FIFO/LIFO strategy) and lets the operator **override the selection - split or reassign quantities across the available lots** - before the step starts. Tapping **Start** opens a picking modal seeded with the system's proposal (skipped automatically when there's nothing to pick, so the existing flow is unchanged); the chosen lots are validated server-side (belong to the material, released/available, sum to the required quantity) under row locks, so a bad pick rolls back the whole start. The picked lots are bridged into the `batch_step_lot_consumption` genealogy at batch completion, so forward/backward traceability and recall now reflect **what was actually consumed**, not just an automatic guess. No schema change - built on the existing allocation, lot-pick and genealogy tables; auto FEFO/FIFO/LIFO picking is unchanged when the operator supplies no override. Serial-tracked components are out of scope (phase 2).
- **Reverse traceability for recall — "Recall impact" in the traceability console**: Admin → Traceability now answers the recall question directly. Searching a **material lot, supplier LOT or source container** (a component "version") - or a **serial number** - shows a **Recall impact** panel listing every affected work order, its product, the quantity consumed, the batches involved and the **finished serialized units to pull** (each linking back into the console). The walk is **transitive**: a consuming batch's own output lots are followed downstream, so multi-stage builds surface every affected level (bounded by max depth). A typed serial is resolved through the output lots its batch produced - i.e. where that component ended up. Read-only, admin-only; built on the existing `batch_step_lot_consumption` genealogy, no schema change.
- **Reverse traceability for recall - component line journeys on a finished unit**: searching a **serial number** in Admin → Traceability now shows a **Components & production lines** panel - for every component (material lot) consumed to build that finished piece, the **lines and workstations the component itself passed through** during its own production (the steps of the batch that produced it), with the step, operator and completion time. Raw supplied lots are flagged as having no internal line. The per-unit **process history** now also labels each step with its production line. This answers the diagnostic recall question: a finished piece is defective - which component, and on which line, was at fault? Read-only, admin-only; no schema change.
- **Product overview - "Components & serials used"**: the Admin → Product Types detail page now shows, below the process templates and work orders, what actually went into making this product. **Components consumed** aggregates every material lot consumed across all of the product's (non-deleted) work orders → batches → steps, grouped by material with the total quantity and the number of lots (real genealogy from `batch_step_lot_consumption`, not the planned BOM); each row links to the material. **Serialized units** lists the serial numbers produced under the product's work orders with a status breakdown (in production / completed / scrapped / shipped), each linking into the traceability console. Read-only, admin-only.
- **MRP — net requirements & shortage report** *([#90](https://github.com/Mes-Open/OpenMes/issues/90))*: a basic MRP (Admin → Reports → Net requirements) explodes **planned work orders** (PENDING/ACCEPTED) against their BOMs to gross component requirements, nets them against **on-hand stock** (`Material.stock_quantity`) and produces a **shortage list** — component, required, available, shortfall and the **work orders driving the demand** — filterable by period (work-order due date, default next 30 days) and line. Started orders are excluded from demand (their materials are already pulled from stock, so they're reflected in the lower on-hand — no double-counting). Requirement math reuses the allocation engine's formula (`qty_per_unit × planned_qty × (1 + scrap%)`); single-level BOM. New `NetRequirementsService`, web page, and API `GET /api/v1/reports/net-requirements`. Read-only — no schema changes.
- **Additional machine states — waiting, cleaning, maintenance** *([#87](https://github.com/Mes-Open/OpenMes/issues/87))*: machine state tracking gains three states beyond running/idle/stopped/fault/setup so downtime is categorised more accurately. Operators set a workstation's state from the operator panel and supervisors/admins from the Machine Monitor (recorded with `source=manual` in the state history). Each state is treated correctly in OEE/availability: **maintenance** and **cleaning** open a **planned** downtime (scheduled — reduces planned time, not an availability loss), while **waiting** (machine idle waiting for material/operator) opens an **unplanned** downtime that counts as an availability loss. Entering a downtime state auto-opens a `ProductionDowntime` with the right `DowntimeKind`; leaving it closes it — so the new states flow through downtime reporting and OEE without manual entry. Existing states and historical data are unchanged (`workstation_states.state` is a free-text column — no schema migration). New endpoints `POST /admin/machine-monitor/{workstation}/state` and `POST /operator/workstation/machine-state/{workstation}`.
- **Non-conformance management — disposition workflow & root-cause tracking** *([#11](https://github.com/Mes-Open/OpenMes/issues/11))*: an issue (the non-conformance record) can now carry a **disposition decision** — *pending → scrap / rework / return to supplier / use as is* — set from a confirmation modal on the Issues page alongside the **non-conforming quantity**, **root cause**, **containment action** and a responsibility **source** (internal / external / supplier, a separate axis from the existing origin `source`). The disposition is filterable and badged in the issues list. Corrective/preventive actions gain a third **containment** type, and outstanding actions past their due date are flagged **overdue** (a derived flag — the open → in-progress → done → verified lifecycle and the "all actions verified before close" gate from [#107](https://github.com/Mes-Open/OpenMes/issues/107) are unchanged). A new **Non-conformance report** (Admin → Reports) shows a **Pareto by issue type** and a **disposition summary**, an **admin-dashboard widget** surfaces open NCRs by type plus the overdue-action count, and a scheduled `quality:notify-overdue-actions` command logs overdue actions daily. New columns on `issues` (`disposition`, `non_conforming_qty`, `root_cause`, `containment_action`, `nc_source`, `disposition_by_id`, `disposition_at`); API endpoints `PUT /issues/{id}/disposition`, `GET|POST /issues/{id}/actions`, `PUT /issue-actions/{id}`, `GET /reports/non-conformance-pareto`.
- **Quality results linked to a pallet** *([#106](https://github.com/Mes-Open/OpenMes/issues/106))*: a quality check can now be **linked to the output pallet** it concerns (the pallet must belong to the check's work order). Each pallet derives a **quality status** — *pending* (no check yet), *passed* (every linked check passed) or *failed* (any linked check failed) — shown as a badge on Admin → Pallets and **filterable** there. A pallet **can't be shipped until its quality status is passed** (the closed→shipped transition is gated, mirroring the batch-release gate). Link a check from the Quality Controls queue (optional pallet picker), the operator batch check, or the API (`pallet_id` on `POST /batches/{batch}/quality-checks`). New `quality_checks.pallet_id` FK + denormalized `pallets.quality_status`.
- **Quality control triggers — in-production, frequency, after downtime/setup, roaming** *([#105](https://github.com/Mes-Open/OpenMes/issues/105))*: quality controls can now fire **automatically during production**, beyond the manual inbound inspection and ad-hoc per-batch check. Configure **triggers** (Admin → Quality Control Triggers) of six types — **in production** (batch enters production), **every N units**, **every N minutes**, **after downtime**, **after setup/changeover**, and **roaming** (manual ad-hoc) — each optionally scoped to a line, workstation or product type and pointing at a quality-check template. When a trigger fires it raises a **due control** that surfaces in the live **Quality Controls** queue (supervisor/admin); performing it records a `QualityCheck` against the **work order / machine** and, on failure, raises a non-conformance `Issue`. Triggers marked **blocking** hard-gate production — the next step can't start and the batch can't be released until the control is done. Time-based triggers fire via a scheduled `quality:fire-due-triggers` command; the rest fire synchronously from the production lifecycle (`BatchService`, `DowntimeService`). New `quality_control_triggers` + `quality_control_tasks` tables follow the soft-delete-with-audit pattern. Existing inbound inspection and per-batch checks are unchanged.
- **Non-conformity workflow — material hold/release + batch-release quality gate** *(part 2 of [#107](https://github.com/Mes-Open/OpenMes/issues/107))*: quality can now put **any material lot on hold** (→ quarantine, with a reason and an optional link to the triggering issue) and **release** it later, straight from Admin → Material Lots — not just via an inbound-inspection disposition. Held lots stay out of the allocation engine. A new **quality gate on batch release** refuses to release a batch whose work order is blocked by an open non-conformance, or that consumed a held (quarantined/rejected) lot. Implemented in `MaterialHoldService` + `BatchReleaseService`.
- **Non-conformity workflow — corrective/preventive actions (CAPA) on issues** *(part 1 of [#107](https://github.com/Mes-Open/OpenMes/issues/107))*: an issue (the non-conformance record) can now carry **corrective and preventive actions**, each with an assignee, due date and lifecycle **Open → In progress → Done → Verified**. The issue **can only be CLOSED once every action is Verified** (enforced server-side in `IssueService::closeIssue` for both the admin/supervisor UI and the API). Manage actions from a panel on the Issues page (Admin → Issues / Supervisor → Issues). New `issue_actions` table follows the soft-delete-with-audit pattern and cascades when its issue is deleted.

### Changed
- **Background jobs run asynchronously on the Docker stack** (`QUEUE_CONNECTION=database` + a built-in queue worker): real queued jobs — webhook delivery, CSV import, MQTT message processing, auto-update — now run on a worker with retries instead of inline on the request thread, so a slow/failing job no longer blocks the HTTP response. The backend entrypoint runs a self-restarting `queue:work` on the primary, so a plain `docker compose up` processes jobs out of the box (the standalone `queue-worker` service, profile `workers`, can still scale this out). Live-sync is unaffected — its broadcasts are `ShouldBroadcastNow` and never used the queue. **Existing installs keep their current `.env`** (no surprise switch); only the Docker stack and fresh installs default to `database`. Non-Docker installs that opt into `database` must run a worker themselves, otherwise jobs queue and never execute.

### Fixed
- **Blank page on `/admin/schedule` and two other pages (missing `__` import)**: the localization work wrapped UI strings in `__()` on the **schedule planner**, **supervisor work-order detail** and **label-template form** without importing the helper, so they threw `ReferenceError: __ is not defined` and rendered a blank white page (Vite can't catch a runtime ReferenceError, so the production build was affected too). Added the missing import on all three; a repo-wide sweep confirms they were the only affected pages.
- **Error-handling hardening from production telemetry (schedule 500 / sample-data 409 / dashboard 403)**: three failures observed via `http_error` session telemetry are fixed. (1) **Dragging a work order on the schedule planner could 500** — `PUT /admin/schedule/{wo}` runs auto-snapshot + auto-batch side-effects, and a failure there (e.g. incomplete BOM/material data) crashed the whole request and discarded the placement; the side-effects are now wrapped so the **schedule placement always saves** and a batch-prep failure degrades to a warning. (2) **"Load Sample Data" could 409** — re-running the demo seeder against an already-populated database raced on unique keys; it's now **guarded by a `sample_data_loaded` flag** (idempotent, friendly "already loaded" message) and any seeder error is reported instead of bubbling up. (3) **`/admin/dashboard` hard-403'd** users who couldn't open the dashboard tab but could open others — a dead end; the admin home now **redirects them to their first accessible tab** (specific resource tabs and API requests still return a real 403, preserving the access-matrix contract).
- **BOM items could be added and removed but not edited**: the process-template Bill of Materials page only exposed *Add* and *Remove*, so correcting a quantity, step, scrap %, consumed-at or notes meant deleting the line and re-adding it. The `PUT .../bom/{bom_item}` endpoint already existed (`BomManagementController::update`) but no UI was wired to it. Each BOM row now has an **Edit** action that opens the form pre-filled (the material itself stays fixed - it's the line's identity); saving issues the `PUT`. The controller now also exposes `template_step_id` on each item so the edit form can pre-select the current step.
- **Operator couldn't start the first step of a batch**: a newly created batch promotes its first step to `READY` (the "next in line" state), but the operator Work Order detail only rendered a **Start** button for `PENDING` steps — so the first step showed no action and production couldn't begin. The button now appears for `READY` and `PENDING` alike (the backend already accepted both). Found driving a full production run through the UI.
- **Creating a work order failed when a BOM component had no material type**: since material type became optional, a BOM line could reference a material without a type, but `ProcessTemplate::toSnapshot()` (and `BomService::calculateRequirements()`) dereferenced `material->materialType->code` — the resulting "Attempt to read property on null" rolled the work-order create transaction back with *"Failed to create work order"*. Both now use the null-safe `materialType?->code`. Found building a computer-production configuration whose components had no material type.
- **Performing a quality control crashed the page (white screen)**: the *Perform quality control* modal passed `tone="info"` to `InlineAlert`, but the component reads the `severity` prop — so it dereferenced an undefined severity (`Cannot read properties of undefined (reading 'bg')`) and the React tree threw, making it impossible to record any quality control from the queue. Corrected to `severity="info"`.
- **Supervisors can now create & manage work orders (as the role docs state)** *([#122](https://github.com/Mes-Open/OpenMes/issues/122))*: the Supervisor role already held the `create work orders` ability and `WorkOrderPolicy::create` allowed it, but there was no way to use it — no supervisor create route/controller/button, and the role lacked the `tab:orders` permission needed to reach the admin order pages. Supervisor now gets `tab:orders` (seeder) **and** a native create flow: `GET/POST /supervisor/work-orders` (authorized via `WorkOrderPolicy`) with a **"+ New Work Order"** button on the supervisor Orders list. Operators and guests remain blocked.
- **Live-sync lists 401 / appear empty when the app host isn't `APP_URL`**: creating a record returned `200` but the list stayed empty because the live-sync read (`GET /api/collections/{name}`, guarded by `auth:web,sanctum`) was only treated as a stateful session request when the host matched `localhost`/`127.0.0.1`/`APP_URL`. Served on any other host (LAN IP, custom domain, a port `APP_URL` doesn't cover, behind Caddy) the request wasn't stateful → no session → `401` → empty lists. `config/sanctum.php` now includes `Sanctum::currentRequestHost()`, so the app's own host is stateful for **same-origin** requests on any deployment (cross-origin requests stay non-stateful, so CSRF protection is unchanged). `SANCTUM_STATEFUL_DOMAINS` still overrides.
- **Production Docker image build broken by the `@openmes/ui` workspace package**: the image's `npm run build` failed with `Rollup failed to resolve import "@openmes/ui"` because `backend/package.json` depends on it via `file:../packages/ui`, but `backend/Dockerfile` never copied `packages/` into the build. The Dockerfile now copies `packages/` to `/var/www/packages/` before `npm ci` (and removes it after the build to keep the image lean); the dev-overlay frontend watcher gains a `./packages` bind mount so its runtime `npm ci` resolves the dependency and edits to `@openmes/ui` rebuild live. Only the image build was affected — PR CI runs the PHP suite, not the Docker build.

---

## [0.15.5] - 2026-06-21

### Fixed
- **German locale terminology**: "Work Order" now translates as **Fertigungsauftrag** (production order) instead of the generic *Arbeitsauftrag*, matching the MES domain — applied across all singular/plural/compound occurrences in `backend/lang/de.json`.

---

## [0.15.4] - 2026-06-19

### Added
- **German (de) locale**: OpenMES is now available in German — a complete `backend/lang/de.json` (full key-parity with English, MES terminology) plus registration as a selectable language, so German can be chosen from the language switcher (login screen, Settings → System) exactly like English / Polski / Türkçe.

### Changed
- **Material type is now optional**: a material no longer has to belong to a material type. The `materials.material_type_id` column is nullable, the create/edit form offers a "— None —" option, and the web + API validation accepts a missing type (an invalid/non-existent type is still rejected). The foreign key (restrict-on-delete) is unchanged and still applies when a type is set.

### Fixed
- **Web-shell/XSS test fixtures defanged**: the upload-sanitizer security tests embedded literal `<?php system($_GET[...])` / `<script>` payloads (used to prove the sanitizer destroys them), which matched antivirus signatures (`Backdoor:PHP/Perhetshell.A`) and got the files quarantined on checkout. The payloads are now assembled from fragments at runtime — identical test behaviour, no literal payload left in source.

---

## [0.15.3] - 2026-06-16

### Added
- **Windows installer (`install.ps1`)**: a PowerShell port of `install.sh` with the same behaviour — auto-selects a free host port (80 → 8080/8443+), generates `.env` with secure random credentials (written LF/no-BOM so values aren't corrupted), builds + starts production, waits until the app actually serves, and prints the URL + login. Run `.\install.ps1` in PowerShell with Docker Desktop (`-Interactive` to customise, `-Dev` for the watch overlay). A `.gitattributes` keeps shell scripts LF-only so a Windows checkout doesn't break the `#!/bin/bash` shebang.
- **"Ready to start" batch-step status**: a new step state between **Pending** and **In Progress**. A step is **Pending** while it's still blocked (its predecessor isn't done), becomes **Ready to start** once its prerequisites are met (first step, predecessor done/skipped, or non-sequential mode) — i.e. it's next in line and waiting for an operator — and then **In Progress** once started. Operators only see the **Start** button on Ready steps; Pending steps show as blocked. The next step is promoted to Ready automatically when the current one is completed or skipped. No schema change (the status column is a free string); existing in-flight batches are backfilled by migration, and the value syncs to live views like any other status. _(Refs [discussion #69](https://github.com/orgs/Mes-Open/discussions/69))_

---

## [0.15.2] - 2026-06-16

### Changed
- **Smoother first-run install**: `install.sh` now **auto-selects a free host port** — it prefers 80/443 and falls back to the next free port (e.g. 8080/8443) when those are taken, then wires `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` to match, so `git clone && ./install.sh` works even when port 80 is occupied. It runs a **real production build** by default (Octane + the frontend baked into the image) instead of the vite-watch dev overlay (use `./install.sh --dev` for that). Container names are now **prefixable** (`${OPENMES_NAME_PREFIX:-openmmes}-<service>`) and `install.sh` derives a unique prefix from the install directory, so **several local instances can run at once without clashing on container names** (a single deployment still gets the familiar `openmmes-*` names; docs use `docker compose exec <service>` so they work regardless of the prefix). Prompts are now **non-blocking**: it runs unattended when there's no terminal (piped / CI) and treats Enter as "proceed" (`--yes` to force it) — previously a no-input run aborted with exit 1 before writing `.env` or starting anything, because a `read` under `set -e` failed on EOF. It also **reuses the existing DB/admin passwords on re-run** so they keep matching the already-initialised postgres volume (previously a re-run regenerated the password and broke DB auth). `docker-compose.yml` now defaults to `pull_policy: build`, so a plain `docker compose up` builds from the cloned source (layer-cached → instant when unchanged) rather than failing on the private image or serving a stale one; deployers pulling a published tag can set `OPENMES_PULL_POLICY=missing`. `install.sh` is also **macOS/BSD-safe** now: port detection uses a portable bash `/dev/tcp` probe (no Linux-only `ss`/`netstat` flags) and password generation runs `tr` under `LC_ALL=C` (BSD `tr` otherwise aborts on `/dev/urandom` bytes with "Illegal byte sequence").

---

## [0.15.1] - 2026-06-15

### Fixed
- **Sidecar containers raced the database migrations**: the `reverb` (and `queue`/`mqtt`/`modbus`) containers share the app image and ran the full entrypoint, so on a fresh `docker compose up` every sidecar ran `migrate`/`db:seed` concurrently with the backend — flooding the logs with `duplicate key "pg_type_typname_nsp_index"`, `relation … already exists` and `column "deleted_at" … already exists`, and crash-looping reverb. The entrypoint now runs migrations/seeders/admin-creation/scheduler **only on the primary (Octane) container**; sidecars log "skipping migrations/seeders" and start straight away.
- **Creating a division without a factory returned a 500**: `divisions.factory_id` is `NOT NULL`, but the controller validated it as `nullable`, so submitting the form without a factory (e.g. on a fresh install with none created yet) hit a Postgres NOT NULL violation. It's now `required`, returning a normal 422. Regression test added.
- **Stale caches surviving a Docker upgrade**: `bootstrap/cache` is a persisted volume, so after `git pull` + rebuild the previous release's compiled **route cache** could keep serving old middleware (e.g. the pre-0.15 `role:Admin` `/admin` group instead of `tab.access`), surfacing as a bogus **403 "user does not have the right roles"** — even for the admin. The entrypoint now wipes the route cache alongside the config/package caches before rebuilding, and resets the Spatie permission cache after seeding so the freshly-seeded roles/permissions are authoritative on every boot.

---

## [0.15.0] - 2026-06-14

### Added
- **Per-role tab access (Settings → Access)**: a new admin-only settings page with a **role × tab matrix** of checkboxes that grants each role access to individual admin-panel tabs (Orders, Production, HR, Maintenance, …). Enforced server-side by a single `TabAccessMiddleware` on the `/admin` group (each path resolves to a tab via `TabRegistry` and checks a `tab:<key>` permission), and reflected in the sidebar (tabs a role can't access are hidden). The **Admin** role is locked to full access and can never be revoked (`Gate::before` safety net), so you can't lock yourself out. Backed by Spatie permissions — no new tables; existing installs get the `tab:*` permissions (and Admin grant) via migration.
- **Optional & variant steps in process templates**: a template step can be marked **Optional** (operators may skip it during a batch) or assigned to a **variant group** — steps sharing a group are mutually-exclusive alternatives (e.g. *Matte* vs *Gloss finish*). One step per group is the **default variant** for that product (the template is product-type specific); it is pre-selected when a batch is created and its siblings start skipped, but the operator can switch with **Choose**, or skip an optional step with an optional reason. A batch can't be completed while a variant group has nothing executed (exactly one variant must run). Authored from the process-template step editor; runtime actions on the operator work-order screen.
- **Soft deletes with deletion audit (all domain entities)**: deleting anything (work orders, lots, workers, users, structure, connectivity configs, …) no longer removes the row — it is marked deleted together with **who** deleted it and when. Deletes cascade to dependent records exactly like the old DB cascades (e.g. work order → batches → steps), and the new **Admin → Trash** page lists everything deleted (item, type, user, time) with one-click restore that also brings back the records deleted along with it. Unique codes/numbers can be re-used after deletion (validation and DB unique indexes ignore trashed rows), and deleted rows never reach live-synced lists.
- **Source container on material lots**: new `source_container_no` field (scanned identifier of the physical container/pallet/drum a delivery arrived in). Scan inputs on the admin material-lot registration form and on the inbound-inspection start screen — the value entered at receiving is carried onto the material lot the inspection creates. Shown in the traceability console (lot header, ingredient-lot table, backward-trace tree) and resolvable in the genealogy search alongside lot number / supplier LOT / serial number.
- **Pallets**: new shippable-unit entity. Each pallet gets a unique `pallet_no` drawn from a dedicated Postgres sequence (format `PAL-000001`), and tracks `work_order_id`, `qty`, `status` (open / closed / shipped), `location` and `erp_reference`. Full admin CRUD under **Packaging → Pallets** (`/admin/pallets`), live-synced list.
- **Pallet labels**: new `pallet` label-template type wired into the existing template/label system. Prints QR + 1D barcode (both encoding the pallet number) plus pallet no., product, quantity and location, as PDF or ZPL. A default *Standard Pallet* template (100×100 mm) is seeded.
- **Packing-station create-pallet action**: operators can open a pallet for a work order at the scanning station; subsequent scans of that order's pieces are assigned to the open pallet (incrementing its `qty`), with a close action and one-click label print. A scan whose EAN belongs to a different work order than the open pallet is rejected.
- **Resume open pallets across shifts**: open pallets persist with their running `qty`, so a pallet started on one shift can be continued on the next. The scanning station now shows a list of all open pallets grouped by production line (derived from each pallet's work order, filterable via `GET /packaging/pallets?line_id=`), each with a **Resume** action that makes it the active pallet so further scans keep filling it instead of opening a new one.
- **Friendly in-app error page**: in production, error statuses (500/503/404/403/429) now render an Inertia `Error` page that keeps the user's chrome (sidebar) — admins/supervisors get the admin sidebar, operators their touch layout — so they can navigate away instead of landing on a bare error screen. API/JSON clients keep their normal JSON error; local/testing keep the debug page.
- **Sidebar menu search**: a search box at the top of the sidebar filters all navigation entries (including items nested in groups/subgroups) and shows a flat result list with the group path; matches both English labels and the active locale's translations. Enter opens the first result, Escape clears; on a collapsed sidebar the search icon expands it and focuses the input.
- **Shift handover screen** (Supervisor): per-line balance reconciling **produced** (operator shift entries) − **scrap** = **good**, vs **packed** (station scans), vs **WIP** (open pallets' qty + still-unpacked good output), vs **shipped** (dispatched pallets), all scoped to the active shift window. Flags discrepancies (unpacked output, awaiting shipment, over-packed). Requires supervisor confirmation to **close the shift**, which writes an immutable audit snapshot (figures + who/when + open-pallet breakdown). New `shift_handovers` table; audit history on the same screen.
- **Worker absences / availability**: record per-worker absences (vacation / sick / personal / training / other) over a date range (full-day or partial), with status (approved / pending / rejected). New admin CRUD under **HR → Absences** (`/admin/worker-absences`), live-synced list, overlap-validated. A `WorkerAvailabilityService` exposes `isAvailable()` / `isAbsentOn()` / `absentWorkerIds()` as the seam for assignment warnings and capacity planning.
- **Crew-level break windows**: define recurring intra-day breaks (e.g. lunch 12:00–12:30) that apply to a whole crew on selected weekdays. New admin CRUD under **HR → Break Windows** (`/admin/crew-break-windows`), live-synced list, overlap-validated per crew. `WorkerAvailabilityService` gains `isOnBreak()` and `crewBreakWindowsOn()` — a worker is "on break" whenever their crew is — extending the same availability seam used by absences.
- **Scrap Overview dashboard widget**: the admin dashboard gains a *Scrap (30 days)* widget showing total scrap quantity, scrap-entry count and the top scrap reason for the trailing 30 days (reuses `ScrapReportService::pareto()`, so the figures match the Scrap Reports page). Registered as the `scrap_overview` widget (enabled by default, toggleable like the other dashboard widgets) with a link through to the full scrap report.

### Changed
- **Operators granted admin tabs can reach them without leaving line selection**: an operator still lands on the line-selection screen (their primary screen) after login. When they've been granted any tab in the **Settings → Access** matrix, the operator screen shows the **same sidebar as the admin panel**, with **Lines** (back to line selection) added at the top, so they can open the granted panel pages directly with identical chrome; the panel's own sidebar likewise lists **Lines** first, so navigation is consistent both ways. Operators with no granted tab keep the plain full-screen operator view.
- **Packing station now follows the configured Shifts**: the station's "this shift" window (packed-count / history / stats) is derived from `Shift::current()` (admin → **Shifts**) instead of a hard-coded 06:00–18:00 / 18:00–06:00 split — including overnight shifts — falling back to the fixed split only when no shift is configured. The station header shows the active shift's name and window.

### Fixed
- **Demo seeder crash (#73)**: `OeeAndDowntimeDemoSeeder` still inserted downtime reasons with the removed `is_planned` column (replaced by `kind`), so `DemoDataSeeder` aborted with a PostgreSQL "undefined column" error and demo OEE/downtime data was never created. It now writes `kind` (`planned` / `unplanned` / `changeover`). Regression test added.

### Security
- **Patched dependency advisories**: updated `laravel/framework`, `phpoffice/phpspreadsheet`, `guzzlehttp/psr7`, and several Symfony components (`routing`, `http-foundation`, `http-kernel`, `mailer`, `mime`, `polyfill-intl-idn`) to their fixed releases, clearing 17 advisories — including two critical PhpSpreadsheet SSRF/RCE issues (CVE-2026-34084, CVE-2026-45034) and high-severity email-header/SMTP injection in `symfony/mime`. `composer audit` is now clean; no version constraints changed (lockfile-only update).

---

## [0.14.5] - 2026-06-12

### Fixed
- Creating/updating a maintenance schedule with an empty **Lead time (days)** crashed with a NOT NULL violation (23502): the field is validated as nullable but the column is `NOT NULL default 0`, and submitting `null` overrode the default. The controller now falls back to `0` ("generate on the due date"). Regression test added.

---

## [0.14.4] - 2026-06-09

### Fixed
- Demo tenant pruning (`tenants:prune`) crashed every minute with a 23503 foreign-key violation: deleting a tenant cascades to its users, but `packaging_checklists.checked_by`, `quality_checks.checked_by` and `process_confirmations.confirmed_by` referenced users with `restrictOnDelete`, blocking the cascade. These audit references are now `nullOnDelete` (migration), matching every other user FK. The command also isolates each tenant in its own transaction/try-catch so one bad delete can't abort the whole scheduled run.
- System Settings page no longer 500s on a fresh tenant: `showSystemSettings()` read `$rows['key']->value` which threw "Attempt to read property 'value' on null" when a `system_settings` key had not been written yet. All ~24 reads are now null-safe (`?->value`), so the page renders with defaults until settings are saved. Regression test added (renders with an empty settings table).

### Changed
- Sidebar: nav groups that have their own landing page (e.g. **Modules**) now navigate there on click instead of only expanding — previously clicking the group header did nothing visible.
- Forms (all config-driven CRUD via `ResourceForm`): a failed submit now scrolls to and focuses the first invalid field and shows an error summary at the top — the main cause of form abandonment (e.g. Material Lot registration). Required Material Lot fields gained clearer placeholders/hints.

---

## [0.14.3] - 2026-06-09

### Fixed
- Process template steps: Save / Add / Delete / Move on the step editor hit a 404 (popup) because the React page posted to the route *names* (`/update-step/{id}` etc.) instead of the actual RESTful paths (`/steps/{id}`). Corrected all five step-action URLs; added a web test pinning the literal paths.
- Live sync no longer breaks writes: a failing Reverb broadcast (e.g. server unreachable) is now caught so the originating create/update/delete still succeeds — fixes work-order actions (Accept etc.) erroring out when the broadcaster is down. Clients fall back to polling.
- Structure deletions (workstation type, workstation, site, factory, division) surface a friendly "still referenced — deactivate instead" message instead of a 500 when a foreign-key constraint is hit.

### Changed
- Work order detail: issue cards are now clickable links to the filtered issues list (previously they looked interactive but did nothing).
- BOM material picker: the unit of measure is shown in the material dropdown and next to "Quantity per Unit", quantity has a helper hint, and an auto-filled default scrap % is now labelled.
- Form guidance: the user form's worker-profile section is now a collapsible block (collapsed by default, auto-expanded when editing an account that already has one); the tool and material forms gained helper hints clarifying optional/ERP fields and tracking modes.

---

## [0.14.2] - 2026-06-09

### Fixed
- Bare-hosting install wizard could not render (HTTP 500 / blank page): the shipped `.env` defaults to database-backed sessions (correct for the Docker stack, whose entrypoint marks the app installed before serving), so on a plain PHP host every request — including the installer itself — queried a database that does not exist yet. The app now forces file-based session/cache drivers while the `storage/installed` flag is absent, so the wizard boots without a database; once installed, the configured drivers and the migrated `sessions` table take over. No effect on Docker (already installed at boot) or the test suite (`runningUnitTests` guard).
- A finished bare-hosting install no longer goes live as `APP_ENV=local` / `APP_DEBUG=true`: completing the wizard now writes `APP_ENV=production` and `APP_DEBUG=false` (the environment step is otherwise skipped because `public/index.php` auto-generates `APP_KEY`).

---

## [0.14.1] - 2026-06-09

### Fixed
- Inertia page resolution on case-sensitive filesystems: published `config/inertia.php` pinned to `resources/js/Pages` (the package default points to lowercase `js/pages`, which never resolves on Linux/CI and broke `assertInertia()->component()` page-existence checks).

### Changed
- Release packaging: the `Release` GitHub Actions workflow no longer attempts to push the version bump to the protected `main` branch (which aborted the run before any artifact was built). It now builds a **self-contained** distributable ZIP — source plus bundled `vendor/` and `backend/public/build/` — so the package installs with no `composer install`/`npm build` step, going straight into the browser-based setup wizard.

---

## [0.14.0] - 2026-06-08

### Added
- Scrap reason codes - categorized defect tracking per work order: `scrap_reasons` with a 5M Ishikawa category (material/machine/method/man/environment), admin CRUD + activate/deactivate, and 5 seeded default reasons (#13)
- Operator scrap reporting on the work order detail page (reason, quantity, notes); `scrap_entries` link to the work order and optionally to a batch step and shift, with a per-work-order total scrap quantity and a derived quality % metric (#13)
- Scrap reports: Pareto by reason, scrap rate per line, and scrap trend over time (Chart.js), plus REST API endpoints `reports/scrap-pareto`, `reports/scrap-rate`, scrap-reason read/CRUD and scrap-entry report/list (#13)
- Work Order History: relocated Reports into its own nav group (between Production and Structure) and turned it into a read-only historical analysis view over finished orders (DONE / CANCELLED / REJECTED). Filter by status, line, product type, full-text (order no. / LOT) and date — with day presets (today, yesterday, last 7/30 days, this/last month, custom range, all time). Summary aggregates (orders, produced, planned, avg execution time, on-time %), CSV export, and a deep per-order drill-down: execution timeline, batches with assigned LOTs, steps with start/end times, duration and operator, material genealogy (consumed lots), quality checks and issues raised. All execution data is retained indefinitely.
- Production Cost report: per-work-order costing that sums material + labor + additional costs into a total and a cost-per-unit, with a detailed per-line breakdown (each material qty x unit price, each worker's hours/pieces x rate, each additional cost). Material cost uses actual recorded consumption (price snapshotted at consumption time for stable history) and falls back to the BOM recipe; labor cost is driven by per-worker pay mode. Filterable list (line, product, date presets) with summary cards and CSV export, under the Reports nav group.
- Per-worker compensation: pay type (hourly / weekly / piece rate) and rate set on the worker edit form. Hourly bills rate x hours on the order; weekly converts the salary to an effective hourly rate via a configurable `standard_weekly_hours`; piece rate bills rate x pieces, splitting a work order's output across piece-rate workers proportionally to their logged hours. The wage group remains a fallback when no per-worker rate is set.
- LOT number pattern generation: build LOT identifiers from composable tokens (e.g. `prefix-[date]-[numeric]-[hourly]`) — operators define the parameter set once and the system renders consistent, collision-safe LOT numbers per work order (#55)
- Process template photos: attach reference photos to a process template, with a hardened upload path — every image is fully decoded and re-encoded server-side (`ImageSanitizer`, GD) to strip EXIF/polyglot payloads, accepting only JPEG/PNG/WebP (#56)
- Per-step process photos: one reference photo per production step (rather than a single photo for the whole template), shown inline on the operator's work order view so each step carries its own visual instruction (#63)
- Operators on the line can view the full process-build photos for the product they are working on, read-only (#58)
- Inspection plan versioning: plans carry a version number and `published_at`; editing a published plan creates a new immutable version, and recorded inspection results store the exact plan version used, so historical results stay reproducible (#62)

### Changed
- i18n: the UI-language whitelist is now single-sourced from `config('app.available_locales')`; date/time and number formatting is locale-aware (BCP-47 mapping) instead of a hardcoded clock locale, and `APP_TIMEZONE` is honoured across the app (#57)
- Login screen is fully translated (#54)
- Reports nav entry moved out of the Admin group into a dedicated Reports group (Scrap Reports moved alongside it); the previous aggregate KPI dashboard was replaced by the Work Order History view.
- Worker create/update validation moved into dedicated Form Requests (`StoreWorkerRequest` / `UpdateWorkerRequest`).

---

## [0.13.0] - 2026-05-31

### Added
- Two-Factor Authentication (2FA): TOTP with QR setup, 8 one-time recovery codes (encrypted/bcrypt), login challenge, rate limiting, enable/disable with password (#41)
- Workstation routing: optional mode restricting each operator to steps assigned to their own workstation, enforced by a single server-side guard in `BatchService` (covers both the Livewire UI and the REST API)
- Machine connectivity — protocol-agnostic signal pipeline: machine tags, workstation state machine, automatic downtime from machine state, per-workstation OEE, Live Machine Monitor
- Modbus TCP connector: poller daemon (`modbus:poll`), in-PHP simulator (`modbus:simulate`), tag editor and CRUD (#24)
- OPC UA connector: gateway sidecar (`opcua-gateway/`, Node.js node-opcua) bridging to a protocol-agnostic ingest API, node editor and CRUD (#23)
- Runtime health awareness: connection pages show whether the required daemon/container is running, with copy-paste start commands (bare metal + Docker)
- Material traceability / genealogy: formal batch-output -> input-lot link (`material_lots.source_batch_id`), traceability console (forward/backward trace by finished LOT / material lot / supplier LOT / serial number), per-unit serial tracking (`serial_units`, `unit_step_history`) with parameter snapshots and API
- Turkish (Turkce) as a third UI language (1253 strings, English-first preserved) (#44)
- Real-time polling for operator queue and workstation views (work flows between stations automatically)
- Alerts: show all open issues (not just blocking), with real-time polling and alert sound
- Maintenance reminder popup with sound for supervisors and operators
- Maintenance events on the schedule planner (weekly + hourly Gantt), recurring blocks across the full visible range, `scheduled_end_at` (start + end time)
- Production quantity correction with a configurable edit policy (none / timed / full)
- Full configuration export/import (JSON): lines, products, templates, materials, shifts, ISA-95 and more, upsert-based to preserve FK relations, with a forbidden-keys whitelist
- Import buttons and example CSV downloads on Materials, Product Types and Lines
- Opt-in `docker-compose` services: `modbus-poller`, `opcua-gateway` (connectivity profile), `queue-worker` (workers profile)
- `docs/machine-connectivity.md`: signal pipeline, protocols, how to add a new protocol, and intentionally-deferred items (Reverb, write-back, real-server OPC UA test) with rationale

### Fixed
- Schedule planner: overlapping work orders stacked in lanes on the hourly Gantt; drag-and-drop no longer drops a work order when moved to another line; maintenance block position corrected (diffInMinutes argument order)
- Config import: upsert instead of truncate to preserve FK relations with production data; PostgreSQL savepoints for per-row error recovery
- Carbon 3 signed-`diffInSeconds` fixes in machine state durations and availability calculations
- Restore Polish packaging/label translations dropped in an earlier merge conflict (#47)

### Security
- CORS defaults are now fail-closed: empty allowed-origins blocks all, GET/POST only, no preflight cache
- Ownership check on production quantity corrections; hardened settings import

### Changed
- Packaging module views: hardcoded Polish strings replaced with `__()` translations (#43)
- License switched from MIT to AGPL-3.0

## [0.12.0] - 2026-05-24

### Added
- ISA-95 / IEC 62264 foundations (equipment hierarchy, material lots, process segments, personnel classes, quality disposition)
- Minute-level production planning with hourly Gantt view (drag, resize, cross-line moves)
- Material allocation hardening (lot picking, stock movements, reservation system)
- Activity Logs and System Logs with live tail and detail modal
- Maintenance overhaul: recurring schedules, redesigned forms and index
- Updater hardening (8/8): snapshot/rollback, SHA256 checksum, background job, maintenance mode, audit trail
- Inbound quality inspection workflow with dashboard widget
- OEE dashboard: one-click PDF download, shift breakdown, per-line trend
- Extended demo seeder: shifts, materials, lots, ISA-95 hierarchy, skills, segments, maintenance, OEE records
- Packaging moved from module to core: label templates, PDF label generation
- Security Policy (SECURITY.md) and Code of Conduct
- Screenshots in README (dashboard, planner weekly/hourly, operator queue/workstation)

### Fixed
- Sidebar "Orders" group: label now navigates to Work Orders, chevron toggles submenu
- Schedule planner: prev/next preserves view mode, correct step per mode (daily=1 day, weekly=1 week, monthly=1 month)
- Unassign clears planned_start_at/planned_end_at
- Max validation (99999999) on planned_qty across all controllers
- SQL errors hidden from users in production (generic message + report())
- CSV formula injection neutralization in all exports
- PR review fixes: __() translations, raw SQL replaced with Query Builder, hardcoded URL moved to config


## [v0.13.0] - 2026-05-30

### Added
- OPC UA gateway + runtime health awareness
- Modbus TCP + protocol-agnostic machine signal pipeline
- material genealogy — lot-link, console, serial units
- add Turkish (Türkçe) as a third UI language
- workstation routing — restrict operators to their own station
- Two-Factor Authentication with TOTP and recovery codes (#41)
- add real-time polling for workstation queue and production view
- maintenance reminder popup with sound for all users (supervisors, operators, admins)
- generate recurring maintenance blocks for entire visible range (weekly shows every week, not just next_due)
- show upcoming recurring maintenance on planner from schedules (not just existing events)
- add scheduled_end_at — maintenance events have start and end time on planner
- show maintenance events on planner (weekly, hourly views)
- show ALL open issues (not just blocking), add real-time polling with alert sound
- full config export/import — lines, products, templates, materials, shifts, ISA-95, and more
- add settings import (JSON upload) with security whitelist
- add Import button + example CSV download on Materials, Product Types, Lines; add Settings export
- stack overlapping WOs in lanes on hourly Gantt view instead of overlapping
- production quantity correction — configurable edit policy (none/timed/full)

### Fixed
- restore Polish packaging/label translations dropped in merge conflict
- replace hardcoded Polish strings with __() translations
- security review
- correct maintenance block position — diffInMinutes argument order was reversed
- maintenance blocks top-aligned and more visible on hourly Gantt (bg-purple-200, shadow, top instead of bottom)
- pass maintenanceEvents to hourly partial (was missing from include)
- increase maintenance event block size on hourly Gantt (24px→36px, bolder text)
- reported issue with packing
- config import — use savepoints for PostgreSQL error recovery, add unique keys for more tables
- config import uses upsert instead of truncate to preserve FK relations with production data
- restrict CORS defaults — empty origins (block all), GET/POST only, no preflight cache
- ownership check on production corrections, harden settings import
- update planned_start_at/planned_end_at on drag & drop to prevent WO disappearing on line change

## [0.11.1] - 2026-05-19

### Fixed
- Version file not updated for v0.11.0 release (caused "Update available" banner)
- Alerts page 500 error (Carbon diffForHumans() invalid argument)

## [0.11.0] - 2026-05-19

### Added
- Production planner with Gantt shift grid, weekly/daily/monthly views
- Drag & drop work order scheduling with shift-precise placement
- Real-time schedule updates with polling + WebSocket support
- Live order tracking panel + overdue visual on schedule planner
- Tabbed system settings (General, Production, Schedule, Security, Data)
- Overdue work order highlighting (red rows in admin/supervisor views)
- Download-based updater replacing exec git pull

### Fixed
- Security hardening: CRITICAL (C1-C3) + MEDIUM (M2-M6) vulnerabilities
- CORS admin settings, WorkOrder authorization
- CheckInstallation removed from global middleware
- Sidebar cleanup (removed duplicate Shifts link, Integrations link)

## [0.9.0] - 2026-05-14

### Added
- Full i18n system with language selector (EN/PL)
- 1178 translation keys covering all views
- Dashboard widget system (enable/disable/reorder from Settings)
- OEE overview section on dashboard with A/P/Q gauges
- Favicon from getopenmes.com
- Workstation view improvements (info button, report issues, stronger row colors)
- Optional marketing consent checkbox on registration

### Fixed
- Session expired (419) redirect to login instead of blank error
- Removed Microsoft Clarity tracking script
- Default to light mode (ignore system dark preference)

## [0.8.0] - 2026-05-12

### Added
- Onboarding wizard (4-step setup guide for first-time admins)
- Welcome popup on first admin login
- Help icon linking to onboarding wizard

## [0.7.0] - 2026-05-10

### Added
- Bill of Materials (BOM) module
- LOT tracking, workstation assignment, batch release workflow
- Operator production controls UI + supervisor dashboard
- Process confirmations, quality checks, packaging checklist

## [0.6.0] - 2026-05-07

### Added
- PIN login, workstation view, shifts, view templates
- Mobile API with Sanctum authentication
- OpenAPI documentation (Scramble)

## [0.5.0] - 2026-05-03

### Added
- User self-registration (Operator role, disabled by default)
- Multi-tenant registration (isolated Admin workspaces)
- Demo account expiry (3h countdown)
- Registration log

### Fixed
- Bug fixes, UX improvements, module support

## [0.4.1] - 2026-04-30

### Fixed
- Critical and high security vulnerabilities

## [0.4.0] - 2026-04-28

### Added
- MQTT machine connectivity module
- Barcode scanning (Packaging module)

## [0.3.0] - 2026-04-20

### Added
- Plug-and-play ZIP release with vendor and assets
- WordPress-style browser-based installation wizard
- Auto-setup on Docker Compose (migrations + seed + admin)
- MariaDB, MySQL, SQLite support alongside PostgreSQL
- Update check and apply (banner for Admin)

---

[Unreleased]: https://github.com/Mes-Open/OpenMes/compare/v0.15.5...develop
[0.15.5]: https://github.com/Mes-Open/OpenMes/compare/v0.15.4...v0.15.5
[0.15.4]: https://github.com/Mes-Open/OpenMes/compare/v0.15.3...v0.15.4
[0.15.3]: https://github.com/Mes-Open/OpenMes/compare/v0.15.2...v0.15.3
[0.15.2]: https://github.com/Mes-Open/OpenMes/compare/v0.15.1...v0.15.2
[0.15.1]: https://github.com/Mes-Open/OpenMes/compare/v0.15.0...v0.15.1
[0.15.0]: https://github.com/Mes-Open/OpenMes/compare/v0.14.5...v0.15.0
[0.14.5]: https://github.com/Mes-Open/OpenMes/compare/v0.14.4...v0.14.5
[0.14.4]: https://github.com/Mes-Open/OpenMes/compare/v0.14.3...v0.14.4
[0.14.3]: https://github.com/Mes-Open/OpenMes/compare/v0.14.2...v0.14.3
[0.14.2]: https://github.com/Mes-Open/OpenMes/compare/v0.14.1...v0.14.2
[0.14.1]: https://github.com/Mes-Open/OpenMes/compare/v0.14.0...v0.14.1
[0.14.0]: https://github.com/Mes-Open/OpenMes/compare/v0.13.0...v0.14.0
[0.11.1]: https://github.com/Mes-Open/OpenMes/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/Mes-Open/OpenMes/compare/v0.9.0...v0.11.0
[0.9.0]: https://github.com/Mes-Open/OpenMes/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/Mes-Open/OpenMes/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/Mes-Open/OpenMes/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/Mes-Open/OpenMes/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/Mes-Open/OpenMes/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/Mes-Open/OpenMes/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/Mes-Open/OpenMes/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Mes-Open/OpenMes/releases/tag/v0.3.0
