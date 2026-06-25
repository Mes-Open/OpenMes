import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function LabelTemplatesIndex() {
    const { typeLabels = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'type', label: __('Type'), className: 'text-gray-600', render: (r) => __(typeLabels[r.type] ?? r.type) },
        { key: 'size', label: __('Size'), className: 'text-gray-600' },
        { key: 'barcode_format', label: __('Barcode'), className: 'font-mono text-gray-600' },
        { key: 'is_default', label: __('Default'), render: (r) => (r.is_default ? '★' : '') },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/packaging/label-templates/${r.id}/edit` },
        ...(r.is_default ? [] : [{ label: __('Make default'), onClick: () => router.post(`/packaging/label-templates/${r.id}/set-default`, {}, { preserveScroll: true }) }]),
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(__('Delete label template ":name"?', { name: r.name }))) router.delete(`/packaging/label-templates/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title={__('Label Templates')} />
            <ResourceTable
                shape="label_templates"
                title={__('Label Templates')}
                createHref="/packaging/label-templates/create"
                createLabel={__('+ New Template')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No label templates yet.')}
            />
        </>
    );
}

LabelTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
