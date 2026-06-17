// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (live-shape wiring and stats logic untouched).
import { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Dropdown, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
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
                    <pre className="bg-om-blocked-bg text-om-blocked p-3 rounded-om text-xs mb-4">
                        {String(error)}
                    </pre>
                )}

                {isLoading && (
                    <p className="text-om-muted text-sm mb-4">{__('Connecting to live sync…')}</p>
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
    const { locale } = usePage().props;
    const selectedLine = lines.find((l) => String(l.id) === String(selectedLineId));
    const dateStr = new Date().toLocaleString(locale, {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
    return (
        <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Admin Dashboard')}</h1>
                <p className="text-om-muted mt-1 text-sm">
                    {formatDateTime(new Date())}{' '}
                    {selectedLine ? (
                        <>— <span className="font-medium text-om-accent">{selectedLine.name}</span></>
                    ) : (
                        __('— all lines')
                    )}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Dropdown
                    value={selectedLineId == null ? '' : String(selectedLineId)}
                    onChange={(v) => onLineChange(v)}
                    placeholder={__('All lines')}
                    className="min-w-[180px]"
                    options={[
                        { value: '', label: __('All lines') },
                        ...lines
                            .slice()
                            .sort((a, b) => a.name.localeCompare(b.name))
                            .map((line) => ({ value: String(line.id), label: line.name })),
                    ]}
                />
                {selectedLineId && (
                    <button
                        onClick={() => onLineChange('')}
                        className="text-xs text-om-faint hover:text-om-ink cursor-pointer"
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
    // Geist White state tints: accent orange (primary), running green, blocked red, downtime amber.
    const accentClasses = {
        blue: 'text-om-accent',
        gray: 'text-om-muted',
        red: 'text-om-blocked',
        green: 'text-om-running',
        yellow: 'text-om-downtime',
    };
    const valueClass = accentClasses[accent] ?? 'text-om-ink';
    const Wrapper = href ? 'a' : 'div';
    return (
        <Wrapper
            href={href}
            className={`block bg-om-card border border-om-line rounded-om p-5 transition-colors ${
                href ? 'hover:border-om-faintest' : ''
            }`}
        >
            <p className="mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</p>
            <p className={`font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${valueClass}`}>{value}</p>
            {hint && <p className="text-[11px] text-om-faint mt-1.5">{hint}</p>}
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
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-[14px] font-semibold text-om-ink">{__('OEE Overview')}</h2>
                <a href="/admin/oee" className="text-[12.5px] text-om-accent hover:underline">
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
                            className={`p-3 rounded-om-sm border flex flex-col items-center ${oeeCardClass(value)}`}
                        >
                            <p className="text-[12.5px] font-medium text-om-ink text-center mb-2 truncate w-full">
                                {line.name ?? __('Line :id', { id: line.id })}
                            </p>
                            <OeeGauge value={value} />
                            {r ? (
                                <div className="flex gap-2 font-mono text-[10px] text-om-muted mt-2">
                                    <span>A: {pct(r.availability_pct)}</span>
                                    <span>P: {r.performance_pct != null ? pct(r.performance_pct) : '-'}</span>
                                    <span>Q: {pct(r.quality_pct)}</span>
                                </div>
                            ) : (
                                <div className="text-xs text-om-faint mt-2">&nbsp;</div>
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
        green: 'border-om-line bg-om-running-bg',
        yellow: 'border-om-line bg-om-downtime-bg',
        red: 'border-om-line bg-om-blocked-bg',
        gray: 'border-om-line bg-om-panel',
    }[oeeColor(value)];
}

function oeeTextClass(value) {
    return {
        green: 'text-om-running',
        yellow: 'text-om-downtime',
        red: 'text-om-blocked',
        gray: 'text-om-faint',
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
                <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="currentColor" strokeWidth="10" className="text-om-line2" />
                <path d={`M 10 50 A 40 40 0 0 1 ${rEndX} ${rEndY}`} fill="none" stroke="#D6442F" strokeWidth="10" />
                <path d={`M ${rEndX} ${rEndY} A 40 40 0 0 1 ${yEndX} ${yEndY}`} fill="none" stroke="#C9821E" strokeWidth="10" />
                <path d={`M ${yEndX} ${yEndY} A 40 40 0 0 1 ${gEndX} ${gEndY}`} fill="none" stroke="#1C9A55" strokeWidth="10" />
                {hasValue ? (
                    <>
                        <line x1="50" y1="50" x2={needleX} y2={needleY} stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" className="text-om-ink" />
                        <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-om-ink" />
                    </>
                ) : (
                    <circle cx="50" cy="50" r="2.2" fill="currentColor" className="text-om-faintest" />
                )}
            </svg>
            <div className="-mt-2 text-center leading-tight">
                <div className={`font-mono font-medium ${oeeTextClass(hasValue ? p : null)}`} style={{ fontSize: size * 0.18 }}>
                    {hasValue ? `${p.toFixed(1)}%` : 'N/A'}
                </div>
                <div className="font-mono text-om-faint uppercase tracking-[0.08em]" style={{ fontSize: size * 0.085 }}>
                    OEE
                </div>
            </div>
        </div>
    );
}

function InboundQcOverview({ stats }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-[14px] font-semibold text-om-ink">{__('Inbound QC (30 days)')}</h2>
                <a href="/inspections" className="text-[12.5px] text-om-accent hover:underline">{__('View all')} →</a>
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
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <h2 className="text-[14px] font-semibold text-om-ink mb-3">{__('Materials')}</h2>
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

function Stat({ label, value, color }) {
    // State-tinted tiles on om tokens (parity with the Blade dashboard widgets).
    const tile = {
        red: 'bg-om-blocked-bg',
        yellow: 'bg-om-downtime-bg',
        green: 'bg-om-running-bg',
        blue: 'bg-om-chip',
        purple: 'bg-om-chip',
    };
    const colors = {
        red: 'text-om-blocked',
        yellow: 'text-om-downtime',
        green: 'text-om-running',
        blue: 'text-om-accent',
        purple: 'text-om-accent',
    };
    return (
        <div className={`p-3 rounded-om-sm border border-om-line ${tile[color] ?? 'bg-om-panel'}`}>
            <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</p>
            <p className={`font-mono text-[22px] font-medium tracking-[-0.02em] ${colors[color] ?? 'text-om-ink'}`}>{value}</p>
        </div>
    );
}

function RecentWorkOrders({ rows, lines }) {
    const lineById = useMemo(() => new Map(lines.map((l) => [String(l.id), l])), [lines]);
    const columns = useMemo(
        () => [
            {
                id: 'order',
                accessorKey: 'order_no',
                header: __('Order'),
                cell: ({ row }) => {
                    const wo = row.original;
                    return (
                        <a
                            href={`/admin/work-orders/${wo.id}`}
                            className="font-mono text-[12px] font-medium text-om-accent hover:underline"
                        >
                            {wo.order_no}
                        </a>
                    );
                },
            },
            {
                id: 'line',
                accessorFn: (wo) => lineById.get(String(wo.line_id))?.name ?? '—',
                header: __('Line'),
                cell: ({ row }) => (
                    <span className="text-om-muted">
                        {lineById.get(String(row.original.line_id))?.name ?? '—'}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: __('Status'),
                cell: ({ row }) => <StatusBadge status={row.original.status} />,
            },
            {
                id: 'produced_planned',
                accessorFn: (wo) => Number(wo.produced_qty),
                header: __('Produced / planned'),
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const wo = row.original;
                    return (
                        <span className="font-mono text-[13px] text-om-muted">
                            {Number(wo.produced_qty).toFixed(0)} /{' '}
                            {Number(wo.planned_qty).toFixed(0)}
                        </span>
                    );
                },
            },
        ],
        [lineById],
    );
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-[14px] font-semibold text-om-ink">{__('Recent work orders')}</h2>
                <a href="/admin/work-orders" className="text-[12.5px] text-om-accent hover:underline">{__('View all')} →</a>
            </div>
            <DataTable
                data={rows}
                columns={columns}
                searchable={false}
                columnToggle={false}
                paginated={false}
                emptyLabel={__('No active work orders.')}
            />
        </div>
    );
}

function RecentIssues({ rows, issueTypes }) {
    const typeById = useMemo(
        () => new Map(issueTypes.map((t) => [String(t.id), t])),
        [issueTypes],
    );
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-[14px] font-semibold text-om-ink">{__('Open issues')}</h2>
                <a href="/admin/issues" className="text-[12.5px] text-om-accent hover:underline">{__('View all')} →</a>
            </div>
            {rows.length === 0 ? (
                <p className="text-om-muted text-sm">{__('No open issues.')} 🎉</p>
            ) : (
                <ul className="divide-y divide-om-line2">
                    {rows.map((issue) => {
                        const type = typeById.get(String(issue.issue_type_id));
                        return (
                            <li key={issue.id} className="py-2 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-om-ink">
                                        {issue.title}
                                    </p>
                                    <p className="text-xs text-om-faint">
                                        {type?.name ?? '—'} ·{' '}
                                        {issue.work_order_id ? __('WO #:id', { id: issue.work_order_id }) : __('no WO')}
                                    </p>
                                </div>
                                <div className="flex gap-2 items-center">
                                    {type?.is_blocking && (
                                        <Badge variant="danger">{__('blocking')}</Badge>
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

// App status → Geist White pill state (labels stay the translated app statuses).
const PILL_STATUS = {
    PENDING: 'pending',
    ACCEPTED: 'pending',
    IN_PROGRESS: 'running',
    BLOCKED: 'blocked',
    PAUSED: 'downtime',
    OPEN: 'blocked',
    ACKNOWLEDGED: 'downtime',
    RESOLVED: 'running',
    DONE: 'done',
    CLOSED: 'done',
};

function StatusBadge({ status }) {
    return <StatusPill status={PILL_STATUS[status] ?? 'pending'} label={__(status)} />;
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
