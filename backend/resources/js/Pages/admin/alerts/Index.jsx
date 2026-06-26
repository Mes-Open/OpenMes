import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import AppLayout from '../../../layouts/AppLayout';
import { useSyncedShape } from '../../../lib/useSyncedShape';
import { realtimeCollection } from '../../../lib/realtimeCollection';
import { formatDate } from '../../../lib/i18n';
import { DataTable } from '@openmes/ui/table';

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
    const { data: types = [] } = useSyncedShape('issue_types_all');
    const { data: lines = [] } = useSyncedShape('lines_all');
    const { data: users = [] } = useSyncedShape('users');

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

    const issueColumns = useMemo(() => [
        {
            id: 'issue',
            accessorFn: (i) => i.title ?? i.description,
            header: __('Issue'),
            cell: ({ row }) => {
                const issue = row.original;
                return <span className="font-medium text-om-ink">{issue.title ?? issue.description}</span>;
            },
        },
        {
            id: 'work_order',
            accessorFn: (i) => i.order?.order_no ?? '',
            header: __('Work Order'),
            cell: ({ row }) => {
                const issue = row.original;
                return issue.order
                    ? <Link href={`/admin/work-orders/${issue.order.id}`} className="text-om-accent hover:underline font-mono text-xs">{issue.order.order_no}</Link>
                    : <span className="text-om-faint">—</span>;
            },
        },
        {
            id: 'type',
            accessorFn: (i) => i.type?.name ?? '',
            header: 'Type',
            cell: ({ row }) => <span className="text-xs text-om-muted">{row.original.type?.name ?? '—'}</span>,
        },
        {
            id: 'reported',
            accessorFn: (i) => i.created_at ?? '',
            header: 'Reported',
            cell: ({ row }) => <span className="text-xs text-om-muted">{timeAgo(row.original.created_at)}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => {
                const issue = row.original;
                return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${issue.status === 'OPEN' ? 'bg-om-downtime-bg text-om-downtime' : 'bg-om-chip text-om-accent'}`}>{issue.status}</span>;
            },
        },
    ], []);

    return (
        <div className="max-w-7xl mx-auto">
            <div className="flex items-center gap-3 mb-6">
                <h1 className="text-3xl font-bold text-om-ink">{__('Alerts')}</h1>
                {total > 0 && (
                    <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-om-blocked text-white text-sm font-bold">{total}</span>
                )}
                <span className="ml-auto flex items-center gap-1.5 text-xs text-om-faint">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-om-running opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-om-running" />
                    </span>
                    {__('Live')}
                </span>
            </div>

            {total === 0 ? (
                <div className="bg-om-card rounded-om-sm shadow-sm flex flex-col items-center py-16 text-center">
                    <svg className="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-xl font-semibold text-om-muted">{__('All clear')}</p>
                    <p className="text-om-muted mt-1">{__('No active alerts at this time.')}</p>
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
                                        {__('Blocking Issues')}
                                    </SectionTitle>
                                    <div className="space-y-3">
                                        {blockingIssues.map((issue) => <BlockingCard key={issue.id} issue={issue} />)}
                                    </div>
                                </>
                            ) : (
                                <EmptyCard text={__('No blocking issues')} />
                            )}
                        </div>

                        {/* RIGHT: work-order alerts */}
                        <div className="space-y-6">
                            {overdueOrders.length > 0 && (
                                <div>
                                    <SectionTitle color="text-orange-700" count={overdueOrders.length} badge="bg-om-downtime-bg text-orange-700"
                                        icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                                        {__('Overdue Work Orders')}
                                    </SectionTitle>
                                    <OrderTable rows={overdueOrders} accent="orange" showStatus showDue />
                                </div>
                            )}
                            {blockedOrders.length > 0 && (
                                <div>
                                    <SectionTitle color="text-om-downtime" count={blockedOrders.length} badge="bg-om-downtime-bg text-om-downtime"
                                        icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                        {__('Blocked Work Orders')}
                                    </SectionTitle>
                                    <OrderTable rows={blockedOrders} accent="yellow" showBlockedSince />
                                </div>
                            )}
                            {overdueOrders.length === 0 && blockedOrders.length === 0 && <EmptyCard text={__('No work order alerts')} />}
                        </div>
                    </div>

                    {/* Non-blocking issues */}
                    {nonBlockingIssues.length > 0 && (
                        <div className="mt-6">
                            <SectionTitle color="text-om-downtime" plain
                                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                {__('Open Issues')} ({nonBlockingIssues.length})
                            </SectionTitle>
                            <DataTable
                                data={nonBlockingIssues}
                                columns={issueColumns}
                                searchable
                                columnToggle
                                paginated
                                searchPlaceholder="Search issues..."
                            />
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
                        <span className="font-semibold text-om-blocked">{issue.type?.name ?? __('Issue')}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${issue.status === 'OPEN' ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-downtime-bg text-om-downtime'}`}>{issue.status}</span>
                    </div>
                    {issue.description && <p className="text-sm text-om-muted mt-1">{issue.description}</p>}
                    <div className="flex items-center gap-4 mt-2 text-xs text-om-muted flex-wrap">
                        {issue.order && (
                            <span>Work Order: <Link href={`/admin/work-orders/${issue.order.id}`} className="font-mono font-semibold text-om-accent hover:underline">{issue.order.order_no}</Link></span>
                        )}
                        <span>{__('Reported by')}: {issue.reporter?.name ?? '—'}</span>
                        <span>{timeAgo(issue.created_at)}</span>
                    </div>
                </div>
                <Link href="/admin/issues" className="shrink-0 text-xs text-om-blocked hover:underline font-medium">View issues →</Link>
            </div>
        </div>
    );
}

function OrderTable({ rows, showStatus, showDue, showBlockedSince }) {
    const columns = useMemo(() => {
        const cols = [
            {
                id: 'order',
                accessorKey: 'order_no',
                header: __('Order'),
                cell: ({ row }) => {
                    const wo = row.original;
                    return (
                        <>
                            <span className="font-mono text-sm font-semibold text-om-accent">{wo.order_no}</span>
                            {showDue && <div className="text-[10px] text-orange-700 font-medium">{fmtDate(wo.due_date)}</div>}
                        </>
                    );
                },
            },
            {
                id: 'line',
                accessorFn: (wo) => wo.line?.name ?? '',
                header: __('Line'),
                cell: ({ row }) => <span className="text-sm text-om-muted">{row.original.line?.name ?? '—'}</span>,
            },
        ];
        if (showDue) {
            cols.push({
                id: 'overdue',
                accessorFn: (wo) => wo.due_date ?? '',
                header: __('Overdue'),
                cell: ({ row }) => <span className="text-sm text-om-blocked font-semibold">{timeAgo(row.original.due_date)}</span>,
            });
        }
        if (showStatus) {
            cols.push({
                id: 'status',
                accessorKey: 'status',
                header: __('Status'),
                cell: ({ row }) => {
                    const wo = row.original;
                    return <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${WO_STATUS_STYLES[wo.status] ?? 'bg-om-chip text-om-muted'}`}>{WO_STATUS_LABELS[wo.status] ?? wo.status}</span>;
                },
            });
        }
        if (showBlockedSince) {
            cols.push({
                id: 'blocked_since',
                accessorFn: (wo) => wo.updated_at ?? '',
                header: __('Blocked since'),
                cell: ({ row }) => <span className="text-sm text-om-muted">{timeAgo(row.original.updated_at)}</span>,
            });
        }
        return cols;
    }, [showStatus, showDue, showBlockedSince]);

    return (
        <DataTable
            data={rows}
            columns={columns}
            searchable={false}
            columnToggle={false}
            paginated={false}
            onRowClick={(wo) => { window.location = `/admin/work-orders/${wo.id}`; }}
        />
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
