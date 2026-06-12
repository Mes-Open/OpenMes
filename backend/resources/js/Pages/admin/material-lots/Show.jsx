import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const STATUS_COLORS = {
    received:   'bg-blue-100 text-blue-800',
    quarantine: 'bg-amber-100 text-amber-800',
    released:   'bg-green-100 text-green-800',
    consumed:   'bg-gray-100 text-gray-600',
    expired:    'bg-red-100 text-red-800',
    rejected:   'bg-red-200 text-red-900',
};

function ucFirst(str) {
    if (!str) return '—';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function trimQty(val) {
    if (val == null) return '—';
    return parseFloat(Number(val).toFixed(4)).toString();
}

function fmtDate(str) {
    if (!str) return null;
    return str.substring(0, 10);
}

function fmtDateTime(str) {
    if (!str) return '—';
    return str.substring(0, 16).replace('T', ' ');
}

function isExpired(dateStr) {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
}

export default function MaterialLotShow({ lot }) {
    const statusColor = STATUS_COLORS[lot.status] ?? 'bg-gray-100 text-gray-700';
    const totalConsumed = (lot.consumptions ?? []).reduce((sum, c) => sum + Number(c.quantity_consumed ?? 0), 0);
    const expiryPast = lot.expiry_date && isExpired(lot.expiry_date);
    const sourceBatchId = lot.extra_data?.source_batch_id;

    return (
        <>
            <Head title={`Material Lot — ${lot.lot_number}`} />

            {/* Breadcrumbs */}
            <nav className="text-sm text-gray-500 mb-4 flex items-center gap-1">
                <Link href="/admin/dashboard" className="hover:underline">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/material-lots" className="hover:underline">Material Lots</Link>
                <span>/</span>
                <span className="text-gray-800">{lot.lot_number}</span>
            </nav>

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 font-mono">{lot.lot_number}</h1>
                        <p className="text-gray-500 text-sm mt-1">
                            Received {fmtDateTime(lot.received_at)}
                            {lot.material && (
                                <> — <span className="font-medium">{lot.material.name}</span></>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${statusColor}`}>
                            {ucFirst(lot.status)}
                        </span>
                        <Link href={`/admin/material-lots/${lot.id}/edit`} className="btn-touch btn-secondary">Edit</Link>
                        <Link href="/admin/material-lots" className="btn-touch btn-ghost">&#8592; Back</Link>
                    </div>
                </div>

                {/* Info card */}
                <div className="card mb-6">
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Info</h2>
                    <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <InfoCell label="Material">
                            {lot.material ? (
                                <>
                                    <span className="font-medium">{lot.material.name}</span>
                                    <span className="text-xs text-gray-500 block font-mono">{lot.material.code}</span>
                                </>
                            ) : '—'}
                        </InfoCell>
                        <InfoCell label="Quantity">
                            <span className="font-mono">
                                {trimQty(lot.quantity_available)} / {trimQty(lot.quantity_received)}{' '}
                                <span className="text-xs text-gray-500">{lot.unit_of_measure}</span>
                            </span>
                        </InfoCell>
                        <InfoCell label="Expiry">
                            {lot.expiry_date ? (
                                <span className={expiryPast ? 'text-red-600 font-semibold' : 'text-gray-800'}>
                                    {fmtDate(lot.expiry_date)}
                                </span>
                            ) : (
                                <span className="text-gray-400">—</span>
                            )}
                        </InfoCell>
                        <InfoCell label="Manufacturing date">
                            {fmtDate(lot.manufacturing_date) ?? '—'}
                        </InfoCell>
                        <InfoCell label="Supplier lot">{lot.supplier_lot_no ?? '—'}</InfoCell>
                        <InfoCell label="Supplier reference">{lot.supplier_reference ?? '—'}</InfoCell>
                        <InfoCell label="Source container">{lot.source_container_no ?? '—'}</InfoCell>
                        <InfoCell label="Inspection">
                            {lot.inspection ? (
                                <Link
                                    href={`/inspections/${lot.inspection.id}`}
                                    className="text-blue-700 hover:underline"
                                >
                                    #{lot.inspection.id} ({lot.inspection.status})
                                </Link>
                            ) : (
                                <span className="text-gray-400">Not linked</span>
                            )}
                        </InfoCell>
                        <InfoCell label="Source">{lot.source?.external_name ?? '—'}</InfoCell>
                        <InfoCell label="Created by">{lot.created_by?.name ?? '—'}</InfoCell>
                    </dl>
                </div>

                {/* Sublots */}
                {lot.sublots && lot.sublots.length > 0 && (
                    <div className="card mb-6">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">
                            Sublots ({lot.sublots.length})
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr className="text-xs text-gray-500 uppercase">
                                        <th className="px-3 py-2 text-left">Sublot</th>
                                        <th className="px-3 py-2 text-right">Quantity</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                        <th className="px-3 py-2 text-left">Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 text-sm">
                                    {lot.sublots.map((sub) => (
                                        <tr key={sub.id}>
                                            <td className="px-3 py-2 font-mono">{sub.sublot_number}</td>
                                            <td className="px-3 py-2 text-right font-mono">
                                                {trimQty(sub.quantity)}{' '}
                                                <span className="text-xs text-gray-500">{sub.unit_of_measure}</span>
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className="px-2 py-0.5 rounded text-xs bg-gray-100">{ucFirst(sub.status)}</span>
                                            </td>
                                            <td className="px-3 py-2 text-gray-600">{sub.notes ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Genealogy */}
                <div className="card">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Genealogy</h2>
                        <span className="text-xs text-gray-500">
                            Total consumed:{' '}
                            <span className="font-mono font-medium">
                                {trimQty(totalConsumed)} {lot.unit_of_measure}
                            </span>
                        </span>
                    </div>

                    {/* Forward — consumed by */}
                    <div className="mb-6">
                        <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Forward — consumed by</h3>
                        {(!lot.consumptions || lot.consumptions.length === 0) ? (
                            <p className="text-sm text-gray-500 italic">No consumption recorded yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead>
                                        <tr className="text-xs text-gray-500 uppercase">
                                            <th className="px-3 py-2 text-left">When</th>
                                            <th className="px-3 py-2 text-left">Work order</th>
                                            <th className="px-3 py-2 text-left">Batch</th>
                                            <th className="px-3 py-2 text-left">Step</th>
                                            <th className="px-3 py-2 text-right">Quantity</th>
                                            <th className="px-3 py-2 text-left">By</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {lot.consumptions.map((c, i) => {
                                            const step = c.batch_step;
                                            const batch = step?.batch;
                                            const wo = batch?.work_order;
                                            return (
                                                <tr key={c.id ?? i}>
                                                    <td className="px-3 py-2 text-gray-600">{fmtDateTime(c.consumed_at)}</td>
                                                    <td className="px-3 py-2 font-mono text-xs">
                                                        {wo ? (wo.lot_number ?? `#${wo.id}`) : '—'}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono text-xs">
                                                        {batch ? (batch.lot_number ?? `#${batch.id}`) : '—'}
                                                    </td>
                                                    <td className="px-3 py-2">{step?.name ?? '—'}</td>
                                                    <td className="px-3 py-2 text-right font-mono">
                                                        {trimQty(c.quantity_consumed)}{' '}
                                                        <span className="text-xs text-gray-500">{lot.unit_of_measure}</span>
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-600">{c.recorded_by?.name ?? '—'}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Backward — sourced from */}
                    <div>
                        <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Backward — sourced from</h3>
                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-xs text-gray-500">Inspection</dt>
                                <dd className="mt-1">
                                    {lot.inspection ? (
                                        <Link
                                            href={`/inspections/${lot.inspection.id}`}
                                            className="text-blue-700 hover:underline"
                                        >
                                            #{lot.inspection.id} — {ucFirst(lot.inspection.status)}
                                        </Link>
                                    ) : (
                                        <span className="text-gray-400">No inbound inspection</span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500">Supplier reference</dt>
                                <dd className="mt-1 text-gray-800">{lot.supplier_reference ?? lot.supplier_lot_no ?? '—'}</dd>
                            </div>
                        </dl>
                        {sourceBatchId && (
                            <p className="mt-3 text-xs text-gray-500">
                                Upstream source batch:{' '}
                                <span className="font-mono">#{sourceBatchId}</span>
                                {' '}— see backward genealogy API for full chain.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

MaterialLotShow.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── Helpers ─────────────────────────────────────────────────────────────── */

function InfoCell({ label, children }) {
    return (
        <div>
            <dt className="text-xs text-gray-500 uppercase">{label}</dt>
            <dd className="mt-1 text-gray-800">{children}</dd>
        </div>
    );
}
