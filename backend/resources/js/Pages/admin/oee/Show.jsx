import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { DatePicker } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
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

    const columns = useMemo(() => [
        {
            id: 'record_date',
            accessorKey: 'record_date',
            header: 'Date',
            meta: { align: 'left' },
            cell: ({ row }) => <span className="font-mono">{row.original.record_date}</span>,
        },
        {
            id: 'shift',
            accessorFn: (r) => r.shift?.name ?? 'All',
            header: 'Shift',
            meta: { align: 'left' },
            cell: ({ row }) => <span className="text-om-muted">{row.original.shift?.name ?? 'All'}</span>,
        },
        {
            id: 'planned_minutes',
            accessorKey: 'planned_minutes',
            header: 'Planned',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{row.original.planned_minutes}min</span>,
        },
        {
            id: 'operating_minutes',
            accessorKey: 'operating_minutes',
            header: 'Operating',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{row.original.operating_minutes}min</span>,
        },
        {
            id: 'downtime_minutes',
            accessorKey: 'downtime_minutes',
            header: 'Downtime',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono text-om-blocked">{row.original.downtime_minutes}min</span>,
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
                const oeeClass = oeeBand(row.original.oee_pct != null ? Number(row.original.oee_pct) : null);
                return (
                    <span className={`font-bold ${oeeClass}`}>
                        {row.original.oee_pct != null ? Number(row.original.oee_pct).toFixed(1) + '%' : '—'}
                    </span>
                );
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
            cell: ({ row }) => <span className="font-mono">{row.original.scrap_qty > 0 ? formatNumber(Number(row.original.scrap_qty)) : '—'}</span>,
        },
    ], []);

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
                        <DatePicker
                            value={dateFrom || null}
                            onChange={(iso) => apply({ date_from: iso ?? '' })}
                            className="w-44"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">To</label>
                        <DatePicker
                            value={dateTo || null}
                            onChange={(iso) => apply({ date_to: iso ?? '' })}
                            className="w-44"
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
                        <DataTable
                            data={records}
                            columns={columns}
                            searchPlaceholder="Search records…"
                            emptyLabel="No OEE records for this period."
                        />
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
