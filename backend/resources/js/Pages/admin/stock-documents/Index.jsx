import { Head, router, usePage } from '@inertiajs/react';
import { StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

/** Keep in sync with StockDocument::TYPES. */
export const DOCUMENT_TYPES = [
    { value: 'material_issue', label: __('Material release') },
    { value: 'material_receipt', label: __('Material receipt') },
    { value: 'product_receipt', label: __('Product receipt') },
    { value: 'product_issue', label: __('Product release') },
];

const TYPE_LABELS = Object.fromEntries(DOCUMENT_TYPES.map((t) => [t.value, t.label]));

/** StatusPill takes the shop-floor status tokens — map document states onto them. */
const STATUS_PILL = { draft: 'pending', posted: 'done', cancelled: 'blocked' };

export default function StockDocumentsIndex() {
    const { warehouses = [], workOrders = {} } = usePage().props;
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));

    const columns = [
        { key: 'document_no', label: __('Number'), className: 'font-mono text-om-ink font-medium' },
        {
            key: 'type',
            label: __('Type'),
            filter: 'select',
            options: DOCUMENT_TYPES,
            allLabel: __('All types'),
            render: (r) => TYPE_LABELS[r.type] ?? r.type,
        },
        {
            key: 'status',
            label: __('Status'),
            filter: 'select',
            options: [
                { value: 'draft', label: __('Draft') },
                { value: 'posted', label: __('Posted') },
                { value: 'cancelled', label: __('Cancelled') },
            ],
            allLabel: __('All statuses'),
            render: (r) => <StatusPill status={STATUS_PILL[r.status] ?? 'pending'} label={__(r.status)} />,
        },
        {
            key: 'warehouse_id',
            label: __('Warehouse'),
            className: 'font-mono text-om-muted',
            render: (r) => warehouseById[r.warehouse_id]?.code ?? '—',
        },
        {
            key: 'work_order_id',
            label: __('Work Order'),
            className: 'font-mono text-om-muted',
            render: (r) => (r.work_order_id ? (workOrders[r.work_order_id] ?? `#${r.work_order_id}`) : '—'),
        },
        {
            key: 'erp_synced_at',
            label: __('ERP'),
            render: (r) => (r.erp_synced_at ? (r.erp_reference || __('synced')) : '—'),
        },
        {
            key: 'posted_at',
            label: __('Posted'),
            render: (r) => (r.posted_at ? new Date(r.posted_at).toLocaleString() : '—'),
        },
    ];

    const actions = (r) => [
        { label: __('View'), icon: 'edit', href: `/admin/stock-documents/${r.id}` },
        ...(r.status === 'draft'
            ? [{
                label: __('Post'),
                variant: 'primary',
                onClick: () => {
                    if (confirm(__('Post document :no? This moves stock.', { no: r.document_no }))) {
                        router.post(`/admin/stock-documents/${r.id}/post`, {}, { preserveScroll: true });
                    }
                },
            }]
            : []),
        ...(r.status === 'posted'
            ? [{
                label: __('Cancel'),
                variant: 'warning',
                onClick: () => {
                    if (confirm(__('Cancel document :no? This reverses the stock it moved.', { no: r.document_no }))) {
                        router.post(`/admin/stock-documents/${r.id}/cancel`, {}, { preserveScroll: true });
                    }
                },
            }]
            : []),
        ...(r.status === 'posted'
            ? []
            : [{
                label: __('Delete'),
                icon: 'delete',
                variant: 'danger',
                onClick: () => {
                    if (confirm(__('Delete document :no?', { no: r.document_no }))) {
                        router.delete(`/admin/stock-documents/${r.id}`, { preserveScroll: true });
                    }
                },
            }]),
    ];

    return (
        <>
            <Head title={__('Stock Documents')} />
            <ResourceTable
                shape="stock_documents"
                title={__('Stock Documents')}
                createHref="/admin/stock-documents/create"
                createLabel={__('+ New Document')}
                columns={columns}
                orderBy="document_no"
                orderDir="desc"
                actions={actions}
                emptyText={__('No stock documents yet.')}
            />
        </>
    );
}

StockDocumentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
