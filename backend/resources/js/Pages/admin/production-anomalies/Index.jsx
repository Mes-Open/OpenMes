import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
                            <select
                                value={workOrderId}
                                onChange={(e) => setWorkOrderId(e.target.value)}
                                className="form-input"
                            >
                                <option value="">All work orders</option>
                                {workOrders.map((wo) => (
                                    <option key={wo.id} value={wo.id}>{wo.order_no}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="form-label">Status</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="form-input"
                            >
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processed">Processed</option>
                                <option value="dismissed">Dismissed</option>
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <button type="submit" className="btn-touch btn-primary">Filter</button>
                            <button type="button" onClick={resetFilter} className="btn-touch btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <div className="card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-om-line2">
                                    <th className="text-left py-3 px-4 font-semibold text-om-muted">Work Order</th>
                                    <th className="text-left py-3 px-4 font-semibold text-om-muted">Product</th>
                                    <th className="text-right py-3 px-4 font-semibold text-om-muted">Planned Qty</th>
                                    <th className="text-right py-3 px-4 font-semibold text-om-muted">Actual Qty</th>
                                    <th className="text-right py-3 px-4 font-semibold text-om-muted">Deviation</th>
                                    <th className="text-left py-3 px-4 font-semibold text-om-muted">Reason</th>
                                    <th className="text-left py-3 px-4 font-semibold text-om-muted">Status</th>
                                    <th className="text-right py-3 px-4 font-semibold text-om-muted">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line2">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-om-muted">
                                            <svg className="mx-auto h-10 w-10 text-om-faint mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                            <p className="font-medium">No anomalies recorded</p>
                                            <Link href="/admin/production-anomalies/create" className="inline-block mt-3 btn-touch btn-primary">
                                                Record Anomaly
                                            </Link>
                                        </td>
                                    </tr>
                                ) : rows.map((anomaly) => {
                                    const dev = deviation(anomaly.planned_qty, anomaly.actual_qty);
                                    const devPositive = dev >= 0;
                                    return (
                                        <tr key={anomaly.id} className="hover:bg-om-bg">
                                            <td className="py-3 px-4">
                                                {anomaly.work_order ? (
                                                    <a
                                                        href={`/admin/work-orders/${anomaly.work_order.id}`}
                                                        className="inline-flex items-center font-mono text-sm font-semibold text-om-accent bg-om-chip border border-om-line rounded px-2 py-0.5 hover:bg-om-chip hover:border-om-line transition-colors"
                                                    >
                                                        {anomaly.work_order.order_no}
                                                    </a>
                                                ) : (
                                                    <span className="text-om-faint">—</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-om-ink">{anomaly.product_name}</td>
                                            <td className="py-3 px-4 text-right text-om-muted">{fmt(anomaly.planned_qty)}</td>
                                            <td className="py-3 px-4 text-right text-om-muted">{fmt(anomaly.actual_qty)}</td>
                                            <td className={`py-3 px-4 text-right font-medium ${devPositive ? 'text-om-running' : 'text-om-blocked'}`}>
                                                {devPositive ? '+' : ''}{fmt(dev, 1)}%
                                            </td>
                                            <td className="py-3 px-4 text-om-muted">{anomaly.anomaly_reason?.name ?? '—'}</td>
                                            <td className="py-3 px-4">
                                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${STATUS_STYLES[anomaly.status] ?? STATUS_STYLES.dismissed}`}>
                                                    {anomaly.status ? anomaly.status.charAt(0).toUpperCase() + anomaly.status.slice(1) : '—'}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
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
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {links && links.length > 3 && (
                        <div className="mt-4 px-4 flex flex-wrap gap-1">
                            {links.map((link, i) => (
                                link.url ? (
                                    <button
                                        key={i}
                                        onClick={() => router.get(link.url, {}, { preserveState: true })}
                                        className={`px-3 py-1 rounded text-sm border ${link.active ? 'bg-om-ink text-white border-om-accent' : 'border-om-line text-om-muted hover:bg-om-bg'}`}
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
