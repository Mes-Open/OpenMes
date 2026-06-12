import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';

const KIND_BG = { blue: 'bg-om-accent', amber: 'bg-om-downtime', red: 'bg-om-blocked' };
const KIND_TEXT = { blue: 'text-om-accent', amber: 'text-om-downtime', red: 'text-om-blocked' };
const KIND_BADGE = { blue: 'bg-om-chip text-om-accent', amber: 'bg-om-downtime-bg text-om-downtime', red: 'bg-om-blocked-bg text-om-blocked' };

function oeeBand(v) {
    if (v == null) return 'text-om-muted';
    if (v >= 85) return 'text-om-running';
    if (v >= 65) return 'text-om-downtime';
    return 'text-om-blocked';
}

export default function OeeShow() {
    const { line, records = [], downtimeByReason = [], dateFrom, dateTo } = usePage().props;

    const apply = (changes) =>
        router.get(`/admin/oee/${line.id}`, { date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });

    const maxMinutes = Math.max(...downtimeByReason.map((d) => d.total_minutes ?? 0), 1);

    return (
        <>
            <Head title={`OEE — ${line.name}`} />
            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex justify-between items-start flex-wrap gap-3">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{line.name} — OEE</h1>
                        <p className="text-om-muted mt-1 text-sm">{dateFrom} to {dateTo}</p>
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        <a
                            href={`/admin/oee/print?line_id=${line.id}&date_from=${dateFrom}&date_to=${dateTo}`}
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
                            href={`/admin/oee/print/pdf?line_id=${line.id}&date_from=${dateFrom}&date_to=${dateTo}`}
                            className="btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            </svg>
                            Download PDF
                        </a>
                        <Link href="/admin/oee" className="btn-touch btn-secondary text-sm">Back to OEE</Link>
                    </div>
                </div>

                {/* Date filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-4">
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">From</label>
                        <input
                            type="date"
                            value={dateFrom ?? ''}
                            onChange={(e) => apply({ date_from: e.target.value })}
                            className="form-input py-1.5 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">To</label>
                        <input
                            type="date"
                            value={dateTo ?? ''}
                            onChange={(e) => apply({ date_to: e.target.value })}
                            className="form-input py-1.5 text-sm"
                        />
                    </div>
                </div>

                {/* Downtime by Reason */}
                {downtimeByReason.length > 0 && (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-5">
                        <h2 className="text-lg font-bold text-om-ink mb-4">Downtime by Reason</h2>
                        <div className="space-y-2">
                            {downtimeByReason.map((item, i) => {
                                const bg = KIND_BG[item.kind_color] ?? 'bg-om-blocked';
                                const badge = KIND_BADGE[item.kind_color] ?? 'bg-om-blocked-bg text-om-blocked';
                                const pct = (item.total_minutes / maxMinutes) * 100;
                                return (
                                    <div key={i} className="flex items-center gap-3">
                                        <div className="w-44 shrink-0">
                                            <span className="text-sm font-medium text-om-muted">{item.reason}</span>
                                            <span className={`text-xs ml-1 px-1.5 py-0.5 rounded font-medium ${badge}`}>{item.kind_label}</span>
                                        </div>
                                        <div className="flex-1 bg-om-chip rounded-full h-5 overflow-hidden">
                                            <div className={`h-full rounded-full ${bg}`} style={{ width: `${pct}%` }} />
                                        </div>
                                        <div className="w-28 text-right shrink-0">
                                            <span className="text-sm font-mono font-bold text-om-muted">{item.total_minutes}min</span>
                                            <span className="text-xs text-om-faint ml-1">({item.count}×)</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Records Table */}
                {records.length > 0 ? (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-5 overflow-hidden">
                        <h2 className="text-lg font-bold text-om-ink mb-4">Daily Records</h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-om-line2 text-sm">
                                <thead className="bg-om-panel">
                                    <tr>
                                        {['Date', 'Shift', 'Planned', 'Operating', 'Downtime', 'A%', 'P%', 'Q%', 'OEE%', 'Produced', 'Scrap'].map((h) => (
                                            <th key={h} className={`px-3 py-2 text-xs font-medium text-om-muted uppercase ${['Planned', 'Operating', 'Downtime', 'A%', 'P%', 'Q%', 'OEE%', 'Produced', 'Scrap'].includes(h) ? 'text-right' : 'text-left'}`}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-om-line2">
                                    {records.map((r, i) => {
                                        const oeeClass = oeeBand(r.oee_pct != null ? Number(r.oee_pct) : null);
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 font-mono">{r.record_date}</td>
                                                <td className="px-3 py-2 text-om-muted">{r.shift?.name ?? 'All'}</td>
                                                <td className="px-3 py-2 text-right font-mono">{r.planned_minutes}min</td>
                                                <td className="px-3 py-2 text-right font-mono">{r.operating_minutes}min</td>
                                                <td className="px-3 py-2 text-right font-mono text-om-blocked">{r.downtime_minutes}min</td>
                                                <td className="px-3 py-2 text-right">{r.availability_pct != null ? Number(r.availability_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className="px-3 py-2 text-right">{r.performance_pct != null ? Number(r.performance_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className="px-3 py-2 text-right">{r.quality_pct != null ? Number(r.quality_pct).toFixed(1) + '%' : '—'}</td>
                                                <td className={`px-3 py-2 text-right font-bold ${oeeClass}`}>
                                                    {r.oee_pct != null ? Number(r.oee_pct).toFixed(1) + '%' : '—'}
                                                </td>
                                                <td className="px-3 py-2 text-right font-mono">{formatNumber(Number(r.total_produced))}</td>
                                                <td className="px-3 py-2 text-right font-mono">{r.scrap_qty > 0 ? formatNumber(Number(r.scrap_qty)) : '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : (
                    <div className="bg-om-card rounded-om-sm shadow-sm p-8 text-center">
                        <p className="text-om-muted">No OEE records for this period.</p>
                    </div>
                )}
            </div>
        </>
    );
}

OeeShow.layout = (page) => <AppLayout>{page}</AppLayout>;
