import { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button, Modal, StatusBadge, TextField } from '@openmes/ui';
import { __ } from '../../../lib/i18n';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { materialFields, materialInitial } from './fields';
import { materialLotStatusBadge } from '../material-lots/fields';
import AppDataTable from '../../../components/AppDataTable';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
// Explicit extension: `components/engineeringDocuments.js` (the helper module)
// differs only in case, so an extensionless import resolves to the wrong file on a
// case-insensitive filesystem (macOS) and breaks the build.
import EngineeringDocuments from '../../../components/EngineeringDocuments.jsx';
import PageTrail from '../../../components/PageTrail';

const MOVEMENT_TYPE_COLORS = {
    receipt:    'text-om-running',
    return:     'text-om-accent',
    allocation: 'text-om-downtime',
    consume:    'text-om-muted',
    scrap:      'text-om-blocked',
    adjustment: 'text-purple-700',
};

function fmt(val, decimals = 3) {
    return Number(val ?? 0).toFixed(decimals);
}

export default function MaterialShow({ material, lots = [], recentMovements = [], customFields = [], materialTypes }) {
    const drawer = useResourceDrawer();
    const [receiving, setReceiving] = useState(false);
    const receipt = useForm({ quantity: '', reference: '' });
    const receive = (event) => {
        event.preventDefault();
        receipt.post(`/admin/materials/${material.id}/receipts`, {
            preserveScroll: true,
            onSuccess: () => { setReceiving(false); receipt.reset(); },
        });
    };
    const available = material.available_quantity ?? 0;

    const lotColumns = useMemo(() => [
        {
            id: 'lot_number',
            accessorKey: 'lot_number',
            header: __('Lot'),
            cell: ({ row }) => <span className="font-mono">{row.original.lot_number}</span>,
        },
        {
            id: 'supplier_lot_no',
            accessorKey: 'supplier_lot_no',
            header: __('Supplier ref'),
            cell: ({ row }) => (
                <span className="text-om-muted font-mono text-xs">{row.original.supplier_lot_no ?? '—'}</span>
            ),
        },
        {
            id: 'quantity_received',
            accessorKey: 'quantity_received',
            header: __('Received'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{fmt(row.original.quantity_received)}</span>,
        },
        {
            id: 'quantity_available',
            accessorKey: 'quantity_available',
            header: __('Available'),
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
            header: __('Expiry'),
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
            header: __('Status'),
            cell: ({ row }) => <StatusBadge size="sm" {...materialLotStatusBadge(row.original.status)} />,
        },
    ], []);

    const movementColumns = useMemo(() => [
        {
            id: 'performed_at',
            accessorKey: 'performed_at',
            header: __('When'),
            cell: ({ row }) => (
                <span className="text-xs font-mono text-om-muted">
                    {row.original.performed_at ? row.original.performed_at.substring(0, 16).replace('T', ' ') : '—'}
                </span>
            ),
        },
        {
            id: 'movement_type',
            accessorKey: 'movement_type',
            header: __('Type'),
            cell: ({ row }) => {
                const typeColor = MOVEMENT_TYPE_COLORS[row.original.movement_type] ?? 'text-om-muted';
                return <span className={`font-medium ${typeColor}`}>{__(MOVEMENT_LABELS[row.original.movement_type] ?? row.original.movement_type)}</span>;
            },
        },
        {
            id: 'delta',
            accessorKey: 'quantity',
            header: __('Delta'),
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
            header: __('Balance'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="font-mono">{fmt(row.original.balance_after)}</span>,
        },
        {
            id: 'source',
            accessorFn: (r) => movementSource(r),
            header: __('Source'),
            cell: ({ row }) => (
                <span className="text-xs text-om-muted">
                    {movementSource(row.original)}
                </span>
            ),
        },
        {
            id: 'reason',
            accessorFn: (r) => movementReason(r),
            header: __('Reason'),
            cell: ({ row }) => (
                <span className="text-xs text-om-muted truncate max-w-xs block" title={movementReason(row.original)}>
                    {movementReason(row.original).substring(0, 60)}
                </span>
            ),
        },
        {
            id: 'performed_by',
            accessorFn: (r) => r.performed_by?.name ?? '—',
            header: __('By'),
            cell: ({ row }) => <span className="text-xs text-om-muted">{row.original.performed_by?.name ?? '—'}</span>,
        },
    ], []);

    const bomColumns = useMemo(() => [
        {
            id: 'template',
            accessorFn: (r) => r.process_template?.name ?? '—',
            header: __('Template'),
            cell: ({ row }) => <span className="text-sm">{row.original.process_template?.name ?? '—'}</span>,
        },
        {
            id: 'product',
            accessorFn: (r) => r.process_template?.product_type?.name ?? '-',
            header: __('Product'),
            cell: ({ row }) => <span className="text-sm">{row.original.process_template?.product_type?.name ?? '-'}</span>,
        },
        {
            id: 'quantity_per_unit',
            accessorKey: 'quantity_per_unit',
            header: __('Qty/Unit'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-sm">{row.original.quantity_per_unit}</span>,
        },
        {
            id: 'scrap_percentage',
            accessorKey: 'scrap_percentage',
            header: __('Scrap %'),
            meta: { align: 'right' },
            cell: ({ row }) => <span className="text-sm">{row.original.scrap_percentage}%</span>,
        },
    ], []);

    return (
        <>
            <Head title={`${__('Material')} — ${material.name}`} />
            <Modal open={receiving} onClose={() => setReceiving(false)} title={__('Receive material')}>
                <form onSubmit={receive} className="space-y-4">
                    <p>{material.name} · {material.unit_of_measure}</p>
                    <p className="text-sm text-om-muted">{__('Enter the delivered quantity, not the target balance. Use a unique delivery reference for this material.')} {' '}{__('This receipt updates total stock only. It does not receive stock into a lot or warehouse location.')}</p>
                    <TextField label={__('Receipt quantity')} type="number" min="0.001" step="0.001" required value={receipt.data.quantity} onChange={value => receipt.setData('quantity', value)} error={receipt.errors.quantity} />
                    <TextField label={__('Delivery reference')} required value={receipt.data.reference} onChange={value => receipt.setData('reference', value)} error={receipt.errors.reference} />
                    <Button type="submit" variant="primary" loading={receipt.processing}>{__('Record receipt')}</Button>
                </form>
            </Modal>

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/materials"
                fields={materialFields(materialTypes ?? [])}
                initial={materialInitial}
                customFields={customFields}
                ensure={['materialTypes']}
                ready={materialTypes !== undefined}
                title={{ edit: __('Edit Material') }}
            />

            {/* Breadcrumbs */}
            <PageTrail append={[{ label: material.name }]} />

            <div className="max-w-5xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-om-ink">{material.name}</h1>
                            {material.is_active ? (
                                <span className="px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium">{__('Active')}</span>
                            ) : (
                                <span className="px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium">{__('Inactive')}</span>
                            )}
                        </div>
                        <p className="text-sm text-om-muted mt-1 font-mono">{material.code}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="primary" onClick={() => setReceiving(true)}>{__('Receive material')}</Button>
                        <Button variant="outline" onClick={() => drawer.edit(material)}>{__('Edit')}</Button>
                    </div>
                </div>

                {/* Details + Stock grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {/* Details */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">{__('Details')}</h3>
                        <dl className="space-y-3">
                            <Row label={__('Type')}     value={material.material_type?.name ?? '—'} />
                            <Row label={__('Unit')}     value={material.unit_of_measure ?? '—'} />
                            <Row label={__('Tracking')} value={__(ucFirst(material.tracking_type))} />
                            <Row label={__('Default Scrap %')} value={`${material.default_scrap_percentage}%`} />
                        </dl>
                    </div>

                    {/* Stock */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">{__('Stock balance')}</h3>
                        <dl className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">{__('Recorded stock balance')}</dt>
                                <dd className="font-mono">{fmt(material.stock_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm">
                                <dt className="text-om-muted">{__('Reserved by active batches')}</dt>
                                <dd className="font-mono text-om-downtime">{fmt(material.reserved_quantity)} {material.unit_of_measure}</dd>
                            </div>
                            <div className="flex justify-between text-sm pt-2 border-t border-om-line2">
                                <dt className="font-medium text-om-muted">{__('Available for production')}</dt>
                                <dd className={`font-mono font-bold ${available <= 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                                    {fmt(Math.max(0, available))} {material.unit_of_measure}
                                </dd>
                            </div>
                            {material.min_stock_level != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>{__('Min stock level')}</dt>
                                    <dd className="font-mono">{fmt(material.min_stock_level)} {material.unit_of_measure}</dd>
                                </div>
                            )}
                            {material.unit_price != null && (
                                <div className="flex justify-between text-xs text-om-faint">
                                    <dt>{__('Stock value')}</dt>
                                    <dd className="font-mono">
                                        {Number(material.stock_quantity * material.unit_price).toFixed(2)} {material.price_currency}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div className="md:col-span-2 text-sm text-om-muted">
                        {available < 0 && <p className="mb-2 text-om-blocked">{__('Negative balance: production was recorded without enough stock. Check missing receipts; the balance is not a physical quantity.')}</p>}
                        <p>{__('A lot record alone does not receive stock. Record each delivery once; do not repeat receipts already booked by an integration or warehouse document.')}</p>
                    </div>
                    {/* External System */}
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">{__('External System')}</h3>
                        {material.external_code ? (
                            <dl className="space-y-3">
                                <Row label={__('System')}        value={material.external_system} />
                                <Row label={__('External Code')} value={<span className="font-mono">{material.external_code}</span>} />
                            </dl>
                        ) : (
                            <p className="text-sm text-om-muted">{__('No external system linked.')}</p>
                        )}

                        {material.sources && material.sources.length > 0 && (
                            <>
                                <h4 className="text-sm font-semibold mt-4 mb-2">{__('Additional Sources')}</h4>
                                {material.sources.map((src) => (
                                    <div key={src.id} className="p-2 bg-om-panel rounded mb-2 text-sm">
                                        <span className="font-medium">{src.integration_config?.system_name ?? __('Unknown')}</span>:{' '}
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
                            {__('Lots')} <span className="text-sm font-normal text-om-faint">({lots.length})</span>
                        </h3>
                        <AppDataTable
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
                        <h3 className="text-lg font-semibold mb-4">{__('Recent stock movements')}</h3>
                        <AppDataTable
                            filterable={false}
                            data={recentMovements}
                            columns={movementColumns}
                        />
                    </div>
                )}

                {/* BOM usage */}
                {material.bom_items && material.bom_items.length > 0 && (
                    <div className="card">
                        <h3 className="text-lg font-semibold mb-4">
                            {__('Used in BOM (:count templates)', { count: material.bom_items.length })}
                        </h3>
                        <AppDataTable
                            data={material.bom_items}
                            columns={bomColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </div>
                )}

                <EngineeringDocuments entityType="material" entityId={material.id} />
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

const MOVEMENT_LABELS = {
    receipt: 'Stock receipt', return: 'Stock return', allocation: 'Material allocation',
    consume: 'Consumption', scrap: 'Scrap', adjustment: 'Stock adjustment',
    transfer: 'Stock transfer', reclassify: 'Reclassification',
};
const SOURCE_LABELS = {
    batch: 'Batch', batch_step: 'Batch step', inspection: 'Inspection',
    manual_adjust: 'Manual adjustment', manual_receipt: 'Manual receipt', receipt: 'Stock receipt',
    pallet: 'Pallet', stock_document: 'Stock document', erp_sync: 'ERP sync',
    reclassification: 'Reclassification',
};
function movementSource(row) {
    if (!row.source_type) return '—';
    return __(SOURCE_LABELS[row.source_type] ?? row.source_type) + (row.source_id == null ? '' : ` #${row.source_id}`);
}
// System-written notes arrive translated by the server (`reason_display`);
// a user's own note is shown as typed.
function movementReason(row) {
    return row.reason_display ?? row.reason ?? '';
}
