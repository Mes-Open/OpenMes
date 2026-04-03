# Supervisor Guide

This guide is for supervisors and team leads who manage production lines and monitor work order execution.

---

## Table of Contents

- [Dashboard Overview](#dashboard-overview)
- [Managing Work Orders](#managing-work-orders)
  - [Accepting Orders](#accepting-orders)
  - [Pausing and Resuming Orders](#pausing-and-resuming-orders)
  - [Rejecting Orders](#rejecting-orders)
- [Monitoring Production Lines](#monitoring-production-lines)
- [Issue Management (Andon System)](#issue-management-andon-system)
- [Reports and Analytics](#reports-and-analytics)
- [Alerts](#alerts)

---

## Dashboard Overview

The Supervisor Dashboard is your real-time view of the shop floor. It shows:

- **Active lines** — which lines are currently running and their status
- **Work orders in progress** — orders currently being produced
- **Open issues** — problems reported by operators awaiting attention
- **KPI metrics** — throughput, on-time delivery, issue rates
- **Charts** — production volume, cycle time trends, issue breakdown

The dashboard auto-refreshes every 30 seconds. You can also manually refresh with the button in the top-right corner.

---

## Managing Work Orders

### Accepting Orders

New work orders created by admins start with status **Pending**. They must be accepted before operators can work on them.

1. Go to **Work Orders** in the sidebar
2. Filter by status: **Pending**
3. Review the order details (product, quantity, line, due date)
4. Click **Accept** to approve the order for production

The order status changes to **Accepted** and it becomes visible in the operator queue.

### Pausing and Resuming Orders

If production needs to stop temporarily (material shortage, quality check, etc.):

1. Open the work order
2. Click **Pause**
3. Enter an optional reason
4. The order status changes to **Paused**

To resume:
1. Open the paused order
2. Click **Resume**
3. The order returns to **In Progress**

### Rejecting Orders

To reject an order that should not be produced:

1. Open the work order
2. Click **Reject**
3. Enter a reason (required)
4. The order is removed from the production queue

> **Note:** Rejected orders cannot be reactivated. Create a new order if needed.

---

## Monitoring Production Lines

From the dashboard you can see each line's current status:

| Status | Meaning |
|---|---|
| Running | Normal production |
| Changeover | Setting up for new product |
| Breakdown | Machine failure |
| Planned stop | Scheduled downtime |
| Unplanned stop | Unexpected stoppage |

Clicking on a line opens a detailed view showing:
- All work orders on that line
- Active batches and their step progress
- Any open issues
- Line history for the current shift

---

## Issue Management (Andon System)

Issues (problems reported by operators) are your primary alert mechanism.

### Issue Lifecycle

```
Open → Acknowledged → Resolved → Closed
```

### Handling Issues

1. Go to **Issues** in the sidebar (or click the notification badge)
2. Review open issues — they show: type, description, reporter, line, and time
3. Click **Acknowledge** when you are aware and working on it
4. Once resolved, click **Resolve** and add resolution notes
5. Click **Close** to archive the issue

### Issue Severity

- **Critical issues** automatically block the affected work order. The order cannot progress until the issue is resolved
- **Non-critical issues** are logged but do not block production

---

## Reports and Analytics

Go to **Reports** in the sidebar to access:

### Production Summary
- Total units produced per period
- Planned vs. actual quantities
- On-time delivery rate

### Batch Completion Report
- Which batches completed, when, and by whom
- Cycle times per batch
- Multi-batch orders overview

### Downtime Report
- Time per line in each status category
- Breakdown of stop reasons
- Issues causing downtime

### Exporting
All reports can be exported as CSV. Click the **Export CSV** button on any report page.

---

## Alerts

The bell icon in the top navigation shows the total count of active alerts.

Alerts are generated when:
- A critical issue is reported
- A work order misses its due date
- A line has been stopped for an extended period
- Production falls significantly behind plan

Go to **Alerts** to see all active alerts with timestamps and affected orders.
