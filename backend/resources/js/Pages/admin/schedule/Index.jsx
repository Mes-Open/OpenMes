import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDate, formatNumber } from '../../../lib/i18n';

const STATUS_COLORS = {
    BLOCKED:     'bg-om-blocked-bg text-om-blocked',
    IN_PROGRESS: 'bg-om-chip text-om-accent',
    ACCEPTED:    'bg-om-running-bg text-om-running',
    PENDING:     'bg-om-chip text-om-muted',
    PAUSED:      'bg-om-downtime-bg text-om-downtime',
    DONE:        'bg-om-running-bg text-om-running',
    REJECTED:    'bg-om-blocked-bg text-om-blocked',
    CANCELLED:   'bg-om-line2 text-om-muted',
};
const STATUS_LABELS = {
    PENDING: 'Pending', ACCEPTED: 'Accepted', IN_PROGRESS: 'In Progress',
    BLOCKED: 'Blocked', PAUSED: 'Paused', DONE: 'Done',
    REJECTED: 'Rejected', CANCELLED: 'Cancelled',
};

function fmtWeek(isoDateStr) {
    const d = new Date(isoDateStr);
    const year = d.getFullYear();
    // ISO week
    const tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const dayNum = tmp.getUTCDay() || 7;
    tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
    const week = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
    return `${year}-W${String(week).padStart(2, '0')}`;
}

function parseWeekStart(weekParam) {
    if (!weekParam) return null;
    const [y, w] = weekParam.split('-W').map(Number);
    const jan4 = new Date(y, 0, 4);
    const day = jan4.getDay() || 7;
    const monday = new Date(jan4);
    monday.setDate(jan4.getDate() - (day - 1) + (w - 1) * 7);
    return monday;
}

export default function ScheduleIndex() {
    const { workOrders = [], byLine = {}, lines = [], days = [], weekStart, weekEnd, lineId, currentShift } = usePage().props;

    const isCurrentWeek = (() => {
        const now = new Date();
        const ws = weekStart ? new Date(weekStart) : null;
        const we = weekEnd ? new Date(weekEnd) : null;
        return ws && we && now >= ws && now <= we;
    })();

    const prevWeek = weekStart ? fmtWeek(new Date(new Date(weekStart).getTime() - 7 * 86400000)) : '';
    const nextWeek = weekStart ? fmtWeek(new Date(new Date(weekStart).getTime() + 7 * 86400000)) : '';

    const navigate = (params) => router.get('/admin/schedule/list', params, { preserveState: false });

    const weekStartFmt = weekStart ? formatDate(new Date(weekStart), { day: 'numeric', month: 'short' }) : '';
    const weekEndFmt = weekEnd ? formatDate(new Date(weekEnd), { day: 'numeric', month: 'short', year: 'numeric' }) : '';

    // Group workOrders by line for the by-line view
    const ordersByLine = {};
    workOrders.forEach(wo => {
        const key = wo.line_id ?? '__none__';
        if (!ordersByLine[key]) ordersByLine[key] = [];
        ordersByLine[key].push(wo);
    });

    return (
        <>
            <Head title="Production Schedule" />
            <div>
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">Production Schedule</h1>
                        <p className="text-sm text-om-muted mt-0.5">
                            Week {weekStartFmt} &ndash; {weekEndFmt}
                            {currentShift && (
                                <span className="text-om-running font-medium">
                                    &nbsp;&middot;&nbsp;Current shift: {currentShift.name} ({currentShift.start_time?.slice(0, 5)}&ndash;{currentShift.end_time?.slice(0, 5)})
                                </span>
                            )}
                        </p>
                    </div>

                    {/* Filters */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <button
                            onClick={() => navigate({ week: prevWeek, line_id: lineId || '' })}
                            className="btn-touch bg-om-chip text-om-muted hover:bg-om-line2 px-3 py-2 rounded-om-sm"
                            title="Previous week"
                        >
                            &larr;
                        </button>

                        <input
                            type="week"
                            value={weekStart ? fmtWeek(new Date(weekStart)) : ''}
                            onChange={(e) => navigate({ week: e.target.value, line_id: lineId || '' })}
                            className="form-input text-sm py-2 min-h-0"
                        />

                        <select
                            value={lineId || ''}
                            onChange={(e) => navigate({ week: weekStart ? fmtWeek(new Date(weekStart)) : '', line_id: e.target.value })}
                            className="form-input text-sm py-2 min-h-0"
                        >
                            <option value="">All Lines</option>
                            {lines.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>

                        <button
                            onClick={() => navigate({ week: nextWeek, line_id: lineId || '' })}
                            className="btn-touch bg-om-chip text-om-muted hover:bg-om-line2 px-3 py-2 rounded-om-sm"
                            title="Next week"
                        >
                            &rarr;
                        </button>

                        {!isCurrentWeek && (
                            <button
                                onClick={() => navigate({ line_id: lineId || '' })}
                                className="text-sm text-om-accent hover:underline"
                            >
                                Today
                            </button>
                        )}
                    </div>
                </div>

                {workOrders.length === 0 ? (
                    <div className="card flex flex-col items-center py-16 text-center bg-om-card rounded-om border border-om-line2 shadow-sm">
                        <svg className="w-14 h-14 text-om-faintest mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p className="text-om-muted">No work orders scheduled for this week.</p>
                        <Link href="/admin/work-orders/create" className="mt-4 btn-touch btn-primary px-4 py-2 bg-om-ink text-white rounded-om-sm hover:bg-black">
                            Create Work Order
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {!lineId ? (
                            <>
                                {lines.map((line) => {
                                    const lineOrders = ordersByLine[line.id] || [];
                                    if (lineOrders.length === 0) return null;
                                    return (
                                        <div key={line.id}>
                                            <h2 className="flex items-center gap-2 text-base font-bold text-om-muted mb-2">
                                                <span className="w-2 h-2 rounded-full bg-om-ink"></span>
                                                {line.name}
                                                <span className="text-xs font-normal text-om-faint">
                                                    ({lineOrders.length} {lineOrders.length === 1 ? 'order' : 'orders'})
                                                </span>
                                            </h2>
                                            <OrderTable orders={lineOrders} />
                                        </div>
                                    );
                                })}
                                {ordersByLine['__none__'] && ordersByLine['__none__'].length > 0 && (
                                    <div>
                                        <h2 className="flex items-center gap-2 text-base font-bold text-om-muted mb-2">
                                            <span className="w-2 h-2 rounded-full bg-om-faintest"></span>
                                            No Line Assigned
                                        </h2>
                                        <OrderTable orders={ordersByLine['__none__']} />
                                    </div>
                                )}
                            </>
                        ) : (
                            <OrderTable orders={workOrders} />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function OrderTable({ orders }) {
    return (
        <div className="overflow-hidden rounded-om-sm border border-om-line2 bg-om-card mb-1">
            <table className="min-w-full divide-y divide-om-line2">
                <thead>
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted">Order</th>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted">Product</th>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted hidden sm:table-cell">Due</th>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted hidden md:table-cell">Qty</th>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-semibold text-om-muted hidden lg:table-cell">Priority</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-om-line2">
                    {orders.map((wo) => {
                        const isOverdue = wo.due_date && new Date(wo.due_date) < new Date() && !['DONE','REJECTED','CANCELLED'].includes(wo.status);
                        return (
                            <tr
                                key={wo.id}
                                className={`hover:bg-om-bg cursor-pointer ${isOverdue ? 'bg-om-blocked-bg' : ''}`}
                                onClick={() => window.location.href = `/admin/work-orders/${wo.id}`}
                            >
                                <td className="px-4 py-3">
                                    <span className="inline-flex items-center font-mono text-sm font-semibold text-om-accent bg-om-chip border border-om-line rounded px-2 py-0.5">
                                        {wo.order_no}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-sm text-om-muted">{wo.product_name ?? '—'}</td>
                                <td className="px-4 py-3 text-sm hidden sm:table-cell">
                                    {wo.due_date ? (
                                        <span className={isOverdue ? 'text-om-blocked font-semibold' : 'text-om-muted'}>
                                            {formatDate(new Date(wo.due_date), { day: 'numeric', month: 'short' })}
                                            {isOverdue && ' ⚠'}
                                        </span>
                                    ) : <span className="text-om-faint">—</span>}
                                </td>
                                <td className="px-4 py-3 text-sm text-om-muted hidden md:table-cell">
                                    {wo.planned_qty != null ? formatNumber(Number(wo.planned_qty)) : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUS_COLORS[wo.status] ?? 'bg-om-chip text-om-muted'}`}>
                                        {STATUS_LABELS[wo.status] ?? wo.status}
                                    </span>
                                </td>
                                <td className="px-4 py-3 hidden lg:table-cell">
                                    {wo.priority ? (
                                        <div className="flex items-center gap-1.5">
                                            <div className="h-1.5 rounded-full bg-om-ink" style={{ width: `${Math.min(wo.priority, 100)}px`, maxWidth: '80px' }} />
                                            <span className="text-xs text-om-muted">{wo.priority}</span>
                                        </div>
                                    ) : <span className="text-om-faint text-xs">—</span>}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

ScheduleIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
