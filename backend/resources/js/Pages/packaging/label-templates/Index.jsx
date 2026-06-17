import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function LabelTemplatesIndex() {
    const { typeLabels = {} } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'type', label: 'Type', className: 'text-om-muted', render: (r) => typeLabels[r.type] ?? r.type },
        { key: 'size', label: 'Size', className: 'text-om-muted' },
        { key: 'barcode_format', label: 'Barcode', className: 'font-mono text-om-muted' },
        { key: 'is_default', label: 'Default', render: (r) => (r.is_default ? '★' : '') },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/packaging/label-templates/${r.id}/edit` },
        ...(r.is_default ? [] : [{ label: 'Make default', onClick: () => router.post(`/packaging/label-templates/${r.id}/set-default`, {}, { preserveScroll: true }) }]),
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(`Delete label template "${r.name}"?`)) router.delete(`/packaging/label-templates/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title="Label Templates" />
            <ResourceTable
                shape="label_templates"
                title="Label Templates"
                createHref="/packaging/label-templates/create"
                createLabel="+ New Template"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No label templates yet."
            />
        </>
    );
}

LabelTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
