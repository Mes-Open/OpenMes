import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function MaterialTypesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'materials', label: __('Materials'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/material-types/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete material type ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/material-types/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Material Types')} />
            <ResourceTable
                shape="material_types"
                title={__('Material Types')}
                createHref="/admin/material-types/create"
                createLabel={__('New Material Type')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No material types yet.')}
            />
        </>
    );
}

MaterialTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
