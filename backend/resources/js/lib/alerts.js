import { plantDate } from './i18n';

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
 * an order due today is not overdue until the plant's midnight. The due date is
 * read with plantDate(), so a row from a live delta (UTC ISO) and one from the
 * snapshot (raw plant wall time) give the same answer.
 */
export function isOverdue(order, today, tz) {
    const due = plantDate(order.due_date, tz);
    return due !== null && due < today && !TERMINAL_WO_STATUSES.includes(order.status);
}

export function countAlerts({ issues = [], orders = [], today, tz }) {
    const openIssues = issues.filter((i) => OPEN_ISSUE_STATUSES.includes(i.status)).length;
    const overdue = orders.filter((o) => isOverdue(o, today, tz)).length;
    const blocked = orders.filter((o) => o.status === 'BLOCKED').length;
    return { openIssues, overdue, blocked, total: openIssues + overdue + blocked };
}
