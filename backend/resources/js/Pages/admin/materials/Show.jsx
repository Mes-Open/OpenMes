import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';

const MOVEMENT_TYPE_COLORS = {
    receipt:    'text-om-running',
    return:     'text-om-accent',
    allocation: 'text-om-downtime',
    consume:    'text-om-muted',
    scrap:      'text-om-blocked',
    adjustment: 'text-purple-700',
};

const LOT_STATUS_COLORS = {
    released:   'bg-om-running-bg text-om-running',
    quarantine: 'bg-om-blocked-bg text-om-blocked',
    expired:    'bg-om-downtime-bg text-om-downtime',
};

function fmt(val, decimals = 3) {
    return Number(val ?? 0).toFixed(decimals);
}

export default function MaterialShow({ material, lots = [], recentMovements = [], customFields = [] }) {
    const available = material.available_quantity ?? 0;
    const minStock = material.min_stock_level ?? 0;
    const stockCardBorder = available < minStock ? 'border-red-400' : 'border-blue-400';

    const lotColumns = useMemo(() => [
        {
            id: 'lot_number',
            accessorKey: 'lot_number',
            header: 'Lot',
            cell: ({ row }) => <span className="font-mono">{row.original.lot_number}</span>,
        },
        {
            id: 'supplier_lot_no',
            accessorKey: 'supplier_lot_no',
            header: 'Supplier ref',
            cell: ({ row }) => (
                <span className="text-om-muted font-mono text-xs">{row.original.supplier_lot_no ?? '—'}</span>
            ),
        },
        {
            id: 'quantity_received',
            accessorKey: 'quantity_received',
            header: 'Received',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{fmt(row.original.quantity_received)}</span>,
        },
        {
            id: 'quantity_available',
            accessorKey: 'quantity_available',
            header: 'Available',
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className={`font-mono ${row.original.quantity_available <= 0 ? 'text-om-faint' : 'font-bold'}`}>
                    {fmt(row.original.quantity_available)}
                </span>
            ),
        },
        {
            id: 'expiry_date',
            accessorKey: 'expiry_date',
            header: 'Expiry',
            cell: ({ row }) => {
                const lot = row.original;
                const expiringSoon = lot.expiry_date && isExpiringSoon(lot.expiry_date);
                return (
                    <span className={`text-xs ${expiringSoon ? 'text-om-downtime font-semibold' : 'text-om-muted'}`}>
                        {lot.expiry_date ? lot.expiry_date.substring(0, 10) : '—'}
                        {expiringSoon && <span className="ml-1">&#x23F0;</span>}
                    </span>
                );
            },
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const badge = LOT_STATUS_COLORS[row.original.status] ?? 'bg-om-chip text-om-muted';
                return (
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${badge}`}>
                        {ucFirst(row.original.status)}
                    </span>
                );
            },
        },
    ], []);

    const movementColumns = useMemo(() => [
        {
            id: 'performed_at',
            accessorKey: 'performed_at',
            header: 'When',
            cell: ({ row }) => (
                <span className="text-xs font-mono text-om-muted">
                    {row.original.performed_at ? row.original.performed_at.substring(0, 16).replace('T', ' ') : '—'}
                </span>
            ),
        },
        {
            id: 'movement_type',
            accessorKey: 'movement_type',
            header: 'Type',
            cell: ({ row }) => {
                const typeColor = MOVEMENT_TYPE_COLORS[row.original.movement_type] ?? 'text-om-muted';
                return <span className={`font-medium ${typeColor}`}>{row.original.movement_type}</span>;
            },
        },
        {
            id: 'delta',
            accessorKey: 'quantity',
            header: 'Delta',
            meta: { align: 'right' },
            cell: ({ row }) => {
                const qty = Number(row.original.quantity ?? 0);
                const qtyColor = qty > 0 ? 'text-om-running' : qty < 0 ? 'text-om-blocked' : 'text-om-muted';
                return (
                    <span className={`font-mono ${qtyColor}`}>
                        {qty > 0 ? '+' : ''}{fmt(qty)}
                    </span>
                );
            },
        },
        {
            id: 'balance_after',
            accessorKey: 'balance_after',
            header: 'Balance',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{fmt(row.original.balance_after)}</span>,
        },
        {
            id: 'source',
            accessorFn: (r) => (r.source_type ? `${r.source_type} #${r.source_id}` : '—'),
            header: 'Source',
            cell: ({ row }) => (
                <span className="text-xs text-om-muted">
                    {row.original.source_type ? `${row.original.source_type} #${row.original.source_id}` : '—'}
                </span>
            ),
        },
        {
            id: 'reason',
            accessorKey: 'reason',
            header: 'Reason',
            cell: ({ row }) => (
                <span className="text-xs text-om-muted truncate max-w-xs block" title={row.original.reason ?? ''}>
                    {(row.original.reason ?? '').substring(0, 60)}
                </span>
            ),
        },
        {
            id: 'performed_by',
            accessorFn: (r) => r.performed_by?.name ?? '—',
            header: 'By',
            cell: ({ row }) => <span className="text-xs text-om-muted">{row.original.performed_by?.name ?? '—'}</span>,
        },
    ], []);

    const bomColumns = useMemo(() => [
        {
            id: 'template',
            accessorFn: (r) => r.process_template?.name ?? '—',
            header: 'Template',
            cell: ({ row }) => <span className="text-sm">{row.original.process_template?.name ?? '—'}</span>,
        },
        {
            id: 'product',
            accessorFn: (r) => r.process_template?.product_type?.name ?? '-',
            header: 'Product',
            cell: ({ row }) => <span className="text-sm">{row.original.process_template?.product_type?.name ?? '-'}</span>,
        },
        {
            id: 'quantity_per_unit',
            accessorKey: 'quantity_per_unit',
            header: 'Qty/Unit',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-sm">{row.original.quantity_per_unit}</span>,
        },
        {
            id: 'scrap_percentage',
            accessorKey: 'scrap_percentage',
            header: 'Scrap %',
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-sm">{row.original.scrap_percentage}%</span>,
        },
    ], []);

    return (
        <>
            <Head title={`Material — ${material.name}`} />

            {/* Breadcrumbs */}
            <nav className="text-sm text-om-muted mb-4 flex items-center gap-1">
                <Link href="/admin/dashboard" className="hover:underline">Dashboard</Link>
                <span>/</span>
                <Link href="/admin/materials" className="hover:underline">Materials</Link>
                <span>/</span>
                <span className="text-om-ink">{material.name}</span>
            </nav>

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-om-ink">{material.name}</h1>
                            {material.is_active ? (
                                <span className="px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium">Active</span>
                            ) : (
                                <span className="px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium">Inactive</span>
                            )}
                        </div>
                        <p className="text-sm text-om-muted mt-1 font-mono">{material.code}</p>
                    </div>
                    <Link href={`/admin/materials/${material.id}/edit`} className="btn-touch btn-secondary">Edit</Link>
                </div>

                {/* Details + Stock grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {/* Details */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">Details</h3>
                        <dl className="space-y-3">
                            <Row label="Type"     value={material.material_type?.name ?? '—'} />
                            <Row label="Unit"     value={material.unit_of_measure ?? '—'} />
                            <Row label="Tracking" value={ucFirst(material.tracking_type)} />
                            <Row label="Default Scrap %" value={`${material.default_scrap_percentage}%`} />
                        </dl>
                    </div>

                    {/* Stock */}
                    <div className={`card border-l-4 ${stockCardBorder}`}>
                        <h3 className="text-lg font-semibold mb-4">Stock breakdown</h3>
                        <dl className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">On hand</dt>
                                <dd className="font-mono">{fmt(material.stock_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">Reserved by active batches</dt>
                                <dd className="font-mono text-om-downtime">{fmt(material.reserved_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm pt-2 border-t border-om-line2">
                                <dt className="font-medium text-om-muted">Available</dt>
                                <dd className={`font-mono font-bold ${available <= 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                                    {fmt(available)} {material.unit_of_measure}
                                </dd>
                            </div>
                            {material.min_stock_level != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>Min stock level</dt>
                                    <dd className="font-mono">{fmt(material.min_stock_level)} {material.unit_of_measure}</dd>
                                </div>
                            )}
                            {material.unit_price != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>Stock value</dt>
                                    <dd className="font-mono">
                                        {Number(material.stock_quantity * material.unit_price).toFixed(2)} {material.price_currency}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {/* External System */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">External System</h3>
                        {material.external_code ? (
                            <dl className="space-y-3">
                                <Row label="System"        value={material.external_system} />
                                <Row label="External Code" value={<span className="font-mono">{material.external_code}</span>} />
                            </dl>
                        ) : (
                            <p className="text-sm text-om-muted">No external system linked.</p>
                        )}

                        {material.sources && material.sources.length > 0 && (
                            <>
                                <h4 className="text-sm font-semibold mt-4 mb-2">Additional Sources</h4>
                                {material.sources.map((src) => (
                                    <div key={src.id} className="p-2 bg-om-panel rounded mb-2 text-sm">
                                        <span className="font-medium">{src.integration_config?.system_name ?? 'Unknown'}</span>:{' '}
                                        <span className="font-mono">{src.external_code}</span>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                </div>

                {/* Custom fields */}
                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={material.custom_fields ?? {}} />
                </div>

                {/* Lots */}
                {lots.length > 0 && (
                    <div className="card mb-6">
                        <h3 className="text-lg font-semibold mb-4">
                            Lots <span className="text-sm font-normal text-om-faint">({lots.length})</span>
                        </h3>
                        <DataTable
                            data={lots}
                            columns={lotColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </div>
                )}

                {/* Recent stock movements */}
                {recentMovements.length > 0 && (
                    <div className="card mb-6">
                        <h3 className="text-lg font-semibold mb-4">Recent stock movements</h3>
                        <DataTable
                            data={recentMovements}
                            columns={movementColumns}
                            searchPlaceholder="Search movements…"
                        />
                    </div>
                )}

                {/* BOM usage */}
                {material.bom_items && material.bom_items.length > 0 && (
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">
                            Used in BOM ({material.bom_items.length} templates)
                        </h3>
                        <DataTable
                            data={material.bom_items}
                            columns={bomColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

MaterialShow.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── Helpers ─────────────────────────────────────────────────────────────── */

function ucFirst(str) {
    if (!str) return '—';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function isExpiringSoon(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = (d - now) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 30;
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between">
            <dt className="text-sm text-om-muted">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}
