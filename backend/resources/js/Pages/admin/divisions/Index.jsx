import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function DivisionsIndex() {
    const { counts = {}, factoryNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'factory', label: __('Factory'), className: 'text-gray-600', render: (r) => factoryNames[r.factory_id] ?? '—' },
        { key: 'crews', label: __('Crews'), render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
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
            onClick: () => {
                if (confirm(__('Delete division ":name"?', { name: r.name }))) {
                    router.delete(`/admin/divisions/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Divisions')} />
            <ResourceTable
                shape="divisions"
                title={__('Divisions')}
                createHref="/admin/divisions/create"
                createLabel={__('+ New Division')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No divisions yet.')}
            />
        </>
    );
}

DivisionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
