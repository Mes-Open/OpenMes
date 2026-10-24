/**
 * The one front-end definition of what the alert badge counts — the mirror of
 * AlertController::totalCount on the server:
 *   open issues (OPEN/ACKNOWLEDGED) + overdue work orders + blocked work orders.
 *
 * Shared by the sidebar badge (LiveAlertCount), its hover panel (LatestAlerts),
 * the Alerts page and the work orders `?overdue=1` filter, so all four agree with
 * each other and with the server's `nav.alertCount` fallback.
 */

/** Mirrors Issue::STATUS_OPEN / Issue::STATUS_ACKNOWLEDGED. */
export const OPEN_ISSUE_STATUSES = ['OPEN', 'ACKNOWLEDGED'];

/** Mirrors WorkOrder::TERMINAL_STATUSES. */
export const TERMINAL_WO_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

/**
 * A live order whose due date is before `today` (`YYYY-MM-DD` in the plant
 * timezone — use todayInTimezone()). Date-only, like the server's whereDate:
 * an order due today is not overdue until the plant's midnight.
 */
export function isOverdue(order, today) {
    return Boolean(order.due_date)
        && String(order.due_date).slice(0, 10) < today
        && !TERMINAL_WO_STATUSES.includes(order.status);
}

export function countAlerts({ issues = [], orders = [], today }) {
    const openIssues = issues.filter((i) => OPEN_ISSUE_STATUSES.includes(i.status)).length;
    const overdue = orders.filter((o) => isOverdue(o, today)).length;
    const blocked = orders.filter((o) => o.status === 'BLOCKED').length;
    return { openIssues, overdue, blocked, total: openIssues + overdue + blocked };
}
