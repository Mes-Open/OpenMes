import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const DISPOSITION_LABELS = {
    pending: 'Pending',
    scrap: 'Scrap',
    rework: 'Rework',
    return_to_supplier: 'Return to supplier',
    use_as_is: 'Use as is',
};
const DISPOSITION_BAR = {
    pending: 'bg-om-downtime',
    scrap: 'bg-om-blocked',
    rework: 'bg-om-accent',
    return_to_supplier: 'bg-om-accent',
    use_as_is: 'bg-om-running',
};

const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(undefined, { maximumFractionDigits: 2 });

export default function NonConformanceReportIndex() {
    const {
        dateFrom, dateTo,
        pareto = { total_count: 0, total_nc_qty: 0, types: [] },
        dispositionSummary = {},
        overdueActions = 0,
    } = usePage().props;

    const types = pareto.types ?? [];
    const topType = types[0] ?? null;
    const maxCount = Math.max(...types.map((t) => num(t.count)), 1);

    const dispositionTotal = Object.values(dispositionSummary).reduce((a, b) => a + num(b), 0);

    const apply = (changes) =>
        router.get('/admin/non-conformance-reports', { date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });

    const paretoColumns = [
        { id: 'name', accessorKey: 'name', header: __('Issue type'), cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.name}</span> },
        { id: 'count', accessorFn: (r) => num(r.count), header: __('Count'), cell: ({ row }) => <span className="tabular-nums">{row.original.count}</span>, meta: { align: 'right' } },
        { id: 'nc_qty', accessorFn: (r) => num(r.nc_qty), header: __('Non-conforming quantity'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.nc_qty)}</span>, meta: { align: 'right' } },
        { id: 'pct', accessorFn: (r) => num(r.pct), header: __('% of Total'), cell: ({ row }) => <span className="tabular-nums">{num(row.original.pct).toFixed(1)}%</span>, meta: { align: 'right' } },
        { id: 'cumulative_pct', accessorFn: (r) => num(r.cumulative_pct), header: __('Cumulative %'), cell: ({ row }) => <span className="tabular-nums">{num(row.original.cumulative_pct).toFixed(1)}%</span>, meta: { align: 'right' } },
    ];

    return (
        <>
            <Head title={__('Non-conformance report')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Non-conformance report')}</h1>
                    <p className="text-om-muted mt-1 text-sm">{__('Which issue types drive the most non-conformances (Pareto), and how they are dispositioned.')}</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label={__('From')}>
                        <DatePicker value={dateFrom || null} onChange={(iso) => apply({ date_from: iso ?? '' })} className="w-44" />
                    </Filter>
                    <Filter label={__('To')}>
                        <DatePicker value={dateTo || null} onChange={(iso) => apply({ date_to: iso ?? '' })} className="w-44" />
                    </Filter>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label={__('Total non-conformances')} value={pareto.total_count} />
                    <Kpi label={__('Non-conforming quantity')} value={fmt(pareto.total_nc_qty)} />
                    <Kpi label={__('Distinct types')} value={types.length} />
                    <Kpi label={__('Overdue actions')} value={overdueActions} tone={overdueActions > 0 ? 'blocked' : null} />
                </div>

                {/* Pareto: sorted bars + table */}
                <Card title={__('Non-conformance Pareto by type')}>
                    {types.length === 0 ? <Empty>{__('No non-conformances in this period.')}</Empty> : (
                        <>
                            <div className="space-y-2 mb-6">
                                {types.map((t) => (
                                    <div key={t.issue_type_id ?? t.name} className="flex items-center gap-3 text-sm">
                                        <span className="w-44 shrink-0 truncate text-om-muted" title={t.name}>{t.name}</span>
                                        <div className="flex-1 h-5 bg-om-chip rounded">
                                            <div className="h-5 bg-om-blocked rounded" style={{ width: `${(num(t.count) / maxCount) * 100}%` }} />
                                        </div>
                                        <span className="w-28 shrink-0 text-right tabular-nums text-om-muted">
                                            {t.count} <span className="text-om-faint">({num(t.pct).toFixed(1)}%)</span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <DataTable
                                data={types}
                                columns={paretoColumns}
                                searchable
                                columnToggle
                                paginated
                                searchPlaceholder={__('Search types…')}
                                emptyLabel={__('No non-conformances in this period.')}
                            />
                        </>
                    )}
                </Card>

                {/* Disposition summary: stacked proportions */}
                <Card title={__('Disposition summary')}>
                    {dispositionTotal === 0 ? <Empty>{__('No non-conformances in this period.')}</Empty> : (
                        <div className="space-y-2">
                            {Object.keys(DISPOSITION_LABELS).map((key) => {
                                const v = num(dispositionSummary[key]);
                                const pct = dispositionTotal > 0 ? (v / dispositionTotal) * 100 : 0;
                                return (
                                    <div key={key} className="flex items-center gap-3 text-sm">
                                        <span className="w-44 shrink-0 truncate text-om-muted">{__(DISPOSITION_LABELS[key])}</span>
                                        <div className="flex-1 h-5 bg-om-chip rounded">
                                            <div className={`h-5 rounded ${DISPOSITION_BAR[key]}`} style={{ width: `${pct}%` }} />
                                        </div>
                                        <span className="w-28 shrink-0 text-right tabular-nums text-om-muted">
                                            {v} <span className="text-om-faint">({pct.toFixed(1)}%)</span>
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

NonConformanceReportIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ─────────────────────────────── helpers ──────────────────────────────────── */

function Filter({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-om-muted mb-1">{label}</label>
            {children}
        </div>
    );
}

function Kpi({ label, value, sub, tone }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om-sm p-5">
            <p className="text-sm text-om-muted">{label}</p>
            <p className={`text-2xl font-bold truncate ${tone === 'blocked' ? 'text-om-blocked' : 'text-om-ink'}`} title={String(value)}>{value}</p>
            {sub && <p className="text-xs text-om-muted mt-0.5">{sub}</p>}
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om-sm p-5">
            <h2 className="text-lg font-semibold text-om-ink mb-4">{title}</h2>
            {children}
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-om-muted text-center py-8">{children}</p>;
}
