import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const categoryLabels = () => ({
    material: __('Material'),
    machine: __('Machine'),
    method: __('Method'),
    man: __('Man'),
    environment: __('Environment'),
    unknown: __('Unknown'),
});

const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(undefined, { maximumFractionDigits: 2 });

export default function ScrapReportsIndex() {
    const {
        lines = [], lineId, dateFrom, dateTo,
        pareto = { total_qty: 0, total_entries: 0, reasons: [] },
        ratePerLine = [],
    } = usePage().props;

    const categories = categoryLabels();
    const reasons = pareto.reasons ?? [];
    const topReason = reasons[0] ?? null;
    const maxQty = Math.max(...reasons.map((r) => num(r.qty)), 1);

    const apply = (changes) =>
        router.get('/admin/scrap-reports', { line_id: lineId ?? '', date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });

    const paretoColumns = [
        { id: 'code', accessorKey: 'code', header: __('Code'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code}</span> },
        { id: 'name', accessorKey: 'name', header: __('Reason'), cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.name}</span> },
        { id: 'category', accessorFn: (r) => categories[r.category] ?? r.category, header: __('Category'), cell: ({ row }) => <span className="text-om-muted">{categories[row.original.category] ?? row.original.category}</span> },
        { id: 'qty', accessorFn: (r) => num(r.qty), header: __('Quantity'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.qty)}</span>, meta: { align: 'right' } },
        { id: 'pct', accessorFn: (r) => num(r.pct), header: __('% of Total'), cell: ({ row }) => <span className="tabular-nums">{num(row.original.pct).toFixed(1)}%</span>, meta: { align: 'right' } },
        { id: 'cumulative_pct', accessorFn: (r) => num(r.cumulative_pct), header: __('Cumulative %'), cell: ({ row }) => <span className="tabular-nums">{num(row.original.cumulative_pct).toFixed(1)}%</span>, meta: { align: 'right' } },
    ];

    const rateColumns = [
        { id: 'line_name', accessorKey: 'line_name', header: __('Line'), cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.line_name}</span> },
        { id: 'scrap_qty', accessorFn: (r) => num(r.scrap_qty), header: __('Scrap'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.scrap_qty)}</span>, meta: { align: 'right' } },
        { id: 'produced_qty', accessorFn: (r) => num(r.produced_qty), header: __('Produced'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.produced_qty)}</span>, meta: { align: 'right' } },
        { id: 'scrap_rate_pct', accessorFn: (r) => num(r.scrap_rate_pct), header: __('Scrap rate'), cell: ({ row }) => <span className="tabular-nums font-medium">{row.original.scrap_rate_pct != null ? num(row.original.scrap_rate_pct).toFixed(2) + '%' : '—'}</span>, meta: { align: 'right' } },
    ];

    return (
        <>
            <Head title={__('Scrap Reports')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Scrap Reports')}</h1>
                    <p className="text-om-muted mt-1 text-sm">{__('Which reasons cause the most scrap (Pareto), and scrap rate per line.')}</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label={__('Line')}>
                        <Dropdown
                            className="min-w-[160px]"
                            options={[{ value: '', label: __('All Lines') }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))]}
                            value={lineId == null ? '' : String(lineId)}
                            onChange={(v) => apply({ line_id: v })}
                        />
                    </Filter>
                    <Filter label={__('From')}>
                        <DatePicker value={dateFrom || null} onChange={(iso) => apply({ date_from: iso ?? '' })} className="w-44" />
                    </Filter>
                    <Filter label={__('To')}>
                        <DatePicker value={dateTo || null} onChange={(iso) => apply({ date_to: iso ?? '' })} className="w-44" />
                    </Filter>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label={__('Total scrap quantity')} value={fmt(pareto.total_qty)} />
                    <Kpi label={__('Scrap entries')} value={fmt(pareto.total_entries)} />
                    <Kpi label={__('Distinct reasons')} value={reasons.length} />
                    <Kpi label={__('Top reason')} value={topReason?.name ?? '—'} sub={topReason ? __(':pct% of total', { pct: num(topReason.pct).toFixed(1) }) : null} />
                </div>

                {/* Pareto: simple sorted bars + table */}
                <Card title={__('Scrap Pareto by reason')}>
                    {reasons.length === 0 ? <Empty>{__('No scrap reported in this period.')}</Empty> : (
                        <>
                            <div className="space-y-2 mb-6">
                                {reasons.map((r) => (
                                    <div key={r.scrap_reason_id} className="flex items-center gap-3 text-sm">
                                        <span className="w-44 shrink-0 truncate text-om-muted" title={r.name}>{r.name}</span>
                                        <div className="flex-1 h-5 bg-om-chip rounded">
                                            <div className="h-5 bg-om-blocked rounded" style={{ width: `${(num(r.qty) / maxQty) * 100}%` }} />
                                        </div>
                                        <span className="w-28 shrink-0 text-right tabular-nums text-om-muted">
                                            {fmt(r.qty)} <span className="text-om-faint">({num(r.pct).toFixed(1)}%)</span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <DataTable
                                data={reasons}
                                columns={paretoColumns}
                                searchable
                                columnToggle
                                paginated
                                searchPlaceholder={__('Search reasons…')}
                                emptyLabel={__('No scrap reported in this period.')}
                            />
                        </>
                    )}
                </Card>

                {/* Scrap rate per line: simple table */}
                <Card title={__('Scrap rate per line')}>
                    {ratePerLine.length === 0 ? <Empty>{__('No data.')}</Empty> : (
                        <DataTable
                            data={ratePerLine}
                            columns={rateColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                            emptyLabel={__('No data.')}
                        />
                    )}
                </Card>
            </div>
        </>
    );
}

ScrapReportsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ─────────────────────────────── helpers ──────────────────────────────────── */

function Filter({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-om-muted mb-1">{label}</label>
            {children}
        </div>
    );
}

function Kpi({ label, value, sub }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om-sm p-5">
            <p className="text-sm text-om-muted">{label}</p>
            <p className="text-2xl font-bold text-om-ink truncate" title={String(value)}>{value}</p>
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
