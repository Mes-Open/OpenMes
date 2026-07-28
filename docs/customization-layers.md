# Customization Layers

This document classifies OpenMES into four layers so responsibilities and maintenance boundaries stay explicit: **standard functionality**, **configuration**, **customer-maintainable custom development**, and **vendor-supported custom development**.

Use it when deciding whether a change belongs in settings, a module, a fork of core, or a supported engagement — and who owns the result.

---

## Table of Contents

- [Why this split matters](#why-this-split-matters)
- [Quick classifier](#quick-classifier)
- [1. Standard functionality](#1-standard-functionality)
- [2. Configuration](#2-configuration)
- [3. Customer-maintainable custom development](#3-customer-maintainable-custom-development)
- [4. Vendor-supported custom development](#4-vendor-supported-custom-development)
- [Configuration vs custom code](#configuration-vs-custom-code)
- [Ownership and support boundaries](#ownership-and-support-boundaries)
- [Classifying a feature (worked examples)](#classifying-a-feature-worked-examples)
- [Related documentation](#related-documentation)

---

## Why this split matters

OpenMES is open source (AGPL-3.0) and designed to be extended without rewriting the core. Mixing “we toggled a setting,” “we installed a module,” and “we patched Laravel controllers” into one bucket makes upgrades, support, and handover painful.

The four layers below answer three practical questions for any capability:

1. **What is it?** (definition)
2. **Who maintains it?** (ownership)
3. **What support can you expect?** (boundary)

---

## Quick classifier

Ask these in order:

| Question | If yes → |
|---|---|
| Does it ship with OpenMES core and work after install without writing code? | **Standard functionality** |
| Can an admin achieve it only through UI / `.env` / data setup, with no new PHP/JS? | **Configuration** |
| Does it need code, but only via modules, hooks, API clients, or other extension points that leave core untouched? | **Customer-maintainable custom development** |
| Does it change core (or need deep schema work) **and** a commercial support / professional-services agreement owns upgrades? | **Vendor-supported custom development** |

If the work changes core **without** a support agreement, it is an **unsupported core fork** — not vendor-supported. Prefer a module (layer 3) or a contracted engagement (layer 4); see the callout under layer 4.

If two answers seem plausible, prefer the **least invasive** layer that still meets the requirement.

---

## 1. Standard functionality

### Definition

Capabilities that are part of the OpenMES product as released: production planning, work orders, operator flows, issues/andon, reporting, RBAC, audit logs, and other built-in features documented in the user guides and README. No site-specific code is required to use them.

### Examples

- Creating factories, lines, workstations, and process templates
- Scheduling work orders (planner / Gantt views)
- Operator queue, step completion, batch production
- Issue reporting and escalation
- Supervisor dashboards and CSV report export
- Built-in REST API endpoints and Sanctum tokens
- Optional **bundled** modules that ship with the project (enable/disable only)

### Who maintains it

- **Upstream OpenMES maintainers** (community releases) for bugs and product evolution
- Site admins apply updates; they do not own core feature design

### Support boundary

- Covered by project documentation, issues, and community channels for the released version
- Misconfiguration is not a product defect (see Configuration)
- Local patches to core are **not** standard functionality — they move the work into a custom-dev layer

---

## 2. Configuration

### Definition

Site-specific behaviour achieved by **settings, master data, environment variables, and enabling shipped modules** — without introducing new application code. Configuration changes survive upgrades as long as they stay in data / env / module enablement, not in edited core files.

### Examples

- System settings: production period, overproduction, sequential steps ([Admin Guide](admin-guide.md))
- Users, roles, permissions, shifts, lines, product types, process templates
- CSV/Excel import column-mapping profiles
- API tokens for ERP or automation clients
- MQTT / machine connectivity connection settings ([MQTT Connectivity](mqtt-connectivity.md))
- Enabling or disabling an installed module via **Admin → Modules**
- `.env` values for deployment (URL, database, `APP_DEBUG`, mail, etc.)

### Who maintains it

- **Customer / site administrators** (day-to-day)
- Integrators may set initial values during go-live; ongoing ownership stays with the site

### Support boundary

- Documented settings and procedures are in scope for self-service admin work
- Wrong master data or env values are operational issues, not “custom development”
- Editing files under `backend/app/` or rewriting migrations to “configure” something is **not** configuration — that is custom code

---

## 3. Customer-maintainable custom development

### Definition

Code or integrations the customer (or their integrator) builds and owns, using **supported extension points** so core OpenMES can still be upgraded from upstream. The preferred vehicle is a self-contained module under `modules/`, plus hooks/events, the public API, or external services that call OpenMES.

### Examples

- A plant-specific module (new screens, tables, Artisan commands) under `modules/MyPlant/`
- Event listeners on work-order / batch hooks ([HOOKS.md](../HOOKS.md))
- Sidebar entries and routes registered from a module service provider
- External scripts or middleware that use the REST API with a token
- A custom MQTT or REST gateway that posts into documented connectivity APIs
- Theme or Blade overrides **only** where the module system intentionally allows them

### Who maintains it

- **Customer engineering** (or a contractor they designate)
- Upstream does not own site-specific module code unless it is contributed and accepted into the project

### Support boundary

- Community support covers how extension points work (modules, hooks, API) — not debugging proprietary plant logic
- Customer is responsible for tests, backups, and re-testing modules after OpenMES upgrades
- If a module starts requiring core patches to function, refactor onto hooks/modules, or move the work under a vendor agreement (layer 4) — do not leave an unsupported core fork in place

Technical how-to: [Technical Documentation](development.md) (Module System) and [HOOKS.md](../HOOKS.md).

---

## 4. Vendor-supported custom development

### Definition

Custom work delivered and owned under a **commercial support / professional services** agreement, where the vendor takes upgrade and warranty responsibility. Typical scope includes core application changes, invasive schema work, or long-lived forks that cannot live solely in customer-owned modules.

**Vendor support is a support-status qualifier**, not a synonym for “we edited core.” Core edits without a contract are **not** this layer (see Unsupported core forks below).

Use this layer when the requirement crosses product boundaries, needs a guaranteed upgrade path, or the customer does not want to staff MES engineers.

### Examples

- Features that must ship in the main product line under a paid roadmap engagement
- Deep integration with vendor-owned adapters and release-aligned testing
- Hotfixes applied directly to core with an agreed merge-forward plan under contract
- SLA-backed support for a customized deployment (as offered by the project’s commercial contacts)

### Who maintains it

- **Vendor / professional services** under contract

### Support boundary

- Scope, SLA, and upgrade policy are defined by the commercial agreement — not by the AGPL community issue tracker alone
- Prefer contributing reusable pieces upstream or packaging them as modules when that meets the need without a permanent core fork

Contact for commercial / supported work is listed in project docs (e.g. `support@openmmes.com` in [Contributing](CONTRIBUTING.md) for security and related channels; use the project’s published support contacts for paid engagements).

### Unsupported core forks (not layer 4)

Unilateral edits to `backend/` (or a private fork of core) **without** a support agreement are still custom development, but they are **not** vendor-supported:

- The **site owns every upgrade conflict**
- Community channels do not absorb that risk
- For handover, label them explicitly as **unsupported core fork**, then either (a) rewrite onto modules/hooks (layer 3) or (b) put them under a commercial agreement (layer 4)

---

## Configuration vs custom code

| Aspect | Configuration | Custom development (customer or vendor) |
|---|---|---|
| Mechanism | UI settings, master data, `.env`, enable module | New PHP/JS, modules, listeners, API clients, core patches |
| Needs a developer? | Usually no | Yes |
| Upgrade impact | Low if you avoid editing core files | Modules: re-test; core patches: high merge cost |
| Typical owner | Site admin | Customer eng **or** vendor under contract |
| Example | Turn on `force_sequential_steps` | Module that posts to ERP on `WorkOrderCompleted` |

**Rule of thumb:** if you can do it in Admin / Settings / data import without opening a code editor, it is configuration. If you add or change executable code, it is custom development — then choose customer-maintainable (extension points) vs vendor-supported (core / contracted) using the sections above.

---

## Ownership and support boundaries

| Layer | Primary maintainer | Upgrade responsibility | Typical support channel |
|---|---|---|---|
| Standard functionality | Upstream project | Site applies releases; upstream fixes defects in core | Docs, GitHub issues, community |
| Configuration | Customer admins | Customer (backup data/env before update) | Docs + admin runbooks |
| Customer-maintainable custom dev | Customer (or their integrator) | Customer re-validates modules/integrations | Community for APIs/hooks; customer owns logic |
| Vendor-supported custom dev | Vendor under agreement | Vendor per contract | Commercial support / SOW |

### Handover checklist

When transferring a deployment between teams, list every non-standard item and tag it with one of the four layers:

1. Core version in use (standard)
2. Env and system settings that differ from defaults (configuration)
3. Installed modules and external API clients (customer-maintainable custom, unless vendor-owned)
4. Paid / SLA-backed custom deliverables (**vendor-supported**)
5. Any unilateral core diffs or private forks (**unsupported core fork** — not layer 4; plan rewrite or contract)

A reviewer should be able to pick any feature on the site and place it in exactly one primary layer (or mark it as an unsupported core fork) using this document.

---

## Classifying a feature (worked examples)

| Feature / change | Layer | Why |
|---|---|---|
| Operator completes a process step on a tablet | Standard | Built-in product behaviour |
| Require week numbers on all work orders | Configuration | System setting (`production period`) |
| Enable the Packaging module from Admin | Configuration | Enabling shipped/installed module; no new code |
| Module that emails purchasing on material shortage issues | Customer-maintainable custom | Module + hooks; core untouched |
| Script that creates work orders via the REST API | Customer-maintainable custom | External client on public API |
| Patch `WorkOrderController` in core for a one-off UI (no contract) | Unsupported core fork | Core change without vendor ownership; rewrite as a module or engage support |
| Paid delivery of a plant-wide WMS bridge with SLA | Vendor-supported | Commercial ownership and upgrade path |

---

## Related documentation

- [Admin Guide](admin-guide.md) — installation, settings, modules UI
- [Technical Documentation](development.md) — architecture and module development
- [HOOKS.md](../HOOKS.md) — events for extension without core edits
- [API Documentation](API_DOCUMENTATION.md) — integration without UI changes
- [Contributing](CONTRIBUTING.md) — how to propose changes upstream
