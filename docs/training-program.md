# Training Program

This program onboards three audiences onto OpenMES: **operators**, **administrators**, and **developers**. Each track defines learning objectives, a session outline, reference materials in this repo, and a competency checklist suitable for handover.

Use the tracks for instructor-led workshops, self-paced study, or site go-live preparation. Detailed how-to content lives in the linked role guides — these tracks are curricula, not replacements for those guides.

---

## Table of Contents

- [Tracks at a Glance](#tracks-at-a-glance)
- [How to Deliver Training](#how-to-deliver-training)
- [Shared Prerequisites](#shared-prerequisites)
- [Track Documents](#track-documents)
- [Handover Package](#handover-package)

---

## Tracks at a Glance

| Track | Audience | Focus | Primary materials |
|---|---|---|---|
| [Operator](training/operator.md) | Shop-floor operators | Daily line workflows, batches, issues, tablets | [Operator Guide](operator-guide.md) |
| [Administrator](training/administrator.md) | System admins & supervisors configuring the plant | Install, structure, users, products, orders, settings | [Admin Guide](admin-guide.md), [Supervisor Guide](supervisor-guide.md) |
| [Developer](training/developer.md) | Integrators & contributors | Architecture, modules, hooks, API | [Technical Documentation](development.md), [HOOKS.md](../HOOKS.md), [API Documentation](API_DOCUMENTATION.md) |

Suggested durations (adjust to plant complexity):

| Track | Self-paced | Workshop |
|---|---|---|
| Operator | 1–2 hours | Half day (with floor practice) |
| Administrator | 3–5 hours | 1 day |
| Developer | 4–8 hours | 1–2 days |

---

## How to Deliver Training

1. **Prepare an environment** — Docker Compose install (see [Admin Guide → Installation](admin-guide.md#installation)) or a staging site with sample data.
2. **Assign accounts** — create one trainee user per role; operators must be assigned to at least one line.
3. **Follow the track outline** — cover objectives in order; pause for hands-on exercises before advancing.
4. **Sign off with the checklist** — each track ends with a competency checklist for the trainer and trainee.

Supervisors who only monitor production (accept orders, Andon, reports) can take the **Operator** track for shop-floor context, then the **Administrator** sections on work orders and monitoring — or the full [Supervisor Guide](supervisor-guide.md) as a short add-on.

---

## Shared Prerequisites

- Access to an OpenMES URL (local Docker or staging)
- Browser (Chrome/Edge/Safari); tablets optional for the operator track
- For the developer track: Git, Docker, PHP 8.3 tooling as described in [development.md](development.md)

---

## Track Documents

- [Operator training track](training/operator.md) — shop-floor workflows
- [Administrator training track](training/administrator.md) — configuration and plant setup
- [Developer training track](training/developer.md) — extensibility and hooks

---

## Handover Package

When handing OpenMES to a new site team, include:

1. This training program and the three track documents
2. Role guides: [operator](operator-guide.md), [supervisor](supervisor-guide.md), [admin](admin-guide.md)
3. Technical refs: [development](development.md), [HOOKS.md](../HOOKS.md), [API](API_DOCUMENTATION.md), [machine connectivity](machine-connectivity.md) if signals are in scope
4. Completed competency checklists (one per trainee)
5. Site-specific notes: URL, line codes, product types, process templates, and who owns admin accounts
