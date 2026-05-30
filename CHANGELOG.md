# Changelog

All notable changes to OpenMES are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/).

---

## [Unreleased] (develop)

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

[Unreleased]: https://github.com/Mes-Open/OpenMes/compare/v0.13.0...develop
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
