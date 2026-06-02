import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

export default function LotSequencesIndex() {
    const { productTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'product_type', label: 'Product Type', className: 'text-gray-600', render: (r) => productTypeNames[r.product_type_id] ?? 'Global' },
        { key: 'prefix', label: 'Prefix', className: 'font-mono text-gray-700' },
        { key: 'next_number', label: 'Next #', className: 'text-gray-600' },
        { key: 'pad_size', label: 'Pad', className: 'text-gray-600' },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/lot-sequences/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete LOT sequence "${r.name}"?`)) {
                    router.delete(`/admin/lot-sequences/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="LOT Sequences" />
            <ResourceTable
                shape="lot_sequences"
                title="LOT Sequences"
                createHref="/admin/lot-sequences/create"
                createLabel="+ New Sequence"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No LOT sequences yet."
            />
        </>
    );
}

LotSequencesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
