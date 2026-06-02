import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useDashboardShapes, DASHBOARD_SHAPES } from '../../lib/useDashboardShapes';
import { useShapeConfigs } from '../../lib/useShapeConfigs';
import { useHotShapes } from '../../components/LiveShapesProvider';
import AppLayout from '../../layouts/AppLayout';

const WO_TERMINAL = ['DONE', 'CANCELLED', 'REJECTED'];

/**
 * Top-level page. Fetches signed shape configs from the gatekeeper first, then
 * mounts the live dashboard once they're ready — so the shape hooks inside
 * DashboardBody always run with valid signed params (rules of hooks).
 */
export default function AdminDashboard(props) {
    const { configs, error } = useShapeConfigs(DASHBOARD_SHAPES);
    const hot = useHotShapes(); // shared work_orders_active / issues_open collections

    return (
        <>
            <Head title="Admin Dashboard" />
            {error ? (
                <div className="max-w-7xl mx-auto">
                    <pre className="bg-red-50 text-red-800 p-3 rounded text-xs">{String(error)}</pre>
                </div>
            ) : !configs || !hot ? (
                <div className="max-w-7xl mx-auto">
                    <h1 className="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
                    <p className="text-gray-500 text-sm mt-2">Connecting to live sync…</p>
                </div>
            ) : (
                <DashboardBody configs={configs} hot={hot} {...props} />
            )}
        </>
    );
}

// Persistent layout — AppLayout (sidebar + chrome) survives Inertia navigation
// between pages that share it, so the sidebar isn't torn down and rebuilt.
AdminDashboard.layout = (page) => <AppLayout>{page}</AppLayout>;

function DashboardBody({
    configs,
    hot,
    enabledWidgets = [],
    widgetOrder = [],
    inboundQcStats,
    materialsStats,
}) {
    const { workOrders, lines, issues, issueTypes, oeeRecords, isLoading, error } =
        useDashboardShapes(configs, hot);

    const [selectedLineId, setSelectedLineId] = useState('');

    const scopedWorkOrders = useMemo(
        () => filterByLine(workOrders, selectedLineId),
        [workOrders, selectedLineId],
    );

    const scopedIssues = useMemo(() => {
        if (!selectedLineId) return issues;
        const lineWoIds = new Set(
            workOrders
                .filter((wo) => String(wo.line_id) === String(selectedLineId))
                .map((wo) => String(wo.id)),
        );
        return issues.filter((i) => lineWoIds.has(String(i.work_order_id)));
    }, [issues, workOrders, selectedLineId]);

    const stats = useMemo(
        () => computeStats(scopedWorkOrders, scopedIssues, issueTypes, lines),
        [scopedWorkOrders, scopedIssues, issueTypes, lines],
    );

    const recentWorkOrders = useMemo(
        () =>
            [...scopedWorkOrders]
                .sort((a, b) => byDateDesc(a.created_at, b.created_at))
                .slice(0, 10),
        [scopedWorkOrders],
    );

    const recentIssues = useMemo(
        () =>
            [...scopedIssues]
                .sort((a, b) => byDateDesc(a.created_at, b.created_at))
                .slice(0, 5),
        [scopedIssues],
    );

    const showWidget = (id) => enabledWidgets.length === 0 || enabledWidgets.includes(id);
    const order = useMemo(() => buildOrder(widgetOrder), [widgetOrder]);

    return (
        <div className="max-w-7xl mx-auto">
                <Header
                    selectedLineId={selectedLineId}
                    onLineChange={setSelectedLineId}
                    lines={lines}
                />

                {error && (
                    <pre className="bg-red-50 text-red-800 p-3 rounded text-xs mb-4">
                        {String(error)}
                    </pre>
                )}

                {isLoading && (
                    <p className="text-gray-500 text-sm mb-4">Connecting to live sync…</p>
                )}

                <div className="flex flex-col gap-6">
                    {showWidget('kpi_cards') && (
                        <Section order={order.kpi_cards}>
                            <KpiCards stats={stats} selectedLineId={selectedLineId} />
                        </Section>
                    )}

                    {showWidget('oee_overview') && oeeRecords.length > 0 && (
                        <Section order={order.oee_overview}>
                            <OeeOverview records={oeeRecords} lines={lines} />
                        </Section>
                    )}

                    {showWidget('inbound_qc_overview') && inboundQcStats && (
                        <Section order={order.inbound_qc_overview}>
                            <InboundQcOverview stats={inboundQcStats} />
                        </Section>
                    )}

                    {showWidget('materials_overview') && materialsStats && (
                        <Section order={order.materials_overview}>
                            <MaterialsOverview stats={materialsStats} />
                        </Section>
                    )}

                    {showWidget('recent_work_orders') && (
                        <Section order={order.recent_work_orders}>
                            <RecentWorkOrders rows={recentWorkOrders} lines={lines} />
                        </Section>
                    )}

                    {showWidget('open_issues') && (
                        <Section order={order.open_issues}>
                            <RecentIssues rows={recentIssues} issueTypes={issueTypes} />
                        </Section>
                    )}
                </div>
        </div>
    );
}

function Header({ selectedLineId, onLineChange, lines }) {
    const selectedLine = lines.find((l) => String(l.id) === String(selectedLineId));
    return (
        <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 className="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
                <p className="text-gray-500 mt-1 text-sm">
                    {new Date().toLocaleString()}{' '}
                    {selectedLine ? (
                        <>— <span className="font-medium text-blue-600">{selectedLine.name}</span></>
                    ) : (
                        '— all lines'
                    )}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <select
                    value={selectedLineId}
                    onChange={(e) => onLineChange(e.target.value)}
                    className="form-input py-1.5 text-sm pr-8 min-w-[180px]"
                >
                    <option value="">All lines</option>
                    {lines
                        .slice()
                        .sort((a, b) => a.name.localeCompare(b.name))
                        .map((line) => (
                            <option key={line.id} value={line.id}>
                                {line.name}
                            </option>
                        ))}
                </select>
                {selectedLineId && (
                    <button
                        onClick={() => onLineChange('')}
                        className="text-xs text-gray-400 hover:text-gray-700"
                    >
                        ✕ Clear
                    </button>
                )}
            </div>
        </div>
    );
}

function KpiCards({ stats, selectedLineId }) {
    const lineQs = selectedLineId ? `?line_id=${selectedLineId}` : '';
    return (
        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <KpiCard
                href={`/admin/work-orders${lineQs}`}
                label="Total Work Orders"
                value={stats.total_work_orders}
            />
            <KpiCard
                href={`/admin/work-orders?status=IN_PROGRESS${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label="In Progress"
                value={stats.in_progress}
                accent="blue"
                hint="incl. accepted"
            />
            <KpiCard
                href={`/admin/work-orders?status=PENDING${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label="Pending"
                value={stats.pending}
                accent="gray"
            />
            <KpiCard
                href={`/admin/work-orders?status=BLOCKED${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label="Blocked"
                value={stats.blocked}
                accent="red"
            />
            <KpiCard
                label="Active Today"
                value={stats.active_today}
                accent="green"
                hint="started or updated"
            />
            <KpiCard
                href="/admin/issues"
                label="Open Issues"
                value={stats.open_issues}
                accent="yellow"
            />
            <KpiCard
                href="/admin/issues?blocking=1"
                label="Blocking Issues"
                value={stats.blocking_issues}
                accent="red"
            />
            <KpiCard href="/admin/lines" label="Active Lines" value={stats.active_lines} />
        </div>
    );
}

function KpiCard({ href, label, value, accent, hint }) {
    const accentClasses = {
        blue: 'border-l-4 border-blue-400 text-blue-600',
        gray: 'border-l-4 border-gray-300 text-gray-600',
        red: 'border-l-4 border-red-400 text-red-600',
        green: 'border-l-4 border-green-400 text-green-600',
        yellow: 'border-l-4 border-yellow-400 text-yellow-600',
    };
    const valueClass = accent ? accentClasses[accent]?.split(' ').slice(-1)[0] : 'text-gray-800';
    const Wrapper = href ? 'a' : 'div';
    return (
        <Wrapper
            href={href}
            className={`block bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow ${
                accentClasses[accent] ? accentClasses[accent].split(' ').slice(0, 2).join(' ') : ''
            }`}
        >
            <p className="text-sm text-gray-500 mb-1">{label}</p>
            <p className={`text-3xl font-bold ${valueClass}`}>{value}</p>
            {hint && <p className="text-xs text-gray-400 mt-0.5">{hint}</p>}
        </Wrapper>
    );
}

function OeeOverview({ records, lines }) {
    const lineById = useMemo(() => new Map(lines.map((l) => [String(l.id), l])), [lines]);
    const todayStr = new Date().toISOString().slice(0, 10);
    const today = records.filter((r) => r.record_date === todayStr);
    const yesterday = records.filter((r) => r.record_date !== todayStr);
    const byLineToday = new Map(today.map((r) => [String(r.line_id), r]));
    const byLineYesterday = new Map(yesterday.map((r) => [String(r.line_id), r]));
    const lineIds = Array.from(
        new Set([...byLineToday.keys(), ...byLineYesterday.keys()]),
    ).sort();

    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">OEE Overview</h2>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-gray-500 border-b">
                        <th className="py-2">Line</th>
                        <th className="py-2">Today OEE</th>
                        <th className="py-2">Today A × P × Q</th>
                        <th className="py-2">Yesterday OEE</th>
                    </tr>
                </thead>
                <tbody>
                    {lineIds.map((id) => {
                        const t = byLineToday.get(id);
                        const y = byLineYesterday.get(id);
                        const line = lineById.get(id);
                        return (
                            <tr key={id} className="border-b last:border-0">
                                <td className="py-2 font-medium">
                                    {line?.name ?? `Line ${id}`}
                                </td>
                                <td className="py-2">
                                    <OeeBadge value={t?.oee_pct} />
                                </td>
                                <td className="py-2 text-gray-600">
                                    {t ? `${pct(t.availability_pct)} · ${pct(t.performance_pct)} · ${pct(t.quality_pct)}` : '—'}
                                </td>
                                <td className="py-2">
                                    <OeeBadge value={y?.oee_pct} muted />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function OeeBadge({ value, muted }) {
    if (value == null) return <span className="text-gray-400">—</span>;
    const n = Number(value);
    const color =
        n >= 85 ? 'text-green-600' : n >= 60 ? 'text-yellow-600' : 'text-red-600';
    return <span className={`font-bold ${muted ? 'text-gray-500' : color}`}>{n.toFixed(1)}%</span>;
}

function InboundQcOverview({ stats }) {
    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">Inbound QC (30 days)</h2>
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
                <Stat label="Pending" value={stats.pending} />
                <Stat label="Completed" value={stats.completed_30d} />
                <Stat label="Failed" value={stats.failed_30d} color="red" />
                <Stat label="Conditional" value={stats.conditional_30d} color="yellow" />
                <Stat
                    label="Pass rate"
                    value={stats.pass_rate_30d != null ? `${stats.pass_rate_30d}%` : '—'}
                    color="green"
                />
            </div>
        </div>
    );
}

function MaterialsOverview({ stats }) {
    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">Materials</h2>
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
                <Stat label="Low stock" value={stats.low_stock_count} color="red" />
                <Stat label="Expiring 30d" value={stats.expiring_count} color="yellow" />
                <Stat label="Lots released" value={stats.lots_total} />
                <Stat label="Quarantined" value={stats.quarantined_count} color="red" />
                <Stat label="Reserved qty" value={Number(stats.reserved_total).toFixed(0)} />
            </div>
        </div>
    );
}

function Stat({ label, value, color }) {
    const colors = {
        red: 'text-red-600',
        yellow: 'text-yellow-600',
        green: 'text-green-600',
    };
    return (
        <div>
            <p className="text-gray-500 mb-1">{label}</p>
            <p className={`text-2xl font-bold ${colors[color] ?? 'text-gray-800'}`}>{value}</p>
        </div>
    );
}

function RecentWorkOrders({ rows, lines }) {
    const lineById = useMemo(() => new Map(lines.map((l) => [String(l.id), l])), [lines]);
    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">Recent work orders</h2>
            {rows.length === 0 ? (
                <p className="text-gray-500 text-sm">No active work orders.</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b">
                            <th className="py-2">Order</th>
                            <th className="py-2">Line</th>
                            <th className="py-2">Status</th>
                            <th className="py-2 text-right">Produced / planned</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((wo) => (
                            <tr key={wo.id} className="border-b last:border-0">
                                <td className="py-2">
                                    <a
                                        href={`/admin/work-orders/${wo.id}`}
                                        className="text-blue-600 hover:underline"
                                    >
                                        {wo.order_no}
                                    </a>
                                </td>
                                <td className="py-2 text-gray-600">
                                    {lineById.get(String(wo.line_id))?.name ?? '—'}
                                </td>
                                <td className="py-2">
                                    <StatusBadge status={wo.status} />
                                </td>
                                <td className="py-2 text-right text-gray-600">
                                    {Number(wo.produced_qty).toFixed(0)} /{' '}
                                    {Number(wo.planned_qty).toFixed(0)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

function RecentIssues({ rows, issueTypes }) {
    const typeById = useMemo(
        () => new Map(issueTypes.map((t) => [String(t.id), t])),
        [issueTypes],
    );
    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">Open issues</h2>
            {rows.length === 0 ? (
                <p className="text-gray-500 text-sm">No open issues. 🎉</p>
            ) : (
                <ul className="divide-y">
                    {rows.map((issue) => {
                        const type = typeById.get(String(issue.issue_type_id));
                        return (
                            <li key={issue.id} className="py-2 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-800">
                                        {issue.title}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {type?.name ?? '—'} ·{' '}
                                        {issue.work_order_id ? `WO #${issue.work_order_id}` : 'no WO'}
                                    </p>
                                </div>
                                <div className="flex gap-2 items-center">
                                    {type?.is_blocking && (
                                        <span className="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">
                                            blocking
                                        </span>
                                    )}
                                    <StatusBadge status={issue.status} />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

function StatusBadge({ status }) {
    const colors = {
        PENDING: 'bg-yellow-100 text-yellow-800',
        ACCEPTED: 'bg-blue-100 text-blue-800',
        IN_PROGRESS: 'bg-emerald-100 text-emerald-800',
        BLOCKED: 'bg-red-100 text-red-800',
        PAUSED: 'bg-gray-200 text-gray-700',
        OPEN: 'bg-yellow-100 text-yellow-800',
        ACKNOWLEDGED: 'bg-blue-100 text-blue-800',
    };
    return (
        <span
            className={`text-xs px-2 py-0.5 rounded font-medium ${
                colors[status] ?? 'bg-gray-100 text-gray-700'
            }`}
        >
            {status}
        </span>
    );
}

function Section({ order, children }) {
    return <div style={{ order: order * 10 }}>{children}</div>;
}

function computeStats(workOrders, issues, issueTypes, lines) {
    const counts = workOrders.reduce(
        (acc, wo) => {
            acc.total++;
            if (wo.status === 'PENDING') acc.pending++;
            if (wo.status === 'ACCEPTED' || wo.status === 'IN_PROGRESS') acc.in_progress++;
            if (wo.status === 'BLOCKED') acc.blocked++;
            return acc;
        },
        { total: 0, pending: 0, in_progress: 0, blocked: 0 },
    );

    // Shape excludes terminal statuses, so "done today" can't be derived from
    // synced rows. Surface a related signal: WOs updated today (proxy for
    // production activity). Real done-today belongs to a separate shape or
    // an Inertia prop later.
    const today = new Date().toISOString().slice(0, 10);
    const active_today = workOrders.filter(
        (wo) => (wo.updated_at ?? '').slice(0, 10) === today,
    ).length;

    const blockingTypeIds = new Set(
        issueTypes.filter((t) => t.is_blocking).map((t) => String(t.id)),
    );
    const blocking_issues = issues.filter((i) =>
        blockingTypeIds.has(String(i.issue_type_id)),
    ).length;

    return {
        total_work_orders: counts.total,
        pending: counts.pending,
        in_progress: counts.in_progress,
        blocked: counts.blocked,
        active_today,
        open_issues: issues.length,
        blocking_issues,
        active_lines: lines.filter((l) => l.is_active).length,
    };
}

function filterByLine(rows, lineId) {
    if (!lineId) return rows;
    return rows.filter((row) => String(row.line_id) === String(lineId));
}

function byDateDesc(a, b) {
    return (b ?? '').localeCompare(a ?? '');
}

function buildOrder(widgetOrder) {
    const defaults = [
        'kpi_cards',
        'oee_overview',
        'inbound_qc_overview',
        'materials_overview',
        'recent_work_orders',
        'open_issues',
    ];
    const indexOf = (id) => {
        const i = widgetOrder.indexOf(id);
        return i === -1 ? defaults.indexOf(id) + 100 : i;
    };
    return Object.fromEntries(defaults.map((id) => [id, indexOf(id)]));
}

function pct(v) {
    if (v == null) return '—';
    return `${Number(v).toFixed(0)}%`;
}
