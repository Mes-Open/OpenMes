# Operator Training Track

Training outline for production floor operators using OpenMES on workstations or tablets.

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

1. Log in and select the correct production line
2. Read and navigate the work queue (status, priority, progress)
3. Start a batch, complete process steps in order, and finish a run
4. Report an issue (Andon) with the right type and description
5. Update line status (running, changeover, breakdown, stop)
6. Install and use OpenMES as a PWA on a tablet, including basic offline behaviour

---

## Prerequisites

- Operator user account assigned to at least one active line
- At least one **Accepted** or **In Progress** work order on that line (trainer prepares this)
- Optional: tablet for the PWA section

---

## Session Outline

### 1. Orientation (10–15 min)

- What OpenMES is used for on the shop floor
- Roles: operator vs supervisor vs admin (operators do not change system config)
- Where to get the site URL and who resets passwords

### 2. Login and line selection (10 min)

- Open the OpenMES URL and log in
- Choose the assigned line from the line selection screen
- What to do if the line is missing (contact supervisor)

**Materials:** [Operator Guide → Logging In](../operator-guide.md#logging-in), [Selecting Your Line](../operator-guide.md#selecting-your-line)

### 3. Work queue (15 min)

- Read order number, product, quantity, due date, status, progress
- Sort/priority expectations for the shift
- Open an order detail page

**Materials:** [Your Work Queue](../operator-guide.md#your-work-queue), screenshot [operator-queue.png](../screenshots/operator-queue.png)

### 4. Daily production workflow (30–40 min)

- Start a batch and enter planned quantity
- Complete steps in sequence (unless sequential enforcement is off)
- Add optional step comments
- Confirm batch completion and quantity update

**Materials:** [Working on a Production Order](../operator-guide.md#working-on-a-production-order), [operator-workstation.png](../screenshots/operator-workstation.png)

### 5. Problems and line status (20 min)

- Report issue: type, description, submit
- Understand critical issues may block the order
- Set line status accurately during the shift

**Materials:** [Reporting a Problem](../operator-guide.md#reporting-a-problem), [Line Status Updates](../operator-guide.md#line-status-updates)

### 6. Tablets and PWA (15–20 min, optional)

- Install on iPad or Android
- Offline banner, queued actions, sync on reconnect
- Shop-floor tips (touch targets, landscape, session timeout)

**Materials:** [Using OpenMES on a Tablet (PWA)](../operator-guide.md#using-openmes-on-a-tablet-pwa), [Tips for the Shop Floor](../operator-guide.md#tips-for-the-shop-floor)

---

## Reference Materials

| Resource | Use |
|---|---|
| [Operator Guide](../operator-guide.md) | Primary how-to for all shop-floor tasks |
| [Supervisor Guide](../supervisor-guide.md) | Context only — how issues and accept/pause look upstream |
| [Screenshots](../screenshots/) | Visual reference for queue and workstation screens |
| Site URL / line list | Provided by the plant administrator |

---

## Hands-on Exercises

1. **Queue drill** — Log in, select line, identify the highest-priority open order and open it.
2. **Full batch** — Start a batch for a small quantity, complete every step, confirm the order progress updates.
3. **Issue report** — Submit a non-critical test issue, then ask a supervisor to show it on their dashboard (or have the trainer resolve it).
4. **Status change** — Set the line to Changeover, then back to Running.
5. **PWA (optional)** — Install the app on a tablet and complete one step while noting online/offline indicators.

---

## Competency Checklist

Trainee: _________________  Date: _________  Trainer: _________________

| # | Skill | Pass |
|---|---|---|
| 1 | Logs in and selects the correct line | ☐ |
| 2 | Explains queue fields (order, qty, due, status, progress) | ☐ |
| 3 | Starts a batch with a sensible quantity | ☐ |
| 4 | Completes steps and finishes a batch | ☐ |
| 5 | Reports an issue with correct type and description | ☐ |
| 6 | Updates line status appropriately | ☐ |
| 7 | (Optional) Uses PWA install and understands offline sync | ☐ |

Notes / site-specific procedures:

_________________________________________________________________
_________________________________________________________________
