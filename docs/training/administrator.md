# Administrator Training Track

Training outline for system administrators (and configuration-focused supervisors) who install, configure, and maintain OpenMES for a plant.

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

1. Install or validate a Docker-based OpenMES deployment and complete first-run setup
2. Model the plant: factories, divisions, lines, workstations
3. Create users, assign roles, and map operators/supervisors to lines
4. Configure product types and process templates
5. Create and import work orders; explain the pending → accepted flow
6. Adjust system settings that affect the floor (production period, overproduction, sequential steps)
7. Enable or install modules and manage API tokens when integrations are needed
8. Perform basic maintenance awareness (audit logs, updates, sample data)

---

## Prerequisites

- Admin account (or ability to complete the installer and create one)
- Host with Docker and Docker Compose for a lab install, **or** a staging site already running
- Optional: sample CSV for order import practice

---

## Session Outline

### 1. Installation and first steps (30–45 min)

- Prerequisites (Docker, Git)
- `docker-compose up -d` and web installer (site, database, admin account)
- Production hardening notes (`APP_DEBUG`, HTTPS, protect `.env`)
- Post-install checklist: lines, users, assignments, products, templates, orders

**Materials:** [Admin Guide → Installation](../admin-guide.md#installation), [First Steps After Installation](../admin-guide.md#first-steps-after-installation)

### 2. Users and roles (20–30 min)

- Roles: Admin, Supervisor, Operator
- Create users; assign lines for Operator/Supervisor
- Password reset policy (no self-service — admin/supervisor handles it)

**Materials:** [User Management](../admin-guide.md#user-management)

### 3. Production structure (30–40 min)

- Factory → Division → Line → Workstation hierarchy
- Create an active line; add workstations
- Why operators only see assigned lines

**Materials:** [Production Structure](../admin-guide.md#production-structure)

### 4. Products and process templates (30–40 min)

- Product types as categories
- Process templates: ordered steps, optional required roles
- Link templates to product types for order creation

**Materials:** [Product Configuration](../admin-guide.md#product-configuration)

### 5. Work orders and supervisor handoff (30–40 min)

- Manual order creation (number, qty, line, type, template, due date, priority)
- CSV/Excel import: required fields, strategies, saved mapping profiles
- Pending orders must be **Accepted** by a supervisor before operators see them
- Pause / resume / reject awareness via supervisor workflows

**Materials:** [Work Orders](../admin-guide.md#work-orders), [Supervisor Guide → Managing Work Orders](../supervisor-guide.md#managing-work-orders)

### 6. System settings and modules (30–40 min)

- Production period, overproduction, sequential step enforcement
- Enable/disable modules; install from ZIP when applicable
- API tokens for integrations
- Shifts, HR basics, audit logs, updates overview

**Materials:** [System Settings](../admin-guide.md#system-settings), [Modules](../admin-guide.md#modules), [API Tokens](../admin-guide.md#api-tokens), [Audit Logs](../admin-guide.md#audit-logs), [Updates](../admin-guide.md#updates)

### 7. Day-two operations (20 min)

- Monitoring: supervisor dashboard, issues, reports (for admin awareness)
- Machine/MQTT connectivity only if the site uses signal capture — point to refs, do not deep-dive unless in scope

**Materials:** [Supervisor Guide](../supervisor-guide.md), optional [Machine Connectivity](../machine-connectivity.md), [MQTT Connectivity](../mqtt-connectivity.md)

---

## Reference Materials

| Resource | Use |
|---|---|
| [Admin Guide](../admin-guide.md) | Primary configuration reference |
| [Supervisor Guide](../supervisor-guide.md) | Order lifecycle and Andon after config |
| [Operator Guide](../operator-guide.md) | Validate that config works on the floor |
| [Training Program](../training-program.md) | Handover package checklist |
| Main [README](../../README.md) | Deployment and PWA overview |

---

## Hands-on Exercises

1. **Greenfield checklist** — From a clean (or reset) lab, complete post-install steps through one active line and two users (operator + supervisor).
2. **Template build** — Create a product type and a 3–5 step process template; assign the template to the type.
3. **Order path** — Create a pending order, accept it as supervisor, complete one batch as operator.
4. **Import** — Import a small CSV with insert-only strategy; confirm duplicates are skipped on re-import.
5. **Setting toggle** — Change sequential-steps or overproduction setting and demonstrate the floor effect to trainees.
6. **Module awareness** — List enabled modules and describe how to enable/disable one in a non-production lab.

---

## Competency Checklist

Trainee: _________________  Date: _________  Trainer: _________________

| # | Skill | Pass |
|---|---|---|
| 1 | Installs or verifies Docker deployment / installer completion | ☐ |
| 2 | Creates structure (at least one active line) | ☐ |
| 3 | Creates users and assigns roles and lines | ☐ |
| 4 | Configures product type + process template | ☐ |
| 5 | Creates or imports work orders correctly | ☐ |
| 6 | Explains pending → accepted → operator queue flow | ☐ |
| 7 | Locates and adjusts key system settings | ☐ |
| 8 | Describes module enablement and when to use API tokens | ☐ |

Site-specific configuration notes (URLs, naming conventions, ERP import profiles):

_________________________________________________________________
_________________________________________________________________
