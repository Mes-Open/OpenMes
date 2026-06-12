import { Head, router, usePage } from '@inertiajs/react';
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
                        <select value={lineId ?? ''} onChange={(e) => apply({ line_id: e.target.value })} className="form-input py-1.5 text-sm min-w-[160px]">
                            <option value="">All Lines</option>
                            {lines.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                        </select>
                    </Filter>
                    <Filter label="From">
                        <input type="date" value={dateFrom ?? ''} onChange={(e) => apply({ date_from: e.target.value })} className="form-input py-1.5 text-sm" />
                    </Filter>
                    <Filter label="To">
                        <input type="date" value={dateTo ?? ''} onChange={(e) => apply({ date_to: e.target.value })} className="form-input py-1.5 text-sm" />
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
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm divide-y divide-om-line2">
                                    <thead>
                                        <tr>
                                            {['Code', 'Reason', 'Category', 'Quantity', '% of Total', 'Cumulative %'].map((h, i) => (
                                                <th key={h} className={`px-3 py-2 text-xs font-medium text-om-muted uppercase ${i >= 3 ? 'text-right' : 'text-left'}`}>{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-om-line2">
                                        {reasons.map((r) => (
                                            <tr key={r.scrap_reason_id}>
                                                <td className="px-3 py-2 font-mono text-om-muted">{r.code}</td>
                                                <td className="px-3 py-2 font-medium text-om-ink">{r.name}</td>
                                                <td className="px-3 py-2 text-om-muted">{CATEGORY_LABELS[r.category] ?? r.category}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{fmt(r.qty)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{num(r.pct).toFixed(1)}%</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{num(r.cumulative_pct).toFixed(1)}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </Card>

                {/* Scrap rate per line: simple table */}
                <Card title="Scrap rate per line">
                    {ratePerLine.length === 0 ? <Empty>No data.</Empty> : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm divide-y divide-om-line2">
                                <thead>
                                    <tr>
                                        {['Line', 'Scrap', 'Produced', 'Scrap rate'].map((h, i) => (
                                            <th key={h} className={`px-3 py-2 text-xs font-medium text-om-muted uppercase ${i >= 1 ? 'text-right' : 'text-left'}`}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-om-line2">
                                    {ratePerLine.map((r) => (
                                        <tr key={r.line_id}>
                                            <td className="px-3 py-2 font-medium text-om-ink">{r.line_name}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(r.scrap_qty)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{fmt(r.produced_qty)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums font-medium">
                                                {r.scrap_rate_pct != null ? num(r.scrap_rate_pct).toFixed(2) + '%' : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
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
