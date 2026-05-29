import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import AppLayout from '../../../layouts/AppLayout';
import { useShapeConfigs } from '../../../lib/useShapeConfigs';
import { electricCollection } from '../../../lib/electricCollection';

/**
 * Admin Alerts — fully live via Electric. Subscribes to issues, issue_types,
 * work_orders, lines and users shapes and derives the four alert lists
 * client-side, so blocking/overdue/blocked state changes appear WITHOUT a
 * refresh (the old Blade page only polled a badge and required a reload).
 */
const SHAPES = ['issues_all', 'issue_types_all', 'work_orders_all', 'lines_all', 'users'];
const OPEN_STATUSES = ['OPEN', 'ACKNOWLEDGED'];
const TERMINAL_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

const WO_STATUS_STYLES = {
    PENDING: 'bg-gray-100 text-gray-700', ACCEPTED: 'bg-blue-100 text-blue-700',
    IN_PROGRESS: 'bg-yellow-100 text-yellow-700', BLOCKED: 'bg-red-100 text-red-700',
    PAUSED: 'bg-orange-100 text-orange-700', DONE: 'bg-green-100 text-green-700',
    REJECTED: 'bg-red-200 text-red-800', CANCELLED: 'bg-gray-200 text-gray-600',
};
const WO_STATUS_LABELS = {
    PENDING: 'Pending', ACCEPTED: 'Accepted', IN_PROGRESS: 'In Progress', BLOCKED: 'Blocked',
    PAUSED: 'Paused', DONE: 'Done', REJECTED: 'Rejected', CANCELLED: 'Cancelled',
};

export default function AlertsIndex() {
    const { configs, error } = useShapeConfigs(SHAPES);

    return (
        <>
            <Head title="Alerts" />
            {error ? (
                <pre className="bg-red-50 text-red-800 p-3 rounded text-xs">{String(error)}</pre>
            ) : !configs ? (
                <p className="text-gray-500 text-sm">Connecting to live sync…</p>
            ) : (
                <AlertsLive configs={configs} />
            )}
        </>
    );
}

AlertsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

function AlertsLive({ configs }) {
    const issuesC = useMemo(() => electricCollection('issues_all', configs.issues_all, (r) => r.id), [configs]);
    const typesC = useMemo(() => electricCollection('issue_types_all', configs.issue_types_all, (r) => r.id), [configs]);
    const ordersC = useMemo(() => electricCollection('work_orders_all', configs.work_orders_all, (r) => r.id), [configs]);
    const linesC = useMemo(() => electricCollection('lines_all', configs.lines_all, (r) => r.id), [configs]);
    const usersC = useMemo(() => electricCollection('users', configs.users, (r) => r.id), [configs]);

    const { data: issues = [] } = useLiveQuery((q) => q.from({ r: issuesC }));
    const { data: types = [] } = useLiveQuery((q) => q.from({ r: typesC }));
    const { data: orders = [] } = useLiveQuery((q) => q.from({ r: ordersC }));
    const { data: lines = [] } = useLiveQuery((q) => q.from({ r: linesC }));
    const { data: users = [] } = useLiveQuery((q) => q.from({ r: usersC }));

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
                <h1 className="text-3xl font-bold text-gray-800">Alerts</h1>
                {total > 0 && (
                    <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-600 text-white text-sm font-bold">{total}</span>
                )}
                <span className="ml-auto flex items-center gap-1.5 text-xs text-gray-400">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500" />
                    </span>
                    Live
                </span>
            </div>

            {total === 0 ? (
                <div className="bg-white rounded-lg shadow-sm flex flex-col items-center py-16 text-center">
                    <svg className="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-xl font-semibold text-gray-700">All clear</p>
                    <p className="text-gray-500 mt-1">No active alerts at this time.</p>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* LEFT: Blocking issues */}
                        <div>
                            {blockingIssues.length > 0 ? (
                                <>
                                    <SectionTitle color="text-red-700" count={blockingIssues.length} badge="bg-red-100 text-red-700"
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
                                    <SectionTitle color="text-orange-700" count={overdueOrders.length} badge="bg-orange-100 text-orange-700"
                                        icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                                        Overdue Work Orders
                                    </SectionTitle>
                                    <OrderTable rows={overdueOrders} accent="orange" showStatus showDue />
                                </div>
                            )}
                            {blockedOrders.length > 0 && (
                                <div>
                                    <SectionTitle color="text-yellow-700" count={blockedOrders.length} badge="bg-yellow-100 text-yellow-700"
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
                            <SectionTitle color="text-amber-700" plain
                                icon="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                Open Issues ({nonBlockingIssues.length})
                            </SectionTitle>
                            <div className="bg-white rounded-lg shadow-sm overflow-hidden p-0">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-amber-50">
                                        <tr>
                                            {['Issue', 'Work Order', 'Type', 'Reported', 'Status'].map((h) => (
                                                <th key={h} className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {nonBlockingIssues.map((issue) => (
                                            <tr key={issue.id} className="hover:bg-amber-50/50">
                                                <td className="px-4 py-3"><span className="font-medium text-gray-800">{issue.title ?? issue.description}</span></td>
                                                <td className="px-4 py-3">{issue.order
                                                    ? <Link href={`/admin/work-orders/${issue.order.id}`} className="text-blue-600 hover:underline font-mono text-xs">{issue.order.order_no}</Link>
                                                    : <span className="text-gray-400">—</span>}</td>
                                                <td className="px-4 py-3 text-xs text-gray-600">{issue.type?.name ?? '—'}</td>
                                                <td className="px-4 py-3 text-xs text-gray-500">{timeAgo(issue.created_at)}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${issue.status === 'OPEN' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'}`}>{issue.status}</span>
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

function BlockingCard({ issue }) {
    return (
        <div className="bg-red-50 rounded-lg shadow-sm border-l-4 border-red-500 p-5">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-red-800">{issue.type?.name ?? 'Issue'}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${issue.status === 'OPEN' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800'}`}>{issue.status}</span>
                    </div>
                    {issue.description && <p className="text-sm text-gray-600 mt-1">{issue.description}</p>}
                    <div className="flex items-center gap-4 mt-2 text-xs text-gray-500 flex-wrap">
                        {issue.order && (
                            <span>Work Order: <Link href={`/admin/work-orders/${issue.order.id}`} className="font-mono font-semibold text-blue-700 hover:underline">{issue.order.order_no}</Link></span>
                        )}
                        <span>Reported by: {issue.reporter?.name ?? '—'}</span>
                        <span>{timeAgo(issue.created_at)}</span>
                    </div>
                </div>
                <Link href="/admin/issues" className="shrink-0 text-xs text-red-700 hover:underline font-medium">View issues →</Link>
            </div>
        </div>
    );
}

function OrderTable({ rows, accent, showStatus, showDue, showBlockedSince }) {
    const head = accent === 'orange' ? 'bg-orange-50' : 'bg-yellow-50';
    const border = accent === 'orange' ? 'border-orange-200' : 'border-yellow-200';
    const hover = accent === 'orange' ? 'hover:bg-orange-50' : 'hover:bg-yellow-50';
    return (
        <div className={`overflow-hidden rounded-lg border ${border} bg-white`}>
            <table className="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr className={head}>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Order</th>
                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Line</th>
                        {showDue && <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Overdue</th>}
                        {showStatus && <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>}
                        {showBlockedSince && <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Blocked since</th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {rows.map((wo) => (
                        <tr key={wo.id} className={`${hover} cursor-pointer`} onClick={() => { window.location = `/admin/work-orders/${wo.id}`; }}>
                            <td className="px-3 py-2">
                                <span className="font-mono text-sm font-semibold text-blue-700">{wo.order_no}</span>
                                {showDue && <div className="text-[10px] text-orange-700 font-medium">{fmtDate(wo.due_date)}</div>}
                            </td>
                            <td className="px-3 py-2 text-sm text-gray-600">{wo.line?.name ?? '—'}</td>
                            {showDue && <td className="px-3 py-2 text-sm text-red-600 font-semibold">{timeAgo(wo.due_date)}</td>}
                            {showStatus && (
                                <td className="px-3 py-2">
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${WO_STATUS_STYLES[wo.status] ?? 'bg-gray-100 text-gray-700'}`}>{WO_STATUS_LABELS[wo.status] ?? wo.status}</span>
                                </td>
                            )}
                            {showBlockedSince && <td className="px-3 py-2 text-sm text-gray-500">{timeAgo(wo.updated_at)}</td>}
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
        <div className="bg-white rounded-lg shadow-sm flex flex-col items-center py-10 text-center text-gray-500">
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
    return Number.isNaN(dt.getTime()) ? '' : dt.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
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
