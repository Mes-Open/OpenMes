import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const CATEGORY_LABELS = {
    material: 'Material',
    machine: 'Machine',
    method: 'Method',
    man: 'Man',
    environment: 'Environment',
    unknown: 'Unknown',
};

const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(undefined, { maximumFractionDigits: 2 });

const PARETO_COLUMNS = [
    { id: 'code', accessorKey: 'code', header: 'Code', cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code}</span> },
    { id: 'name', accessorKey: 'name', header: 'Reason', cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.name}</span> },
    { id: 'category', accessorFn: (r) => CATEGORY_LABELS[r.category] ?? r.category, header: 'Category', cell: ({ row }) => <span className="text-om-muted">{CATEGORY_LABELS[row.original.category] ?? row.original.category}</span> },
    { id: 'qty', accessorFn: (r) => num(r.qty), header: 'Quantity', cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.qty)}</span>, meta: { align: 'right' } },
    { id: 'pct', accessorFn: (r) => num(r.pct), header: '% of Total', cell: ({ row }) => <span className="tabular-nums">{num(row.original.pct).toFixed(1)}%</span>, meta: { align: 'right' } },
    { id: 'cumulative_pct', accessorFn: (r) => num(r.cumulative_pct), header: 'Cumulative %', cell: ({ row }) => <span className="tabular-nums">{num(row.original.cumulative_pct).toFixed(1)}%</span>, meta: { align: 'right' } },
];

const RATE_COLUMNS = [
    { id: 'line_name', accessorKey: 'line_name', header: 'Line', cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.line_name}</span> },
    { id: 'scrap_qty', accessorFn: (r) => num(r.scrap_qty), header: 'Scrap', cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.scrap_qty)}</span>, meta: { align: 'right' } },
    { id: 'produced_qty', accessorFn: (r) => num(r.produced_qty), header: 'Produced', cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.produced_qty)}</span>, meta: { align: 'right' } },
    { id: 'scrap_rate_pct', accessorFn: (r) => num(r.scrap_rate_pct), header: 'Scrap rate', cell: ({ row }) => <span className="tabular-nums font-medium">{row.original.scrap_rate_pct != null ? num(row.original.scrap_rate_pct).toFixed(2) + '%' : '—'}</span>, meta: { align: 'right' } },
];

export default function ScrapReportsIndex() {
    const {
        lines = [], lineId, dateFrom, dateTo,
        pareto = { total_qty: 0, total_entries: 0, reasons: [] },
        ratePerLine = [],
    } = usePage().props;

    const reasons = pareto.reasons ?? [];
    const topReason = reasons[0] ?? null;
    const maxQty = Math.max(...reasons.map((r) => num(r.qty)), 1);

    const apply = (changes) =>
        router.get('/admin/scrap-reports', { line_id: lineId ?? '', date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });

    return (
        <>
            <Head title="Scrap Reports" />
            <div className="max-w-7xl mx-auto space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-om-ink">Scrap Reports</h1>
                    <p className="text-om-muted mt-1 text-sm">Which reasons cause the most scrap (Pareto), and scrap rate per line.</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label="Line">
                        <Dropdown
                            className="min-w-[160px]"
                            options={[{ value: '', label: 'All Lines' }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))]}
                            value={lineId == null ? '' : String(lineId)}
                            onChange={(v) => apply({ line_id: v })}
                        />
                    </Filter>
                    <Filter label="From">
                        <DatePicker value={dateFrom || null} onChange={(iso) => apply({ date_from: iso ?? '' })} className="w-44" />
                    </Filter>
                    <Filter label="To">
                        <DatePicker value={dateTo || null} onChange={(iso) => apply({ date_to: iso ?? '' })} className="w-44" />
                    </Filter>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label="Total scrap quantity" value={fmt(pareto.total_qty)} />
                    <Kpi label="Scrap entries" value={fmt(pareto.total_entries)} />
                    <Kpi label="Distinct reasons" value={reasons.length} />
                    <Kpi label="Top reason" value={topReason?.name ?? '—'} sub={topReason ? `${num(topReason.pct).toFixed(1)}% of total` : null} />
                </div>

                {/* Pareto: simple sorted bars + table */}
                <Card title="Scrap Pareto by reason">
                    {reasons.length === 0 ? <Empty>No scrap reported in this period.</Empty> : (
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
                                columns={PARETO_COLUMNS}
                                searchable
                                columnToggle
                                paginated
                                searchPlaceholder="Search reasons…"
                                emptyLabel="No scrap reported in this period."
                            />
                        </>
                    )}
                </Card>

                {/* Scrap rate per line: simple table */}
                <Card title="Scrap rate per line">
                    {ratePerLine.length === 0 ? <Empty>No data.</Empty> : (
                        <DataTable
                            data={ratePerLine}
                            columns={RATE_COLUMNS}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                            emptyLabel="No data."
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
        <div className="bg-om-card rounded-om-sm shadow-sm p-5">
            <p className="text-sm text-om-muted">{label}</p>
            <p className="text-2xl font-bold text-om-ink truncate" title={String(value)}>{value}</p>
            {sub && <p className="text-xs text-om-muted mt-0.5">{sub}</p>}
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-5">
            <h2 className="text-lg font-bold text-om-ink mb-4">{title}</h2>
            {children}
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-om-muted text-center py-8">{children}</p>;
}
