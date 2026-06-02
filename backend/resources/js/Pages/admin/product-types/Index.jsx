import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

/**
 * Product Types — list. Now built on the generic ResourceTable primitive; the
 * page only declares columns + row actions. Rows live-sync from the
 * `product_types` shape; cross-table counts come from the `counts` prop.
 */
export default function ProductTypesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'unit_of_measure', label: 'UoM', className: 'text-gray-600' },
        { key: 'templates', label: 'Templates', render: (r) => counts[r.id]?.process_templates ?? 0 },
        { key: 'work_orders', label: 'Work Orders', render: (r) => counts[r.id]?.work_orders ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/product-types/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/product-types/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete product type "${r.name}"? This cannot be undone.`)) {
                    router.delete(`/admin/product-types/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Product Types" />
            <ResourceTable
                shape="product_types"
                title="Product Types"
                createHref="/admin/product-types/create"
                createLabel="+ New Product Type"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No product types yet."
            />
        </>
    );
}

ProductTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
