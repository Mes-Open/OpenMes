import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * Read-only stock overview: what each warehouse holds. Balances arrive live via
 * the `warehouse_stocks` shape (a posted document or an ERP sync shows up without
 * a reload); ids are resolved to codes from the lookup props.
 *
 * Rows carrying a lot are the lot-level breakdown of the material's warehouse
 * total, so the lot column doubles as the "is this a detail row" marker.
 */
export default function WarehouseStockIndex() {
    const { warehouses = [], materials = [], productTypes = [], lots = {} } = usePage().props;

    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const materialById = Object.fromEntries(materials.map((m) => [m.id, m]));
    const productById = Object.fromEntries(productTypes.map((p) => [p.id, p]));

    const itemCode = (r) =>
        (r.material_id ? materialById[r.material_id]?.code : productById[r.product_type_id]?.code) ?? '—';
    const itemName = (r) =>
        (r.material_id ? materialById[r.material_id]?.name : productById[r.product_type_id]?.name) ?? '—';

    const columns = [
        {
            key: 'warehouse_id',
            label: __('Warehouse'),
            className: 'font-mono text-om-muted',
            filter: 'select',
            options: warehouses.map((w) => ({ value: String(w.id), label: w.code })),
            allLabel: __('All warehouses'),
            render: (r) => warehouseById[r.warehouse_id]?.code ?? '—',
        },
        {
            key: 'kind',
            label: __('Type'),
            render: (r) => (r.material_id ? __('Material') : __('Product')),
        },
        { key: 'item_code', label: __('Code'), className: 'font-mono text-om-muted', render: itemCode },
        { key: 'item_name', label: __('Item'), className: 'font-medium text-om-ink', render: itemName },
        {
            key: 'material_lot_id',
            label: __('Lot'),
            className: 'font-mono text-om-muted',
            render: (r) => (r.material_lot_id ? (lots[r.material_lot_id] ?? `#${r.material_lot_id}`) : __('total')),
        },
        {
            key: 'quantity',
            label: __('Quantity'),
            align: 'right',
            render: (r) => `${Number(r.quantity).toLocaleString()} ${r.unit_of_measure ?? ''}`.trim(),
        },
        {
            key: 'erp_synced_at',
            label: __('ERP Synced'),
            render: (r) => (r.erp_synced_at ? formatDateTime(r.erp_synced_at) : '—'),
        },
    ];

    return (
        <>
            <Head title={__('Stock On Hand')} />
            <ResourceTable
                shape="warehouse_stocks"
                title={__('Stock On Hand')}
                columns={columns}
                orderBy="warehouse_id"
                emptyText={__('No stock recorded yet. Post a document or run an ERP stock sync.')}
            />
        </>
    );
}

WarehouseStockIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
