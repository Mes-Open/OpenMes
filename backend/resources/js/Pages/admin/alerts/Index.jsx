import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import AppLayout from '../../../layouts/AppLayout';
import { useSyncedShape } from '../../../lib/useSyncedShape';
import { realtimeCollection } from '../../../lib/realtimeCollection';
import { formatDate } from '../../../lib/i18n';

/**
 * Admin Alerts — joins five collections (issues, work orders, and the issue
 * type / line / user lookups). Everything rides the single Reverb WebSocket, so
 * the alert lists update live as blocking/overdue/blocked state changes.
 */
const OPEN_STATUSES = ['OPEN', 'ACKNOWLEDGED'];
const TERMINAL_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

const WO_STATUS_STYLES = {
    PENDING: 'bg-om-chip text-om-muted', ACCEPTED: 'bg-om-chip text-om-accent',
    IN_PROGRESS: 'bg-om-downtime-bg text-om-downtime', BLOCKED: 'bg-om-blocked-bg text-om-blocked',
    PAUSED: 'bg-om-downtime-bg text-orange-700', DONE: 'bg-om-running-bg text-om-running',
    REJECTED: 'bg-om-blocked-bg text-om-blocked', CANCELLED: 'bg-om-line2 text-om-muted',
};
const WO_STATUS_LABELS = {
    PENDING: 'Pending', ACCEPTED: 'Accepted', IN_PROGRESS: 'In Progress', BLOCKED: 'Blocked',
    PAUSED: 'Paused', DONE: 'Done', REJECTED: 'Rejected', CANCELLED: 'Cancelled',
};

export default function AlertsIndex() {
    // Live: the transactional data the alerts derive from.
    const issuesC = useMemo(() => realtimeCollection('issues_all', (r) => r.id), []);
    const ordersC = useMemo(() => realtimeCollection('work_orders_all', (r) => r.id), []);
    const { data: issues = [] } = useLiveQuery((q) => q.from({ r: issuesC }));
    const { data: orders = [] } = useLiveQuery((q) => q.from({ r: ordersC }));

    // Lookup tables (names + is_blocking) — all live over the one Reverb socket.
    const { data: types } = useSyncedShape('issue_types_all');
    const { data: lines } = useSyncedShape('lines_all');
    const { data: users } = useSyncedShape('users');

    const derived = useMemo(() => {
        const typeById = new Map(types.map((t) => [String(t.id), t]));
        const orderById = new Map(orders.map((o) => [String(o.id), o]));
        const lineById = new Map(lines.map((l) => [String(l.id), l]));
        const userById = new Map(users.map((u) => [String(u.id), u]));

        const openIssues = issues
            .filter((i) => OPEN_STATUSES.includes(i.status))
            .map((i) => ({
                ...i,
                type: i.issue_type_id != null ? typeById.get(String(i.issue_type_id)) : null,
                order: i.work_order_id != null ? orderById.get(String(i.work_order_id)) : null,
                reporter: i.reported_by_id != null ? userById.get(String(i.reported_by_id)) : null,
            }))
            .sort((a, b) => String(b.created_at ?? '').localeCompare(String(a.created_at ?? '')));

        const blockingIssues = openIssues.filter((i) => i.type?.is_blocking === true || i.type?.is_blocking === 't');
        const nonBlockingIssues = openIssues.filter((i) => !(i.type?.is_blocking === true || i.type?.is_blocking === 't'));

        const todayStr = new Date().toISOString().slice(0, 10);
        const withLine = (o) => ({ ...o, line: o.line_id != null ? lineById.get(String(o.line_id)) : null });

        const overdueOrders = orders
            .filter((o) => o.due_date && String(o.due_date).slice(0, 10) < todayStr && !TERMINAL_STATUSES.includes(o.status))
            .map(withLine)
            .sort((a, b) => String(a.due_date).localeCompare(String(b.due_date)));

        const blockedOrders = orders
            .filter((o) => o.status === 'BLOCKED')
            .map(withLine)
            .sort((a, b) => String(b.updated_at ?? '').localeCompare(String(a.updated_at ?? '')));

        return { blockingIssues, nonBlockingIssues, overdueOrders, blockedOrders };
    }, [issues, types, orders, lines, users]);

    const { blockingIssues, nonBlockingIssues, overdueOrders, blockedOrders } = derived;
    const total = blockingIssues.length + nonBlockingIssues.length + overdueOrders.length + blockedOrders.length;

    return (
        <div className="max-w-7xl mx-auto">
            <div className="flex items-center gap-3 mb-6">
                <h1 className="text-3xl font-bold text-om-ink">Alerts</h1>
                {total > 0 && (
                    <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-om-blocked text-white text-sm font-bold">{total}</span>
                )}
                <span className="ml-auto flex items-center gap-1.5 text-xs text-om-faint">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-om-running opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-om-running" />
                    </span>
                    Live
                </span>
            </div>

            {total === 0 ? (
                <div className="bg-om-card rounded-om-sm shadow-sm flex flex-col items-center py-16 text-center">
                    <svg className="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-xl font-semibold text-om-muted">All clear</p>
                    <p className="text-om-muted mt-1">No active alerts at this time.</p>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* LEFT: Blocking issues */}
                        <div>
                            {blockingIssues.length > 0 ? (
                                <>
                                    <SectionTitle color="text-om-blocked" count={blockingIssues.length} badge="bg-om-blocked-bg text-om-blocked"
                                        icon="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                        Blocking Issues
                                    </SectionTitle>
                                    <div className="space-y-3">
                                        {blockingIssues.map((issue) => <BlockingCard key={issue.id} issue={issue} />)}
                                    </div>
                                </>
                            ) : (
                                <EmptyCard text="No blocking issues" />
                            )}
                        </div>

                        {/* RIGHT: work-order alerts */}
                        <div className="space-y-6">
                            {overdueOrders.length > 0 && (
                                <div>
                                    <SectionTitle color="text-orange-700" count={overdueOrders.length} badge="bg-om-downtime-bg text-orange-700"
                                        icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                                        Overdue Work Orders
                                    </SectionTitle>
                                    <OrderTable rows={overdueOrders} accent="orange" showStatus showDue />
                                </div>
                            )}
                            {blockedOrders.length > 0 && (
                                <div>
                                    <SectionTitle color="text-om-downtime" count={blockedOrders.length} badge="bg-om-downtime-bg text-om-downtime"
                                        icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                        Blocked Work Orders
                                    </SectionTitle>
                                    <OrderTable rows={blockedOrders} accent="yellow" showBlockedSince />
                                </div>
                            )}
                            {overdueOrders.length === 0 && blockedOrders.length === 0 && <EmptyCard text="No work order alerts" />}
                        </div>
                    </div>

                    {/* Non-blocking issues */}
                    {nonBlockingIssues.length > 0 && (
                        <div className="mt-6">
                            <SectionTitle color="text-om-downtime" plain
                                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                Open Issues ({nonBlockingIssues.length})
                            </SectionTitle>
                            <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden p-0">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-om-downtime-bg">
                                        <tr>
                                            {['Issue', 'Work Order', 'Type', 'Reported', 'Status'].map((h) => (
                                                <th key={h} className="px-4 py-2.5 text-left text-xs font-semibold text-om-muted uppercase">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-om-line2">
                                        {nonBlockingIssues.map((issue) => (
                                            <tr key={issue.id} className="hover:bg-om-downtime-bg/50">
                                                <td className="px-4 py-3"><span className="font-medium text-om-ink">{issue.title ?? issue.description}</span></td>
                                                <td className="px-4 py-3">{issue.order
                                                    ? <Link href={`/admin/work-orders/${issue.order.id}`} className="text-om-accent hover:underline font-mono text-xs">{issue.order.order_no}</Link>
                                                    : <span className="text-om-faint">—</span>}</td>
                                                <td className="px-4 py-3 text-xs text-om-muted">{issue.type?.name ?? '—'}</td>
                                                <td className="px-4 py-3 text-xs text-om-muted">{timeAgo(issue.created_at)}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${issue.status === 'OPEN' ? 'bg-om-downtime-bg text-om-downtime' : 'bg-om-chip text-om-accent'}`}>{issue.status}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

AlertsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

function BlockingCard({ issue }) {
    return (
        <div className="bg-om-blocked-bg rounded-om-sm shadow-sm border-l-4 border-om-blocked p-5">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-om-blocked">{issue.type?.name ?? 'Issue'}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${issue.status === 'OPEN' ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-downtime-bg text-om-downtime'}`}>{issue.status}</span>
                    </div>
                    {issue.description && <p className="text-sm text-om-muted mt-1">{issue.description}</p>}
                    <div className="flex items-center gap-4 mt-2 text-xs text-om-muted flex-wrap">
                        {issue.order && (
                            <span>Work Order: <Link href={`/admin/work-orders/${issue.order.id}`} className="font-mono font-semibold text-om-accent hover:underline">{issue.order.order_no}</Link></span>
                        )}
                        <span>Reported by: {issue.reporter?.name ?? '—'}</span>
                        <span>{timeAgo(issue.created_at)}</span>
                    </div>
                </div>
                <Link href="/admin/issues" className="shrink-0 text-xs text-om-blocked hover:underline font-medium">View issues →</Link>
            </div>
        </div>
    );
}

function OrderTable({ rows, accent, showStatus, showDue, showBlockedSince }) {
    const head = accent === 'orange' ? 'bg-orange-50' : 'bg-om-downtime-bg';
    const border = accent === 'orange' ? 'border-orange-200' : 'border-om-line';
    const hover = accent === 'orange' ? 'hover:bg-orange-50' : 'hover:bg-om-downtime-bg';
    return (
        <div className={`overflow-hidden rounded-om-sm border ${border} bg-om-card`}>
            <table className="min-w-full divide-y divide-om-line2">
                <thead>
                    <tr className={head}>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-om-muted">Order</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-om-muted">Line</th>
                        {showDue && <th className="px-3 py-2 text-left text-xs font-semibold text-om-muted">Overdue</th>}
                        {showStatus && <th className="px-3 py-2 text-left text-xs font-semibold text-om-muted">Status</th>}
                        {showBlockedSince && <th className="px-3 py-2 text-left text-xs font-semibold text-om-muted">Blocked since</th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-om-line2">
                    {rows.map((wo) => (
                        <tr key={wo.id} className={`${hover} cursor-pointer`} onClick={() => { window.location = `/admin/work-orders/${wo.id}`; }}>
                            <td className="px-3 py-2">
                                <span className="font-mono text-sm font-semibold text-om-accent">{wo.order_no}</span>
                                {showDue && <div className="text-[10px] text-orange-700 font-medium">{fmtDate(wo.due_date)}</div>}
                            </td>
                            <td className="px-3 py-2 text-sm text-om-muted">{wo.line?.name ?? '—'}</td>
                            {showDue && <td className="px-3 py-2 text-sm text-om-blocked font-semibold">{timeAgo(wo.due_date)}</td>}
                            {showStatus && (
                                <td className="px-3 py-2">
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${WO_STATUS_STYLES[wo.status] ?? 'bg-om-chip text-om-muted'}`}>{WO_STATUS_LABELS[wo.status] ?? wo.status}</span>
                                </td>
                            )}
                            {showBlockedSince && <td className="px-3 py-2 text-sm text-om-muted">{timeAgo(wo.updated_at)}</td>}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function SectionTitle({ children, color, icon, count, badge, plain }) {
    return (
        <h2 className={`flex items-center gap-2 text-lg font-bold ${color} mb-3`}>
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={icon} />
            </svg>
            {children}
            {!plain && <span className={`ml-1 ${badge} text-xs font-bold px-2 py-0.5 rounded-full`}>{count}</span>}
        </h2>
    );
}

function EmptyCard({ text }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm flex flex-col items-center py-10 text-center text-om-muted">
            <svg className="w-10 h-10 text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4" />
            </svg>
            <p className="text-sm">{text}</p>
        </div>
    );
}

function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    return Number.isNaN(dt.getTime()) ? '' : formatDate(dt, { day: '2-digit', month: 'short', year: 'numeric' });
}

function timeAgo(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return '';
    const sec = Math.round((Date.now() - dt.getTime()) / 1000);
    const abs = Math.abs(sec);
    const past = sec >= 0;
    const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]];
    for (const [name, s] of units) {
        if (abs >= s) {
            const n = Math.floor(abs / s);
            return past ? `${n} ${name}${n > 1 ? 's' : ''} ago` : `in ${n} ${name}${n > 1 ? 's' : ''}`;
        }
    }
    return past ? 'just now' : 'soon';
}
