import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../layouts/AppLayout';

const LINE_PALETTE = ['#2563eb', '#db2777', '#0891b2', '#16a34a', '#ea580c', '#7c3aed'];

function oeeBand(v) {
    if (v == null) return { text: 'text-gray-500', bg: 'bg-gray-400' };
    if (v >= 85) return { text: 'text-green-600', bg: 'bg-green-500' };
    if (v >= 65) return { text: 'text-yellow-600', bg: 'bg-yellow-500' };
    return { text: 'text-red-600', bg: 'bg-red-500' };
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

    return (
        <>
            <Head title="OEE Report" />
            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">OEE Report</h1>
                        <p className="text-gray-500 dark:text-gray-400 mt-1 text-sm">Overall Equipment Effectiveness — Availability × Performance × Quality</p>
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
                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label="Line">
                        <select
                            value={lineId ?? ''}
                            onChange={(e) => apply({ line_id: e.target.value })}
                            className="form-input py-1.5 text-sm min-w-[160px]"
                        >
                            <option value="">All Lines</option>
                            {lines.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>
                    </Filter>
                    <Filter label="From">
                        <input
                            type="date"
                            value={dateFrom ?? ''}
                            onChange={(e) => apply({ date_from: e.target.value })}
                            className="form-input py-1.5 text-sm"
                        />
                    </Filter>
                    <Filter label="To">
                        <input
                            type="date"
                            value={dateTo ?? ''}
                            onChange={(e) => apply({ date_to: e.target.value })}
                            className="form-input py-1.5 text-sm"
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
                                    className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-5 hover:shadow-md transition-shadow flex flex-col items-center text-center"
                                >
                                    <h3 className="font-bold text-gray-800 dark:text-gray-100 mb-3">{line.name}</h3>
                                    {/* OEE gauge (CSS arc) */}
                                    <OeeGauge value={oee != null ? Number(oee) : null} />
                                    <div className="w-full grid grid-cols-3 gap-2 mt-4">
                                        <MetricMini label="Availability" value={fmt1(s.avg_availability)} />
                                        <MetricMini label="Performance" value={s.avg_performance != null ? fmt1(s.avg_performance) : 'N/A'} />
                                        <MetricMini label="Quality" value={fmt1(s.avg_quality)} />
                                    </div>
                                    <div className="w-full mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-around text-xs text-gray-500">
                                        <span>Produced: {Number(s.total_produced).toLocaleString()}</span>
                                        <span>Scrap: {Number(s.total_scrap).toLocaleString()}</span>
                                        <span>Downtime: {s.total_downtime}min</span>
                                    </div>
                                </a>
                            );
                        })}
                    </div>
                )}

                {/* Trend Chart */}
                {trend.length > 0 && (
                    <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-5">
                        <div className="flex justify-between items-center mb-4 flex-wrap gap-2">
                            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100">OEE Trend</h2>
                            <div className="flex gap-2 flex-wrap">
                                {coloredByLine.length > 1 && (
                                    <div className="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <ModeBtn active={mode === 'combined'} onClick={() => setMode('combined')}>Combined</ModeBtn>
                                        <ModeBtn active={mode === 'per_line'} onClick={() => setMode('per_line')}>Per line</ModeBtn>
                                    </div>
                                )}
                                <div className="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                                    {[['day', 'Daily'], ['week', 'Weekly'], ['month', 'Monthly']].map(([key, label]) => (
                                        <button
                                            key={key}
                                            onClick={() => apply({ granularity: key })}
                                            className={`px-3 py-1 text-sm ${granularity === key ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600'}`}
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
                                            <span className={`text-[10px] text-gray-400 whitespace-nowrap ${granularity === 'day' ? 'rotate-[-45deg] origin-top-left' : ''}`}>
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
                                        <span className={`text-[10px] text-gray-400 whitespace-nowrap ${granularity === 'day' ? 'rotate-[-45deg] origin-top-left' : ''}`}>
                                            {granularity === 'day' ? bucket.date.substring(5) : bucket.date}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Legend */}
                        <div className="mt-2 flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                            {mode === 'combined' ? (
                                <>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-green-500 rounded inline-block" /> ≥ 85% (World-class)</span>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-yellow-500 rounded inline-block" /> 65–84%</span>
                                    <span className="flex items-center gap-1"><span className="w-3 h-3 bg-red-500 rounded inline-block" /> &lt; 65%</span>
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
                    <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-5 overflow-hidden">
                        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Daily Records</h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead className="bg-gray-50 dark:bg-slate-700">
                                    <tr>
                                        {['Date', 'Line', 'Shift', 'A%', 'P%', 'Q%', 'OEE%', 'Produced', 'Scrap', 'Downtime'].map((h) => (
                                            <th key={h} className={`px-3 py-2 text-xs font-medium text-gray-500 uppercase ${['A%', 'P%', 'Q%', 'OEE%', 'Produced', 'Scrap', 'Downtime'].includes(h) ? 'text-right' : 'text-left'}`}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {records.map((r, i) => {
                                        const band = oeeBand(r.oee_pct != null ? Number(r.oee_pct) : null);
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 font-mono">{r.record_date}</td>
                                                <td className="px-3 py-2 font-medium">{r.line?.name}</td>
                                                <td className="px-3 py-2 text-gray-500">{r.shift?.name ?? 'All'}</td>
                                                <td className="px-3 py-2 text-right">{r.availability_pct != null ? Number(r.availability_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className="px-3 py-2 text-right">{r.performance_pct != null ? Number(r.performance_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className="px-3 py-2 text-right">{r.quality_pct != null ? Number(r.quality_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className={`px-3 py-2 text-right font-bold ${band.text}`}>{r.oee_pct != null ? Number(r.oee_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className="px-3 py-2 text-right font-mono">{Number(r.total_produced).toLocaleString()}</td>
                                                <td className="px-3 py-2 text-right font-mono text-red-600">{r.scrap_qty > 0 ? Number(r.scrap_qty).toLocaleString() : '—'}</td>
                                                <td className="px-3 py-2 text-right font-mono">{r.downtime_minutes}min</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-12 text-center">
                        <p className="text-gray-500 text-lg mb-2">No OEE data available</p>
                        <p className="text-sm text-gray-400">OEE data will appear once production batches are completed and downtimes are reported.</p>
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
            <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
            {children}
        </div>
    );
}

function ModeBtn({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`px-3 py-1 text-sm ${active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600'}`}
        >
            {children}
        </button>
    );
}

function MetricMini({ label, value }) {
    return (
        <div>
            <p className="text-[10px] text-gray-500 uppercase tracking-wide leading-tight">{label}</p>
            <p className="text-sm font-bold text-gray-800 dark:text-gray-200">{value}</p>
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
                <div className="absolute inset-0 rounded-t-full bg-gray-100 dark:bg-slate-700" style={{ borderRadius: '60px 60px 0 0' }} />
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
            <span className="text-xs text-gray-400 font-medium tracking-widest">OEE</span>
        </div>
    );
}
