import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function CostSourcesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'unit_cost', label: __('Unit Cost'), value: (r) => Number(r.unit_cost ?? 0), render: (r) => `${r.unit_cost ?? '—'} ${r.currency ?? ''}`.trim() },
        { key: 'unit', label: __('Unit'), className: 'text-om-muted' },
        { key: 'used', label: __('Used'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/cost-sources/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/cost-sources/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete cost source ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/cost-sources/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Cost Sources')} />
            <ResourceTable
                shape="cost_sources"
                title={__('Cost Sources')}
                createHref="/admin/cost-sources/create"
                createLabel={__('+ New Cost Source')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No cost sources yet.')}
            />
        </>
    );
}

CostSourcesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
