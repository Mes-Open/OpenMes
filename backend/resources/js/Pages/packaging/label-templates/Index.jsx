import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function LabelTemplatesIndex() {
    const { typeLabels = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'type', label: __('Type'), className: 'text-om-muted', value: (r) => typeLabels[r.type] ?? r.type, render: (r) => typeLabels[r.type] ?? r.type },
        { key: 'size', label: __('Size'), className: 'text-om-muted' },
        { key: 'barcode_format', label: __('Barcode'), className: 'font-mono text-om-muted' },
        { key: 'is_default', label: __('Default'), render: (r) => (r.is_default ? '★' : '') },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/packaging/label-templates/${r.id}/edit` },
        ...(r.is_default ? [] : [{ label: __('Make default'), onClick: () => router.post(`/packaging/label-templates/${r.id}/set-default`, {}, { preserveScroll: true }) }]),
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: { title: __('Delete label template ":name"?', { name: r.name }), confirmLabel: __('Delete') },
            onClick: () => router.delete(`/packaging/label-templates/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Label Templates')} />
            <ResourceTable
                shape="label_templates"
                title={__('Label Templates')}
                createHref="/packaging/label-templates/create"
                createLabel={__('New Template')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No label templates yet.')}
            />
        </>
    );
}

LabelTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
