# Operator Guide

This guide is for production floor operators using OpenMES on workstations or tablets.

---

## Table of Contents

- [Logging In](#logging-in)
- [Selecting Your Line](#selecting-your-line)
- [Your Work Queue](#your-work-queue)
- [Working on a Production Order](#working-on-a-production-order)
  - [Starting a Batch](#starting-a-batch)
  - [Completing a Step](#completing-a-step)
  - [Reporting a Problem](#reporting-a-problem)
- [Line Status Updates](#line-status-updates)
- [Using OpenMES on a Tablet (PWA)](#using-openmes-on-a-tablet-pwa)
- [Tips for the Shop Floor](#tips-for-the-shop-floor)

---

## Logging In

1. Open your browser and go to the OpenMES URL (your admin will provide this, e.g. `http://192.168.1.100`)
2. Enter your username and password
3. Click **Log in**

If you forgot your password, contact your supervisor or system administrator — there is no self-service password reset.

---

## Selecting Your Line

After logging in, you are taken to the **Line Selection** screen.

1. You will see a list of production lines you are assigned to
2. Tap or click on your line
3. You are now viewing the work queue for that line

> **Note:** You can only see lines you have been assigned to. If your line is missing, contact your supervisor.

---

## Your Work Queue

The work queue shows all **accepted** and **in-progress** production orders for your line, sorted by priority.

Each row shows:
- **Order number** — unique identifier
- **Product name** — what you are producing
- **Quantity** — how many units to produce
- **Due date** — when the order must be completed
- **Status** — current state of the order
- **Progress** — how many batches have been completed

Tap any order to open its detail page.

---

## Working on a Production Order

### Starting a Batch

A **batch** represents one production run for an order. Large orders may have multiple batches (partial completions).

1. Open an order from the queue
2. You will see the process steps defined for this product
3. Tap **Start Batch** to begin production
4. Enter the quantity you plan to produce in this run
5. Tap **Confirm**

The batch is now in progress. You will see each step you need to complete.

### Completing a Step

Steps must be completed in order (unless your admin has disabled sequential enforcement).

For each step:
1. Read the step name and any instructions displayed
2. Perform the operation
3. Tap **Complete Step** when done
4. Optionally add a comment or note

Once all steps are completed, the batch is marked **Done** and the work order quantity is updated automatically.

### Reporting a Problem

If something goes wrong during production, report it immediately:

1. On the work order detail page, tap **Report Issue**
2. Select the **issue type** from the list (e.g. Material shortage, Quality defect, Tool failure, Machine breakdown)
3. Add a description explaining the problem
4. Tap **Submit**

The issue is immediately visible to your supervisor. If the issue is marked critical, production on this order may be **automatically blocked** until the issue is resolved.

---

## Line Status Updates

You can update the status of your line from the work order screen:

- **Running** — line is producing normally
- **Changeover** — setting up for a new product
- **Breakdown** — machine failure or critical issue
- **Planned stop** — scheduled maintenance or break
- **Unplanned stop** — unexpected stoppage

Keeping line status accurate helps supervisors monitor the shop floor in real time.

---

## Using OpenMES on a Tablet (PWA)

OpenMES can be installed as a Progressive Web App (PWA) on tablets for a native app-like experience.

### Install on iPad
1. Open Safari and navigate to your OpenMES URL
2. Tap the **Share** button (box with arrow)
3. Scroll down and tap **Add to Home Screen**
4. Tap **Add** to confirm

### Install on Android tablet
1. Open Chrome and navigate to your OpenMES URL
2. Tap the menu icon (⋮)
3. Tap **Install app** or **Add to Home Screen**
4. Tap **Install**

### Offline Mode
If the network goes down, OpenMES will:
- Continue to display the last loaded data
- Queue any actions you take (step completions, issue reports)
- Automatically sync when the connection is restored

A banner will appear at the top when you are offline.

---

## Tips for the Shop Floor

- **Large buttons** — all touch targets are at least 48px for easy tapping with gloves
- **Landscape mode** — rotate your tablet to landscape for a wider view of the step list
- **Auto-refresh** — the work queue refreshes automatically; no need to reload manually
- **Session timeout** — for security, you will be logged out after a period of inactivity. Log in again when this happens
- **Quick line switch** — use the line selector at the top of the queue to switch between lines if you are assigned to multiple
