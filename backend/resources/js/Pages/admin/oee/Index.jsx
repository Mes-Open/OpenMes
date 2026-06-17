import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { useMemo, useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';

const LINE_PALETTE = ['#2563eb', '#db2777', '#0891b2', '#16a34a', '#ea580c', '#7c3aed'];

function oeeBand(v) {
    if (v == null) return { text: 'text-om-muted', bg: 'bg-om-faintest' };
    if (v >= 85) return { text: 'text-om-running', bg: 'bg-om-running' };
    if (v >= 65) return { text: 'text-om-downtime', bg: 'bg-om-downtime' };
    return { text: 'text-om-blocked', bg: 'bg-om-blocked' };
}

function fmt1(v) {
    return v != null ? Number(v).toFixed(1) + '%' : '—';
}

export default function OeeIndex() {
    const { lines = [], lineId, dateFrom, dateTo, records = [], summary = {}, trend = [], trendByLine = [], granularity } = usePage().props;

    const [mode, setMode] = useState(lineId ? 'per_line' : 'combined');

    const apply = (changes) =>
        router.get('/admin/oee', { line_id: lineId ?? '', date_from: dateFrom, date_to: dateTo, granularity, ...changes }, { preserveState: false });

    // Attach colors to per-line series
    const coloredByLine = trendByLine.map((l, i) => ({ ...l, color: LINE_PALETTE[i % LINE_PALETTE.length] }));

    const maxTrend = Math.max(...trend.map((d) => d.oee ?? 0), 1);

    const recordColumns = useMemo(() => [
        {
            id: 'record_date',
            accessorKey: 'record_date',
            header: 'Date',
            cell: ({ row }) => <span className="font-mono">{row.original.record_date}</span>,
        },
        {
            id: 'line',
            accessorFn: (r) => r.line?.name,
            header: 'Line',
            cell: ({ row }) => <span className="font-medium">{row.original.line?.name}</span>,
        },
        {
            id: 'shift',
            accessorFn: (r) => r.shift?.name ?? 'All',
            header: 'Shift',
            cell: ({ row }) => <span className="text-om-muted">{row.original.shift?.name ?? 'All'}</span>,
        },
        {
            id: 'availability_pct',
            accessorKey: 'availability_pct',
            header: 'A%',
            meta: { align: 'right' },
            cell: ({ row }) => (row.original.availability_pct != null ? Number(row.original.availability_pct).toFixed(1) + '%' : '—'),
        },
        {
            id: 'performance_pct',
            accessorKey: 'performance_pct',
            header: 'P%',
            meta: { align: 'right' },
            cell: ({ row }) => (row.original.performance_pct != null ? Number(row.original.performance_pct).toFixed(1) + '%' : '—'),
        },
        {
            id: 'quality_pct',
            accessorKey: 'quality_pct',
            header: 'Q%',
            meta: { align: 'right' },
            cell: ({ row }) => (row.original.quality_pct != null ? Number(row.original.quality_pct).toFixed(1) + '%' : '—'),
        },
        {
            id: 'oee_pct',
            accessorKey: 'oee_pct',
            header: 'OEE%',
            meta: { align: 'right' },
            cell: ({ row }) => {
                const r = row.original;
                const band = oeeBand(r.oee_pct != null ? Number(r.oee_pct) : null);
                return <span className={`font-bold ${band.text}`}>{r.oee_pct != null ? Number(r.oee_pct).toFixed(1) + '%' : '—'}</span>;
            },
        },
        {
            id: 'total_produced',
            accessorKey: 'total_produced',
            header: 'Produced',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{formatNumber(Number(row.original.total_produced))}</span>,
        },
        {
            id: 'scrap_qty',
            accessorKey: 'scrap_qty',
            header: 'Scrap',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono text-om-blocked">{row.original.scrap_qty > 0 ? formatNumber(Number(row.original.scrap_qty)) : '—'}</span>,
        },
        {
            id: 'downtime_minutes',
            accessorKey: 'downtime_minutes',
            header: 'Downtime',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{row.original.downtime_minutes}min</span>,
        },
    ], []);

    return (
        <>
            <Head title="OEE Report" />
            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">OEE Report</h1>
                        <p className="text-om-muted mt-1 text-sm">Overall Equipment Effectiveness — Availability × Performance × Quality</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={`/admin/oee/print?${new URLSearchParams(Object.fromEntries(Object.entries({ line_id: lineId, date_from: dateFrom, date_to: dateTo }).filter(([, v]) => v != null && v !== '')))}`}
                            target="_blank"
                            rel="noreferrer"
                            className="btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print
                        </a>
                        <a
                            href={`/admin/oee/print/pdf?${new URLSearchParams(Object.fromEntries(Object.entries({ line_id: lineId, date_from: dateFrom, date_to: dateTo }).filter(([, v]) => v != null && v !== '')))}`}
                            className="btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            </svg>
                            Download PDF
                        </a>
                    </div>
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
                        <DatePicker
                            value={dateFrom || null}
                            onChange={(iso) => apply({ date_from: iso ?? '' })}
                            className="w-44"
                        />
                    </Filter>
                    <Filter label="To">
                        <DatePicker
                            value={dateTo || null}
                            onChange={(iso) => apply({ date_to: iso ?? '' })}
                            className="w-44"
                        />
                    </Filter>
                </div>

                {/* Summary Cards */}
                {Object.keys(summary).length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {lines.map((line) => {
                            const s = summary[line.id];
                            if (!s) return null;
                            const oee = s.avg_oee != null ? Number(s.avg_oee).toFixed(1) : null;
                            const band = oeeBand(oee != null ? Number(oee) : null);
                            return (
                                <a
                                    key={line.id}
                                    href={`/admin/oee/${line.id}?date_from=${dateFrom}&date_to=${dateTo}`}
                                    className="bg-om-card rounded-om-sm shadow-sm p-5 hover:shadow-md transition-shadow flex flex-col items-center text-center"
                                >
                                    <h3 className="font-bold text-om-ink mb-3">{line.name}</h3>
                                    {/* OEE gauge (CSS arc) */}
                                    <OeeGauge value={oee != null ? Number(oee) : null} />
                                    <div className="w-full grid grid-cols-3 gap-2 mt-4">
                                        <MetricMini label="Availability" value={fmt1(s.avg_availability)} />
                                        <MetricMini label="Performance" value={s.avg_performance != null ? fmt1(s.avg_performance) : 'N/A'} />
                                        <MetricMini label="Quality" value={fmt1(s.avg_quality)} />
                                    </div>
                                    <div className="w-full mt-3 pt-3 border-t border-om-line2 flex justify-around text-xs text-om-muted">
                                        <span>Produced: {formatNumber(Number(s.total_produced))}</span>
                                        <span>Scrap: {formatNumber(Number(s.total_scrap))}</span>
                                        <span>Downtime: {s.total_downtime}min</span>
                                    </div>
                                </a>
                            );
                        })}
                    </div>
                )}

                {/* Trend Chart */}
                {trend.length > 0 && (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-5">
                        <div className="flex justify-between items-center mb-4 flex-wrap gap-2">
                            <h2 className="text-lg font-bold text-om-ink">OEE Trend</h2>
                            <div className="flex gap-2 flex-wrap">
                                {coloredByLine.length > 1 && (
                                    <div className="inline-flex rounded-om-sm border border-om-line2 overflow-hidden">
                                        <ModeBtn active={mode === 'combined'} onClick={() => setMode('combined')}>Combined</ModeBtn>
                                        <ModeBtn active={mode === 'per_line'} onClick={() => setMode('per_line')}>Per line</ModeBtn>
                                    </div>
                                )}
                                <div className="inline-flex rounded-om-sm border border-om-line2 overflow-hidden">
                                    {[['day', 'Daily'], ['week', 'Weekly'], ['month', 'Monthly']].map(([key, label]) => (
                                        <button
                                            key={key}
                                            onClick={() => apply({ granularity: key })}
                                            className={`px-3 py-1 text-sm ${granularity === key ? 'bg-om-ink text-white' : 'bg-om-card text-om-muted hover:bg-om-bg'}`}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Combined bars */}
                        {mode === 'combined' && (
                            <div className="h-48 flex items-end gap-1 overflow-x-auto pb-6">
                                {trend.map((day, i) => {
                                    const band = oeeBand(day.oee);
                                    const height = Math.max((day.oee / 100) * 160, 2);
                                    return (
                                        <div key={i} className="flex-1 min-w-[20px] flex flex-col items-center gap-1">
                                            <span className={`text-xs font-bold ${band.text}`}>{day.oee}%</span>
                                            <div
                                                className={`w-full rounded-t transition-all ${band.bg}`}
                                                style={{ height: `${height}px` }}
                                            />
                                            <span className={`text-[10px] text-om-faint whitespace-nowrap ${granularity === 'day' ? 'rotate-[-45deg] origin-top-left' : ''}`}>
                                                {granularity === 'day' ? day.date.substring(5) : day.date}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* Per-line grouped bars */}
                        {mode === 'per_line' && coloredByLine.length > 0 && (
                            <div className="h-48 flex items-end gap-3 overflow-x-auto pb-6">
                                {(coloredByLine[0]?.points ?? []).map((bucket, b) => (
                                    <div key={b} className="flex-1 min-w-[40px] flex flex-col items-center gap-1">
                                        <div className="flex items-end gap-px h-40 w-full justify-center">
                                            {coloredByLine.map((line) => {
                                                const pt = line.points[b] ?? { oee: 0 };
                                                const h = Math.max((pt.oee / 100) * 140, 2);
                                                return (
                                                    <div key={line.line_id} className="flex flex-col items-center justify-end" style={{ width: 18 }} title={`${line.line_name}: ${pt.oee}%`}>
                                                        <span className="text-[9px] font-semibold" style={{ color: line.color }}>{pt.oee}%</span>
                                                        <div className="rounded-t transition-all" style={{ background: line.color, height: `${h}px`, width: '100%' }} />
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        <span className={`text-[10px] text-om-faint whitespace-nowrap ${granularity === 'day' ? 'rotate-[-45deg] origin-top-left' : ''}`}>
                                            {granularity === 'day' ? bucket.date.substring(5) : bucket.date}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Legend */}
                        <div className="mt-2 flex items-center gap-4 text-xs text-om-muted flex-wrap">
                            {mode === 'combined' ? (
                                <>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-om-running rounded inline-block" /> ≥ 85% (World-class)</span>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-om-downtime rounded inline-block" /> 65–84%</span>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-om-blocked rounded inline-block" /> &lt; 65%</span>
                                </>
                            ) : (
                                coloredByLine.map((l) => (
                                    <span key={l.line_id} className="flex items-center gap-1">
                                        <span className="w-3 h-3 rounded inline-block" style={{ background: l.color }} />
                                        {l.line_name}
                                    </span>
                                ))
                            )}
                        </div>
                    </div>
                )}

                {/* Detail Table */}
                {records.length > 0 ? (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-5 overflow-hidden">
                        <h2 className="text-lg font-bold text-om-ink mb-4">Daily Records</h2>
                        <DataTable
                            data={records}
                            columns={recordColumns}
                            searchPlaceholder="Search records…"
                        />
                    </div>
                ) : (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-12 text-center">
                        <p className="text-om-muted text-lg mb-2">No OEE data available</p>
                        <p className="text-sm text-om-faint">OEE data will appear once production batches are completed and downtimes are reported.</p>
                    </div>
                )}
            </div>
        </>
    );
}

OeeIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ---- helpers ---- */

function Filter({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-om-muted mb-1">{label}</label>
            {children}
        </div>
    );
}

function ModeBtn({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`px-3 py-1 text-sm ${active ? 'bg-om-ink text-white' : 'bg-om-card text-om-muted hover:bg-om-bg'}`}
        >
            {children}
        </button>
    );
}

function MetricMini({ label, value }) {
    return (
        <div>
            <p className="text-[10px] text-om-muted uppercase tracking-wide leading-tight">{label}</p>
            <p className="text-sm font-bold text-om-ink">{value}</p>
        </div>
    );
}

/** Lightweight CSS semicircle gauge (no SVG lib needed). */
function OeeGauge({ value }) {
    const pct = value != null ? Math.min(Math.max(Number(value), 0), 100) : null;
    const band = oeeBand(pct);
    // Map 0-100% to 0deg-180deg rotation of the needle half
    const deg = pct != null ? (pct / 100) * 180 : 0;

    return (
        <div className="flex flex-col items-center gap-1">
            {/* Semicircle track */}
            <div className="relative" style={{ width: 120, height: 60, overflow: 'hidden' }}>
                {/* Track */}
                <div className="absolute inset-0 rounded-t-full bg-om-chip" style={{ borderRadius: '60px 60px 0 0' }} />
                {/* Fill — clip to semicircle */}
                {pct != null && (
                    <div
                        className={`absolute inset-0 ${band.bg}`}
                        style={{
                            borderRadius: '60px 60px 0 0',
                            clipPath: `polygon(50% 100%, 0% 100%, 0% 0%, ${50 - 50 * Math.cos((deg * Math.PI) / 180)}% ${100 - 100 * Math.sin((deg * Math.PI) / 180)}%)`,
                            opacity: 0.85,
                        }}
                    />
                )}
                {/* Center text */}
                <div className="absolute bottom-0 inset-x-0 flex justify-center">
                    <span className={`text-lg font-black leading-none ${band.text}`}>{pct != null ? pct.toFixed(1) + '%' : '—'}</span>
                </div>
            </div>
            <span className="text-xs text-om-faint font-medium tracking-widest">OEE</span>
        </div>
    );
}
