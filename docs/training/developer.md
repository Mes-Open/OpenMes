# Developer Training Track

Training outline for developers and integrators who extend OpenMES (modules, hooks, APIs) or contribute to the core.

**Parent program:** [Training Program](../training-program.md)

---

## Table of Contents

- [Learning Objectives](#learning-objectives)
- [Prerequisites](#prerequisites)
- [Session Outline](#session-outline)
- [Reference Materials](#reference-materials)
- [Hands-on Exercises](#hands-on-exercises)
- [Competency Checklist](#competency-checklist)

---

## Learning Objectives

After this track, the trainee can:

1. Describe the tech stack and repository layout (Laravel backend, Blade/Alpine/Livewire UI, modules directory)
2. Run a local development environment and know where tests and formatting live
3. Explain RBAC roles and where operator / supervisor / admin surfaces live
4. Scaffold or outline a module (`module.json`, service provider, routes, migrations)
5. Register event listeners against the hook/event system without patching core controllers
6. Use the REST API documentation for integrations (auth, common resources)
7. Follow contribution conventions (branching, Conventional Commits, PR expectations)

---

## Prerequisites

- Comfortable with PHP and Git; Laravel familiarity recommended
- Docker (or a working local PHP/PostgreSQL setup per [development.md](../development.md))
- Read access to this repository; fork for contribution practice

---

## Session Outline

### 1. Architecture overview (30–40 min)

- Stack table: Laravel 12, PHP 8.3, PostgreSQL, Sanctum, Spatie Permission, Vite, Docker
- Repo map: `backend/`, `modules/`, `docs/`, compose files
- Request paths: web (Blade/Livewire) vs `api/v1`
- Tenant / role model at a high level

**Materials:** [Technical Documentation → Tech Stack](../development.md#tech-stack), [Repository Structure](../development.md#repository-structure), [Architecture Overview](../development.md#architecture-overview)

### 2. Local development setup (30–45 min)

- Clone, compose, installer or `.env` setup
- `composer install`, frontend assets, running the app
- Where feature/unit tests live; `php artisan test`
- Code style (`pint`) and [Contributing](../CONTRIBUTING.md) expectations

**Materials:** [Local Development Setup](../development.md#local-development-setup), [Testing](../development.md#testing), [Code Style](../development.md#code-style), [Contributing](../CONTRIBUTING.md)

### 3. Module system (45–60 min)

- Module directory layout and `module.json`
- Service provider registration and sidebar navigation
- Module migrations and extending core models safely
- Prefer modules over core forks for site-specific behaviour

**Materials:** [Module System](../development.md#module-system), [HOOKS.md → Creating a Module](../../HOOKS.md#creating-a-module)

### 4. Hooks and events (45–60 min)

- Laravel events as the extension point
- Register listeners in the module service provider
- Survey major hook groups: work orders, batches, batch steps, users, lines, process templates, CSV import
- Best practices: keep listeners focused; avoid breaking the transaction path

**Materials:** [Hook System](../development.md#hook-system), [HOOKS.md](../../HOOKS.md) (available hooks and examples)

### 5. API and integrations (30–45 min)

- Sanctum session vs token usage for API clients
- Navigate [API Documentation](../API_DOCUMENTATION.md) for common resources
- Admin-managed API tokens (see admin track) for external systems
- Optional: machine signal pipeline overview if building protocol adapters

**Materials:** [API Development](../development.md#api-development), [API Documentation](../API_DOCUMENTATION.md), optional [Machine Connectivity](../machine-connectivity.md)

### 6. Contribution workflow (15–20 min)

- Fork, branch from `main`, focused PRs
- Conventional Commits (`feat`, `fix`, `docs`, …)
- Tests and docs for user-visible behaviour
- Point contributors at open docs/code issues only after they can run the suite

**Materials:** [Contributing → Submitting Changes](../CONTRIBUTING.md#submitting-changes), [Commit Message Convention](../CONTRIBUTING.md#commit-message-convention)

---

## Reference Materials

| Resource | Use |
|---|---|
| [Technical Documentation](../development.md) | Architecture, modules, local setup |
| [HOOKS.md](../../HOOKS.md) | Event catalogue and module examples |
| [API Documentation](../API_DOCUMENTATION.md) | REST reference |
| [Contributing](../CONTRIBUTING.md) | Process and PR norms |
| [Admin Guide → Modules / API Tokens](../admin-guide.md#modules) | Runtime enablement operators of the platform use |
| [Operator](../operator-guide.md) / [Supervisor](../supervisor-guide.md) guides | Domain language for realistic extension scenarios |

---

## Hands-on Exercises

1. **Map the code** — From a work-order accept action on the UI, locate the controller/service and name one related event if present.
2. **Dev boot** — Bring up the stack locally and run a focused subset of tests successfully.
3. **Module skeleton** — Create (or sketch) a module with `module.json` and a service provider that registers one no-op listener.
4. **Hook listener** — Listen for a work-order or batch completion event and write to the log (lab only).
5. **API read** — With a token or session, call one documented read endpoint and show the JSON shape.
6. **Docs PR practice (optional)** — Open a docs-only branch fixing a typo to rehearse the contribution path.

---

## Competency Checklist

Trainee: _________________  Date: _________  Trainer: _________________

| # | Skill | Pass |
|---|---|---|
| 1 | Explains stack and where backend / modules / docs live | ☐ |
| 2 | Runs local (or Docker) development environment | ☐ |
| 3 | Describes module layout and registration | ☐ |
| 4 | Registers an event listener via hooks (no core fork) | ☐ |
| 5 | Finds and uses API docs for an integration scenario | ☐ |
| 6 | States contribution steps (branch, tests, Conventional Commits, PR) | ☐ |

Extension goals for this site (modules, ERP, signals):

_________________________________________________________________
_________________________________________________________________
