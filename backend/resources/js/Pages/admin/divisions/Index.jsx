import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function DivisionsIndex() {
    const { counts = {}, factoryNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'factory', label: __('Factory'), className: 'text-om-muted', value: (r) => factoryNames[r.factory_id] ?? '—', render: (r) => factoryNames[r.factory_id] ?? '—' },
        { key: 'crews', label: __('Crews'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/divisions/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/divisions/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete division ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/divisions/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Divisions')} />
            <ResourceTable
                shape="divisions"
                title={__('Divisions')}
                createHref="/admin/divisions/create"
                createLabel={__('New Division')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No divisions yet.')}
            />
        </>
    );
}

DivisionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
