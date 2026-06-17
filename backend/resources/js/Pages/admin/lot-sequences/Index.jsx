import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function LotSequencesIndex() {
    const { productTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'product_type', label: __('Product Type'), className: 'text-om-muted', render: (r) => productTypeNames[r.product_type_id] ?? __('Global') },
        {
            key: 'format',
            label: __('Format'),
            className: 'font-mono text-om-muted',
            render: (r) => r.pattern || r.prefix,
        },
        { key: 'next_number', label: __('Next #'), className: 'text-om-muted' },
        { key: 'pad_size', label: __('Pad'), className: 'text-om-muted' },
        {
            key: 'reset_period',
            label: __('Reset'),
            className: 'text-om-muted',
            render: (r) => (r.reset_period && r.reset_period !== 'none' ? r.reset_period : '—'),
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/lot-sequences/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete LOT sequence ":name"?', { name: r.name }))) {
                    router.delete(`/admin/lot-sequences/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('LOT Sequences')} />
            <ResourceTable
                shape="lot_sequences"
                title={__('LOT Sequences')}
                createHref="/admin/lot-sequences/create"
                createLabel={__('+ New Sequence')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No LOT sequences yet.')}
            />
        </>
    );
}

LotSequencesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
