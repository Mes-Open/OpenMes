import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';

import { useHotShapes } from './LiveShapesProvider';
import { __, elapsed } from '../lib/i18n';

/**
 * The newest alerts, for the sidebar's hover panel — what the badge's number is
 * actually made of, without leaving the page you are on.
 *
 * Reads the SHARED `issues_open` / `work_orders_active` collections (the same
 * ones the badge counts), so hovering opens no connection and fetches nothing:
 * the rows are already in the browser. It mounts only while the panel is open,
 * so its live query doesn't run for readers who never hover.
 *
 * `/admin/alerts` is hardcoded because `alert: true` — the flag that puts this
 * panel on a nav entry — exists on exactly one entry, in the admin tree. Give
 * the supervisor nav an alerts entry and this needs the section's base path.
 */
const OPEN_STATUSES = ['OPEN', 'ACKNOWLEDGED'];
const TERMINAL_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

/** Enough to answer "what just happened?" — the page answers "everything". */
const PEEK_ROWS = 5;

export default function LatestAlerts() {
    const hot = useHotShapes();
    const { data: issues = [] } = useLiveQuery((q) => q.from({ r: hot.issuesOpen }));
    const { data: orders = [] } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));

    const { latest, total, overdue, blocked } = useMemo(() => {
        const orderById = new Map(orders.map((o) => [String(o.id), o]));
        const open = issues.filter((i) => OPEN_STATUSES.includes(i.status));
        const todayStr = new Date().toISOString().slice(0, 10);

        const overdueCount = orders.filter(
            (o) => o.due_date && String(o.due_date).slice(0, 10) < todayStr && !TERMINAL_STATUSES.includes(o.status),
        ).length;
        const blockedCount = orders.filter((o) => o.status === 'BLOCKED').length;

        return {
            // Mirrors LiveAlertCount / AlertController::totalCount.
            total: open.length + overdueCount + blockedCount,
            overdue: overdueCount,
            blocked: blockedCount,
            latest: [...open]
                .sort((a, b) =>
                    String(b.reported_at ?? b.created_at ?? '').localeCompare(
                        String(a.reported_at ?? a.created_at ?? ''),
                    ))
                .slice(0, PEEK_ROWS)
                .map((i) => ({ ...i, order: i.work_order_id != null ? orderById.get(String(i.work_order_id)) : null })),
        };
    }, [issues, orders]);

    return (
        <div className="w-[330px]">
            <div className="flex items-center gap-2.5 px-[14px] pt-3 pb-2.5">
                <span className="text-[13.5px] font-semibold text-om-ink">{__('Latest alerts')}</span>
                {/* The badge's own number, not the length of this list — a panel
                    opened from a "94" that then says "53" reads as a bug. */}
                {total > 0 && (
                    <span className="rounded-[20px] bg-om-blocked-bg px-[8px] py-[1px] font-mono text-[10.5px] font-semibold text-om-blocked">
                        {total}
                    </span>
                )}
            </div>

            {latest.length === 0 ? (
                <p className="border-t border-om-line2 px-[14px] py-5 text-center text-[12.5px] text-om-faint">
                    {__('No open issues.')}
                </p>
            ) : (
                <ul className="border-t border-om-line2">
                    {latest.map((issue) => (
                        <li key={issue.id}>
                            <Link
                                href="/admin/alerts"
                                className="flex items-start gap-2.5 border-b border-om-line2 px-[14px] py-2.5 transition-colors last:border-b-0 hover:bg-om-chip"
                            >
                                <span
                                    aria-hidden="true"
                                    className={`mt-[6px] size-[7px] shrink-0 rounded-full ${
                                        issue.status === 'OPEN' ? 'animate-om-pulse bg-om-blocked' : 'bg-om-faintest'
                                    }`}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[12.5px] font-medium text-om-ink">
                                        {issue.title ?? __('Issue')}
                                    </span>
                                    <span className="mt-[1px] block truncate font-mono text-[10.5px] text-om-faint">
                                        {elapsed(issue.reported_at ?? issue.created_at)}
                                        {issue.order && ` · ${issue.order.order_no}`}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {/* The other two things the badge counts. They are work orders, not
                issues, so they'd be invisible in a list of the latest reports —
                and an overdue order is what the number is often mostly made of. */}
            <div className="flex items-center justify-between gap-3 border-t border-om-line2 bg-om-panel px-[14px] py-2.5">
                <span className="truncate font-mono text-[10.5px] whitespace-nowrap text-om-faint">
                    {[
                        overdue > 0 && __(':count overdue', { count: overdue }),
                        blocked > 0 && __(':count blocked', { count: blocked }),
                    ].filter(Boolean).join(' · ')}
                </span>
                <Link href="/admin/alerts" className="shrink-0 text-[11.5px] font-semibold text-om-accent hover:underline">
                    {__('See all →')}
                </Link>
            </div>
        </div>
    );
}
