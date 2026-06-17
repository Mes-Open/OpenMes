import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const STATUS_COLORS = {
    received:   'bg-om-chip text-om-accent',
    quarantine: 'bg-om-downtime-bg text-om-downtime',
    released:   'bg-om-running-bg text-om-running',
    consumed:   'bg-om-chip text-om-muted',
    expired:    'bg-om-blocked-bg text-om-blocked',
    rejected:   'bg-om-blocked-bg text-om-blocked',
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
    const statusColor = STATUS_COLORS[lot.status] ?? 'bg-om-chip text-om-muted';
    const totalConsumed = (lot.consumptions ?? []).reduce((sum, c) => sum + Number(c.quantity_consumed ?? 0), 0);
    const expiryPast = lot.expiry_date && isExpired(lot.expiry_date);
    const sourceBatchId = lot.extra_data?.source_batch_id;

    const sublotColumns = useMemo(() => [
        {
            id: 'sublot',
            accessorKey: 'sublot_number',
            header: 'Sublot',
            cell: ({ row }) => <span className="font-mono">{row.original.sublot_number}</span>,
        },
        {
            id: 'quantity',
            accessorFn: (r) => Number(r.quantity ?? 0),
            header: 'Quantity',
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="font-mono">
                    {trimQty(row.original.quantity)}{' '}
                    <span className="text-xs text-om-muted">{row.original.unit_of_measure}</span>
                </span>
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <span className="px-2 py-0.5 rounded text-xs bg-om-chip">{ucFirst(row.original.status)}</span>
            ),
        },
        {
            id: 'notes',
            accessorKey: 'notes',
            header: 'Notes',
            cell: ({ row }) => <span className="text-om-muted">{row.original.notes ?? '—'}</span>,
        },
    ], []);

    const consumptionColumns = useMemo(() => [
        {
            id: 'when',
            accessorFn: (r) => r.consumed_at,
            header: 'When',
            cell: ({ row }) => <span className="text-om-muted">{fmtDateTime(row.original.consumed_at)}</span>,
        },
        {
            id: 'work_order',
            accessorFn: (r) => {
                const wo = r.batch_step?.batch?.work_order;
                return wo ? (wo.lot_number ?? `#${wo.id}`) : '—';
            },
            header: 'Work order',
            cell: ({ row }) => {
                const wo = row.original.batch_step?.batch?.work_order;
                return (
                    <span className="font-mono text-xs">
                        {wo ? (wo.lot_number ?? `#${wo.id}`) : '—'}
                    </span>
                );
            },
        },
        {
            id: 'batch',
            accessorFn: (r) => {
                const batch = r.batch_step?.batch;
                return batch ? (batch.lot_number ?? `#${batch.id}`) : '—';
            },
            header: 'Batch',
            cell: ({ row }) => {
                const batch = row.original.batch_step?.batch;
                return (
                    <span className="font-mono text-xs">
                        {batch ? (batch.lot_number ?? `#${batch.id}`) : '—'}
                    </span>
                );
            },
        },
        {
            id: 'step',
            accessorFn: (r) => r.batch_step?.name ?? '—',
            header: 'Step',
            cell: ({ row }) => row.original.batch_step?.name ?? '—',
        },
        {
            id: 'quantity',
            accessorFn: (r) => Number(r.quantity_consumed ?? 0),
            header: 'Quantity',
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="font-mono">
                    {trimQty(row.original.quantity_consumed)}{' '}
                    <span className="text-xs text-om-muted">{lot.unit_of_measure}</span>
                </span>
            ),
        },
        {
            id: 'by',
            accessorFn: (r) => r.recorded_by?.name ?? '—',
            header: 'By',
            cell: ({ row }) => <span className="text-om-muted">{row.original.recorded_by?.name ?? '—'}</span>,
        },
    ], [lot.unit_of_measure]);

    return (
        <>
            <Head title={`Material Lot — ${lot.lot_number}`} />

            {/* Breadcrumbs */}
            <nav className="text-sm text-om-muted mb-4 flex items-center gap-1">
                <Link href="/admin/dashboard" className="hover:underline">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/material-lots" className="hover:underline">Material Lots</Link>
                <span>/</span>
                <span className="text-om-ink">{lot.lot_number}</span>
            </nav>

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink font-mono">{lot.lot_number}</h1>
                        <p className="text-om-muted text-sm mt-1">
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
                    <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-4">Info</h2>
                    <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <InfoCell label="Material">
                            {lot.material ? (
                                <>
                                    <span className="font-medium">{lot.material.name}</span>
                                    <span className="text-xs text-om-muted block font-mono">{lot.material.code}</span>
                                </>
                            ) : '—'}
                        </InfoCell>
                        <InfoCell label="Quantity">
                            <span className="font-mono">
                                {trimQty(lot.quantity_available)} / {trimQty(lot.quantity_received)}{' '}
                                <span className="text-xs text-om-muted">{lot.unit_of_measure}</span>
                            </span>
                        </InfoCell>
                        <InfoCell label="Expiry">
                            {lot.expiry_date ? (
                                <span className={expiryPast ? 'text-om-blocked font-semibold' : 'text-om-ink'}>
                                    {fmtDate(lot.expiry_date)}
                                </span>
                            ) : (
                                <span className="text-om-faint">—</span>
                            )}
                        </InfoCell>
                        <InfoCell label="Manufacturing date">
                            {fmtDate(lot.manufacturing_date) ?? '—'}
                        </InfoCell>
                        <InfoCell label="Supplier lot">{lot.supplier_lot_no ?? '—'}</InfoCell>
                        <InfoCell label="Supplier reference">{lot.supplier_reference ?? '—'}</InfoCell>
                        <InfoCell label="Inspection">
                            {lot.inspection ? (
                                <Link
                                    href={`/inspections/${lot.inspection.id}`}
                                    className="text-om-accent hover:underline"
                                >
                                    #{lot.inspection.id} ({lot.inspection.status})
                                </Link>
                            ) : (
                                <span className="text-om-faint">Not linked</span>
                            )}
                        </InfoCell>
                        <InfoCell label="Source">{lot.source?.external_name ?? '—'}</InfoCell>
                        <InfoCell label="Created by">{lot.created_by?.name ?? '—'}</InfoCell>
                    </dl>
                </div>

                {/* Sublots */}
                {lot.sublots && lot.sublots.length > 0 && (
                    <div className="card mb-6">
                        <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-4">
                            Sublots ({lot.sublots.length})
                        </h2>
                        <DataTable
                            data={lot.sublots}
                            columns={sublotColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </div>
                )}

                {/* Genealogy */}
                <div className="card">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wide">Genealogy</h2>
                        <span className="text-xs text-om-muted">
                            Total consumed:{' '}
                            <span className="font-mono font-medium">
                                {trimQty(totalConsumed)} {lot.unit_of_measure}
                            </span>
                        </span>
                    </div>

                    {/* Forward — consumed by */}
                    <div className="mb-6">
                        <h3 className="text-xs font-semibold text-om-muted uppercase mb-2">Forward — consumed by</h3>
                        {(!lot.consumptions || lot.consumptions.length === 0) ? (
                            <p className="text-sm text-om-muted italic">No consumption recorded yet.</p>
                        ) : (
                            <DataTable
                                data={lot.consumptions}
                                columns={consumptionColumns}
                                searchable={false}
                                columnToggle={false}
                                paginated={false}
                            />
                        )}
                    </div>

                    {/* Backward — sourced from */}
                    <div>
                        <h3 className="text-xs font-semibold text-om-muted uppercase mb-2">Backward — sourced from</h3>
                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-xs text-om-muted">Inspection</dt>
                                <dd className="mt-1">
                                    {lot.inspection ? (
                                        <Link
                                            href={`/inspections/${lot.inspection.id}`}
                                            className="text-om-accent hover:underline"
                                        >
                                            #{lot.inspection.id} — {ucFirst(lot.inspection.status)}
                                        </Link>
                                    ) : (
                                        <span className="text-om-faint">No inbound inspection</span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-om-muted">Supplier reference</dt>
                                <dd className="mt-1 text-om-ink">{lot.supplier_reference ?? lot.supplier_lot_no ?? '—'}</dd>
                            </div>
                        </dl>
                        {sourceBatchId && (
                            <p className="mt-3 text-xs text-om-muted">
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
            <dt className="text-xs text-om-muted uppercase">{label}</dt>
            <dd className="mt-1 text-om-ink">{children}</dd>
        </div>
    );
}
