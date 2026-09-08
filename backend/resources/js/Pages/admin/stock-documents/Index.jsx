import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Modal, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import StockDocumentForm from './StockDocumentForm';
import { documentTypeLabels, documentTypes } from './types';
import { __, formatDateTime } from '../../../lib/i18n';

/** StatusPill takes the shop-floor status tokens — map document states onto them. */
const STATUS_PILL = { draft: 'pending', posted: 'done', cancelled: 'blocked' };

export default function StockDocumentsIndex() {
    const {
        warehouseCodes = {}, workOrders = {},
        // Create-form options, the same props the standalone create page receives.
        // `formTypes` is the backend's own TYPES order; the local `types` below is
        // the translated {value,label} list the Type column filters on.
        warehouses = [], materials = [], productTypes = [], types: formTypes = [],
    } = usePage().props;
    const types = documentTypes();
    const typeLabels = documentTypeLabels();

    // Creating from the list happens in a modal so you keep your filters, page
    // and scroll. /admin/stock-documents/create still renders the same form
    // standalone.
    const [creating, setCreating] = useState(false);
    // Bumped after a successful create to remount the form — see the modal below.
    const [formKey, setFormKey] = useState(0);

    const columns = [
        { key: 'document_no', label: __('Document No.'), className: 'font-mono text-om-ink font-medium' },
        {
            key: 'type',
            label: __('Type'),
            filter: 'select',
            options: types,
            allLabel: __('All types'),
            render: (r) => typeLabels[r.type] ?? r.type,
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
            render: (r) => warehouseCodes[r.warehouse_id] ?? '—',
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
            render: (r) => (r.posted_at ? formatDateTime(r.posted_at) : '—'),
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
                onCreate={() => setCreating(true)}
                createLabel={__('New Document')}
                columns={columns}
                orderBy="document_no"
                orderDir="desc"
                actions={actions}
                emptyText={__('No stock documents yet.')}
            />

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={__('New Stock Document')}
                closeLabel={__('Close')}
                width={900}
                // A misclick on the scrim shouldn't cost a half-filled document.
                keepMounted
            >
                {/* THE stock-document form — the same component the create page
                    renders, so a field added there appears here too. `stay` makes
                    the controller send us back to this list instead of redirecting
                    to the new document, and the row arrives through the synced
                    collection on its own. */}
                {/* `keepMounted` holds the form's state, which is the point when you
                    close by accident. Bumping the key remounts the form for the two
                    cases that are not accidents — a finished create, and an explicit
                    Cancel — so neither lingers into the next one. */}
                <StockDocumentForm
                    key={formKey}
                    warehouses={warehouses}
                    materials={materials}
                    productTypes={productTypes}
                    types={formTypes}
                    stay
                    onCancel={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                    onSuccess={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                />
            </Modal>
        </>
    );
}

StockDocumentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
