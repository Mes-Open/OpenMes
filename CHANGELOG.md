# Changelog

All notable changes to OpenMES are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/).

---

## [Unreleased]

### Added
- **Pallets**: new shippable-unit entity. Each pallet gets a unique `pallet_no` drawn from a dedicated Postgres sequence (format `PAL-000001`), and tracks `work_order_id`, `qty`, `status` (open / closed / shipped), `location` and `erp_reference`. Full admin CRUD under **Packaging → Pallets** (`/admin/pallets`), live-synced list.
- **Pallet labels**: new `pallet` label-template type wired into the existing template/label system. Prints QR + 1D barcode (both encoding the pallet number) plus pallet no., product, quantity and location, as PDF or ZPL. A default *Standard Pallet* template (100×100 mm) is seeded.
- **Packing-station create-pallet action**: operators can open a pallet for a work order at the scanning station; subsequent scans of that order's pieces are assigned to the open pallet (incrementing its `qty`), with a close action and one-click label print. A scan whose EAN belongs to a different work order than the open pallet is rejected.
- **Resume open pallets across shifts**: open pallets persist with their running `qty`, so a pallet started on one shift can be continued on the next. The scanning station now shows a list of all open pallets grouped by production line (derived from each pallet's work order, filterable via `GET /packaging/pallets?line_id=`), each with a **Resume** action that makes it the active pallet so further scans keep filling it instead of opening a new one.
- **Friendly in-app error page**: in production, error statuses (500/503/404/403/429) now render an Inertia `Error` page that keeps the user's chrome (sidebar) — admins/supervisors get the admin sidebar, operators their touch layout — so they can navigate away instead of landing on a bare error screen. API/JSON clients keep their normal JSON error; local/testing keep the debug page.
- **Sidebar menu search**: a search box at the top of the sidebar filters all navigation entries (including items nested in groups/subgroups) and shows a flat result list with the group path; matches both English labels and the active locale's translations. Enter opens the first result, Escape clears; on a collapsed sidebar the search icon expands it and focuses the input.
- **Shift handover screen** (Supervisor): per-line balance reconciling **produced** (operator shift entries) − **scrap** = **good**, vs **packed** (station scans), vs **WIP** (open pallets' qty + still-unpacked good output), vs **shipped** (dispatched pallets), all scoped to the active shift window. Flags discrepancies (unpacked output, awaiting shipment, over-packed). Requires supervisor confirmation to **close the shift**, which writes an immutable audit snapshot (figures + who/when + open-pallet breakdown). New `shift_handovers` table; audit history on the same screen.

### Changed
- **Packing station now follows the configured Shifts**: the station's "this shift" window (packed-count / history / stats) is derived from `Shift::current()` (admin → **Shifts**) instead of a hard-coded 06:00–18:00 / 18:00–06:00 split — including overnight shifts — falling back to the fixed split only when no shift is configured. The station header shows the active shift's name and window.

### Fixed
- Creating/updating a maintenance schedule with an empty **Lead time (days)** crashed with a NOT NULL violation (23502): the field is validated as nullable but the column is `NOT NULL default 0`, and submitting `null` overrode the default. The controller now falls back to `0` ("generate on the due date"). Regression test added. (also shipped as v0.14.5)

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

[Unreleased]: https://github.com/Mes-Open/OpenMes/compare/v0.14.4...develop
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
