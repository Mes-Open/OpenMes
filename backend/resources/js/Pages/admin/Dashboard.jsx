import { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useDashboardShapes } from '../../lib/useDashboardShapes';
import { useHotShapes } from '../../components/LiveShapesProvider';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';
import { formatDateTime } from '../../lib/i18n';

const WO_TERMINAL = ['DONE', 'CANCELLED', 'REJECTED'];

/**
 * Top-level admin dashboard. Reads the shared live collections (work orders /
 * issues) plus the dashboard lookups — all over the single Reverb WebSocket.
 */
export default function AdminDashboard(props) {
    const hot = useHotShapes(); // shared work_orders_active / issues_open collections

    return (
        <>
            <Head title={__('Admin Dashboard')} />
            <DashboardBody hot={hot} {...props} />
        </>
    );
}

// Persistent layout — AppLayout (sidebar + chrome) survives Inertia navigation
// between pages that share it, so the sidebar isn't torn down and rebuilt.
AdminDashboard.layout = (page) => <AppLayout>{page}</AppLayout>;

function DashboardBody({
    hot,
    enabledWidgets = [],
    widgetOrder = [],
    inboundQcStats,
    materialsStats,
    scrapStats,
}) {
    const { workOrders, lines, issues, issueTypes, oeeRecords, isLoading, error } =
        useDashboardShapes(hot);

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
                    <pre className="bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 p-3 rounded text-xs mb-4">
                        {String(error)}
                    </pre>
                )}

                {isLoading && (
                    <p className="text-gray-500 dark:text-gray-400 text-sm mb-4">{__('Connecting to live sync…')}</p>
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

                    {showWidget('scrap_overview') && scrapStats && (
                        <Section order={order.scrap_overview}>
                            <ScrapOverview stats={scrapStats} />
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
    const { locale } = usePage().props;
    const selectedLine = lines.find((l) => String(l.id) === String(selectedLineId));
    const dateStr = new Date().toLocaleString(locale, {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
    return (
        <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('Admin Dashboard')}</h1>
                <p className="text-gray-500 dark:text-gray-400 mt-1 text-sm">
                    {formatDateTime(new Date())}{' '}
                    {selectedLine ? (
                        <>— <span className="font-medium text-blue-600 dark:text-blue-300">{selectedLine.name}</span></>
                    ) : (
                        __('— all lines')
                    )}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <select
                    value={selectedLineId}
                    onChange={(e) => onLineChange(e.target.value)}
                    className="form-input py-1.5 text-sm pr-8 min-w-[180px]"
                >
                    <option value="">{__('All lines')}</option>
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
                        className="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-700"
                    >
                        ✕ {__('Clear')}
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
                label={__('Total Work Orders')}
                value={stats.total_work_orders}
            />
            <KpiCard
                href={`/admin/work-orders?status=IN_PROGRESS${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label={__('In Progress')}
                value={stats.in_progress}
                accent="blue"
                hint={__('incl. accepted')}
            />
            <KpiCard
                href={`/admin/work-orders?status=PENDING${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label={__('Pending')}
                value={stats.pending}
                accent="gray"
            />
            <KpiCard
                href={`/admin/work-orders?status=BLOCKED${lineQs ? '&' + lineQs.slice(1) : ''}`}
                label={__('Blocked')}
                value={stats.blocked}
                accent="red"
            />
            <KpiCard
                label={__('Active Today')}
                value={stats.active_today}
                accent="green"
                hint={__('started or updated')}
            />
            <KpiCard
                href="/admin/issues"
                label={__('Open Issues')}
                value={stats.open_issues}
                accent="yellow"
            />
            <KpiCard
                href="/admin/issues?blocking=1"
                label={__('Blocking Issues')}
                value={stats.blocking_issues}
                accent="red"
            />
            <KpiCard href="/admin/lines" label={__('Active Lines')} value={stats.active_lines} />
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
            className={`block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow ${
                accentClasses[accent] ? accentClasses[accent].split(' ').slice(0, 2).join(' ') : ''
            }`}
        >
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-1">{label}</p>
            <p className={`text-3xl font-bold ${valueClass}`}>{value}</p>
            {hint && <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{hint}</p>}
        </Wrapper>
    );
}

function OeeOverview({ records, lines }) {
    // Today's OEE per line. Mirrors the old Blade widget: one gauge card per
    // line, N/A where there's no record for today.
    const todayStr = new Date().toISOString().slice(0, 10);
    const byLineToday = useMemo(
        () => new Map(records.filter((r) => r.record_date === todayStr).map((r) => [String(r.line_id), r])),
        [records, todayStr],
    );
    const sortedLines = useMemo(
        () => [...lines].sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''))),
        [lines],
    );

    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">{__('OEE Overview')}</h2>
                <a href="/admin/oee" className="text-sm text-blue-600 dark:text-blue-300 hover:underline">
                    {__('Full report')} →
                </a>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                {sortedLines.map((line) => {
                    const r = byLineToday.get(String(line.id));
                    const value = r?.oee_pct != null ? Number(r.oee_pct) : null;
                    return (
                        <div
                            key={line.id}
                            className={`p-3 rounded-lg border flex flex-col items-center ${oeeCardClass(value)}`}
                        >
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-200 text-center mb-2 truncate w-full">
                                {line.name ?? __('Line :id', { id: line.id })}
                            </p>
                            <OeeGauge value={value} />
                            {r ? (
                                <div className="flex gap-2 text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    <span>A: {pct(r.availability_pct)}</span>
                                    <span>P: {r.performance_pct != null ? pct(r.performance_pct) : '-'}</span>
                                    <span>Q: {pct(r.quality_pct)}</span>
                                </div>
                            ) : (
                                <div className="text-xs text-gray-400 dark:text-gray-500 mt-2">&nbsp;</div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// OEE banding — mirrors backend/app/Support/OeeBand.php (red < 65 ≤ yellow < 85 ≤ green).
const OEE_RED_BELOW = 65;
const OEE_GREEN_AT_LEAST = 85;

function oeeColor(value) {
    if (value == null) return 'gray';
    if (value >= OEE_GREEN_AT_LEAST) return 'green';
    if (value >= OEE_RED_BELOW) return 'yellow';
    return 'red';
}

function oeeCardClass(value) {
    return {
        green: 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800',
        yellow: 'border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800',
        red: 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800',
        gray: 'border-gray-200 bg-gray-50 dark:bg-slate-700 dark:border-slate-600',
    }[oeeColor(value)];
}

function oeeTextClass(value) {
    return {
        green: 'text-green-600 dark:text-green-400',
        yellow: 'text-yellow-600 dark:text-yellow-400',
        red: 'text-red-600 dark:text-red-400',
        gray: 'text-gray-500 dark:text-gray-400',
    }[oeeColor(value)];
}

/**
 * Semicircle OEE gauge with red/yellow/green zones and a needle — React port of
 * the `<x-oee-gauge>` Blade component. Points lie on a unit semicircle centered
 * at (50,50), r=40: p=0 → (10,50), p=50 → (50,10), p=100 → (90,50).
 */
function OeeGauge({ value, size = 104 }) {
    const hasValue = value != null;
    const p = hasValue ? Math.max(0, Math.min(100, Number(value))) : 0;
    const pointAt = (q, r = 40) => {
        const a = (q / 100) * Math.PI;
        return [50 - r * Math.cos(a), 50 - r * Math.sin(a)];
    };
    const [rEndX, rEndY] = pointAt(OEE_RED_BELOW);
    const [yEndX, yEndY] = pointAt(OEE_GREEN_AT_LEAST);
    const [gEndX, gEndY] = pointAt(100);
    const [needleX, needleY] = pointAt(p, 35);

    return (
        <div className="inline-flex flex-col items-center" style={{ width: size }}>
            <svg viewBox="0 0 100 60" className="w-full h-auto" aria-hidden="true">
                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="currentColor" strokeWidth="10" className="text-gray-200 dark:text-slate-600" />
                <path d={`M 10 50 A 40 40 0 0 1 ${rEndX} ${rEndY}`} fill="none" stroke="#ef4444" strokeWidth="10" />
                <path d={`M ${rEndX} ${rEndY} A 40 40 0 0 1 ${yEndX} ${yEndY}`} fill="none" stroke="#eab308" strokeWidth="10" />
                <path d={`M ${yEndX} ${yEndY} A 40 40 0 0 1 ${gEndX} ${gEndY}`} fill="none" stroke="#22c55e" strokeWidth="10" />
                {hasValue ? (
                    <>
                        <line x1="50" y1="50" x2={needleX} y2={needleY} stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" className="text-gray-800 dark:text-gray-100" />
                        <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-gray-800 dark:text-gray-100" />
                    </>
                ) : (
                    <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-gray-400" />
                )}
            </svg>
            <div className="-mt-2 text-center leading-tight">
                <div className={`font-bold ${oeeTextClass(hasValue ? p : null)}`} style={{ fontSize: size * 0.18 }}>
                    {hasValue ? `${p.toFixed(1)}%` : 'N/A'}
                </div>
                <div className="text-gray-500 dark:text-gray-400 uppercase tracking-wide" style={{ fontSize: size * 0.085 }}>
                    OEE
                </div>
            </div>
        </div>
    );
}

function InboundQcOverview({ stats }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">{__('Inbound QC (30 days)')}</h2>
                <a href="/inspections" className="text-sm text-blue-600 dark:text-blue-300 hover:underline">{__('View all')} →</a>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
                <Stat label={__('Pending')} value={stats.pending} />
                <Stat label={__('Completed')} value={stats.completed_30d} />
                <Stat label={__('Failed')} value={stats.failed_30d} color="red" />
                <Stat label={__('Conditional')} value={stats.conditional_30d} color="yellow" />
                <Stat
                    label={__('Pass rate')}
                    value={stats.pass_rate_30d != null ? `${stats.pass_rate_30d}%` : '—'}
                    color="green"
                />
            </div>
        </div>
    );
}

function MaterialsOverview({ stats }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-3">{__('Materials')}</h2>
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
                <Stat label={__('Low stock')} value={stats.low_stock_count} color="red" />
                <Stat label={__('Expiring 30d')} value={stats.expiring_count} color="yellow" />
                <Stat label={__('Lots released')} value={stats.lots_total} />
                <Stat label={__('Quarantined')} value={stats.quarantined_count} color="red" />
                <Stat label={__('Reserved qty')} value={Number(stats.reserved_total).toFixed(0)} />
            </div>
        </div>
    );
}

function ScrapOverview({ stats }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">{__('Scrap (30 days)')}</h2>
                <a href="/admin/scrap-reports" className="text-sm text-blue-600 dark:text-blue-300 hover:underline">{__('Full report')} →</a>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <Stat label={__('Total scrap')} value={Number(stats.total_qty_30d ?? 0).toFixed(0)} color="red" />
                <Stat label={__('Scrap entries')} value={stats.entries_30d ?? 0} />
                <Stat
                    label={__('Top reason')}
                    value={stats.top_reason ? `${stats.top_reason} (${Number(stats.top_reason_qty ?? 0).toFixed(0)})` : '—'}
                    color="yellow"
                />
            </div>
        </div>
    );
}

function Stat({ label, value, color }) {
    // Colored bordered tiles (parity with the Blade dashboard widgets).
    const tile = {
        red: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20',
        yellow: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20',
        green: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20',
        blue: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20',
        purple: 'border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-900/20',
    };
    const colors = {
        red: 'text-red-600 dark:text-red-300',
        yellow: 'text-yellow-600',
        green: 'text-green-600 dark:text-green-300',
        blue: 'text-blue-600 dark:text-blue-300',
        purple: 'text-purple-600 dark:text-purple-300',
    };
    return (
        <div className={`p-3 rounded-lg border ${tile[color] ?? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40'}`}>
            <p className="text-gray-500 dark:text-gray-400 mb-1">{label}</p>
            <p className={`text-2xl font-bold ${colors[color] ?? 'text-gray-800 dark:text-gray-100'}`}>{value}</p>
        </div>
    );
}

function RecentWorkOrders({ rows, lines }) {
    const lineById = useMemo(() => new Map(lines.map((l) => [String(l.id), l])), [lines]);
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">{__('Recent work orders')}</h2>
                <a href="/admin/work-orders" className="text-sm text-blue-600 dark:text-blue-300 hover:underline">{__('View all')} →</a>
            </div>
            {rows.length === 0 ? (
                <p className="text-gray-500 dark:text-gray-400 text-sm">{__('No active work orders.')}</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 dark:text-gray-400 border-b">
                            <th className="py-2">{__('Order')}</th>
                            <th className="py-2">{__('Line')}</th>
                            <th className="py-2">{__('Status')}</th>
                            <th className="py-2 text-right">{__('Produced / planned')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((wo) => (
                            <tr key={wo.id} className="border-b last:border-0">
                                <td className="py-2">
                                    <a
                                        href={`/admin/work-orders/${wo.id}`}
                                        className="text-blue-600 dark:text-blue-300 hover:underline"
                                    >
                                        {wo.order_no}
                                    </a>
                                </td>
                                <td className="py-2 text-gray-600 dark:text-gray-300">
                                    {lineById.get(String(wo.line_id))?.name ?? '—'}
                                </td>
                                <td className="py-2">
                                    <StatusBadge status={wo.status} />
                                </td>
                                <td className="py-2 text-right text-gray-600 dark:text-gray-300">
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
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">{__('Open issues')}</h2>
                <a href="/admin/issues" className="text-sm text-blue-600 dark:text-blue-300 hover:underline">{__('View all')} →</a>
            </div>
            {rows.length === 0 ? (
                <p className="text-gray-500 dark:text-gray-400 text-sm">{__('No open issues.')} 🎉</p>
            ) : (
                <ul className="divide-y">
                    {rows.map((issue) => {
                        const type = typeById.get(String(issue.issue_type_id));
                        return (
                            <li key={issue.id} className="py-2 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                        {issue.title}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {type?.name ?? '—'} ·{' '}
                                        {issue.work_order_id ? __('WO #:id', { id: issue.work_order_id }) : __('no WO')}
                                    </p>
                                </div>
                                <div className="flex gap-2 items-center">
                                    {type?.is_blocking && (
                                        <span className="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 px-2 py-0.5 rounded">
                                            {__('blocking')}
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
        ACCEPTED: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
        IN_PROGRESS: 'bg-emerald-100 text-emerald-800',
        BLOCKED: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200',
        PAUSED: 'bg-gray-200 dark:bg-gray-700 text-gray-700',
        OPEN: 'bg-yellow-100 text-yellow-800',
        ACKNOWLEDGED: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200',
    };
    return (
        <span
            className={`text-xs px-2 py-0.5 rounded font-medium ${
                colors[status] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700'
            }`}
        >
            {__(status)}
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
        'scrap_overview',
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
