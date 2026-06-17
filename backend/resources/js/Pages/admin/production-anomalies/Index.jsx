import { useState, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';

const STATUS_STYLES = {
    pending:   'bg-om-downtime-bg text-om-downtime',
    processed: 'bg-om-running-bg text-om-running',
    dismissed: 'bg-om-chip text-om-muted',
    draft:     'bg-om-chip text-om-muted',
};

function deviation(planned, actual) {
    const p = parseFloat(planned);
    if (!p) return 0;
    return ((parseFloat(actual) - p) / p) * 100;
}

function fmt(n, decimals = 2) {
    return formatNumber(Number(n), {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

export default function ProductionAnomaliesIndex() {
    const { anomalies, filters = {}, workOrders = [] } = usePage().props;
    const { data: rows = [], links = [], meta = {} } = anomalies ?? {};

    const [workOrderId, setWorkOrderId] = useState(filters.work_order_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function applyFilter(e) {
        e.preventDefault();
        router.get('/admin/production-anomalies', { work_order_id: workOrderId, status }, { preserveState: true });
    }

    function resetFilter() {
        setWorkOrderId('');
        setStatus('');
        router.get('/admin/production-anomalies', {}, { preserveState: true });
    }

    function handleProcess(id) {
        router.post(`/admin/production-anomalies/${id}/process`, {}, { preserveScroll: true });
    }

    function handleDelete(id) {
        if (confirm('Delete this anomaly record?')) {
            router.delete(`/admin/production-anomalies/${id}`, { preserveScroll: true });
        }
    }

    const columns = useMemo(() => [
        {
            id: 'work_order',
            accessorFn: (r) => r.work_order?.order_no ?? '',
            header: 'Work Order',
            cell: ({ row }) => {
                const anomaly = row.original;
                return anomaly.work_order ? (
                    <a
                        href={`/admin/work-orders/${anomaly.work_order.id}`}
                        className="inline-flex items-center font-mono text-sm font-semibold text-om-accent bg-om-chip border border-om-line rounded px-2 py-0.5 hover:bg-om-chip hover:border-om-line transition-colors"
                    >
                        {anomaly.work_order.order_no}
                    </a>
                ) : (
                    <span className="text-om-faint">—</span>
                );
            },
        },
        {
            id: 'product_name',
            accessorKey: 'product_name',
            header: 'Product',
            cell: ({ row }) => <span className="text-om-ink">{row.original.product_name}</span>,
        },
        {
            id: 'planned_qty',
            accessorKey: 'planned_qty',
            header: 'Planned Qty',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-om-muted">{fmt(row.original.planned_qty)}</span>,
        },
        {
            id: 'actual_qty',
            accessorKey: 'actual_qty',
            header: 'Actual Qty',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-om-muted">{fmt(row.original.actual_qty)}</span>,
        },
        {
            id: 'deviation',
            accessorFn: (r) => deviation(r.planned_qty, r.actual_qty),
            header: 'Deviation',
            meta: { align: 'right' },
            cell: ({ row }) => {
                const dev = deviation(row.original.planned_qty, row.original.actual_qty);
                const devPositive = dev >= 0;
                return (
                    <span className={`font-medium ${devPositive ? 'text-om-running' : 'text-om-blocked'}`}>
                        {devPositive ? '+' : ''}{fmt(dev, 1)}%
                    </span>
                );
            },
        },
        {
            id: 'reason',
            accessorFn: (r) => r.anomaly_reason?.name ?? '—',
            header: 'Reason',
            cell: ({ row }) => <span className="text-om-muted">{row.original.anomaly_reason?.name ?? '—'}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const anomaly = row.original;
                return (
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${STATUS_STYLES[anomaly.status] ?? STATUS_STYLES.dismissed}`}>
                        {anomaly.status ? anomaly.status.charAt(0).toUpperCase() + anomaly.status.slice(1) : '—'}
                    </span>
                );
            },
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => {
                const anomaly = row.original;
                return (
                    <div className="flex items-center justify-end gap-2">
                        {anomaly.status === 'pending' && (
                            <button
                                onClick={() => handleProcess(anomaly.id)}
                                title="Process"
                                className="text-om-running hover:text-om-running p-1"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        )}
                        <button
                            onClick={() => handleDelete(anomaly.id)}
                            title="Delete"
                            className="text-om-blocked hover:text-om-blocked p-1"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                );
            },
        },
    ], []);

    return (
        <>
            <Head title="Production Anomalies" />
            <div className="max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">Production Anomalies</h1>
                    <Link
                        href="/admin/production-anomalies/create"
                        className="btn-touch btn-primary"
                    >
                        <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Record Anomaly
                    </Link>
                </div>

                {/* Filters */}
                <div className="card mb-4">
                    <form onSubmit={applyFilter} className="flex flex-wrap gap-3 items-end">
                        <div>
                            <label className="form-label">Work Order</label>
                            <Dropdown
                                className="w-full"
                                options={[{ value: '', label: 'All work orders' }, ...workOrders.map((wo) => ({ value: String(wo.id), label: wo.order_no }))]}
                                value={workOrderId == null ? '' : String(workOrderId)}
                                onChange={(v) => setWorkOrderId(v)}
                            />
                        </div>
                        <div>
                            <label className="form-label">Status</label>
                            <Dropdown
                                className="w-full"
                                options={[
                                    { value: '', label: 'All statuses' },
                                    { value: 'pending', label: 'Pending' },
                                    { value: 'processed', label: 'Processed' },
                                    { value: 'dismissed', label: 'Dismissed' },
                                ]}
                                value={status}
                                onChange={(v) => setStatus(v)}
                            />
                        </div>
                        <div className="flex gap-2">
                            <button type="submit" className="btn-touch btn-primary">Filter</button>
                            <button type="button" onClick={resetFilter} className="btn-touch btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <div className="card">
                    <DataTable
                        data={rows}
                        columns={columns}
                        searchable
                        columnToggle
                        paginated
                        searchPlaceholder="Search anomalies…"
                        emptyLabel="No anomalies recorded"
                    />

                    {/* Pagination */}
                    {links && links.length > 3 && (
                        <div className="mt-4 px-4 flex flex-wrap gap-1">
                            {links.map((link, i) => (
                                link.url ? (
                                    <button
                                        key={i}
                                        onClick={() => router.get(link.url, {}, { preserveState: true })}
                                        className={`px-3 py-1 rounded text-sm border ${link.active ? 'bg-om-ink text-om-on-ink border-om-accent' : 'border-om-line text-om-muted hover:bg-om-bg'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="px-3 py-1 rounded text-sm border border-om-line2 text-om-faint"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

ProductionAnomaliesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
