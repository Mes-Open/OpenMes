import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function WorkersIndex() {
    const { crewNames = {}, wageGroupNames = {}, personnelClassNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'email', label: __('Email'), className: 'text-gray-600' },
        { key: 'crew', label: __('Crew'), className: 'text-gray-600', render: (r) => crewNames[r.crew_id] ?? '—' },
        { key: 'class', label: __('Class'), className: 'text-gray-600', render: (r) => personnelClassNames[r.personnel_class_id] ?? '—' },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/workers/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/workers/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete worker ":name"?', { name: r.name }))) {
                    router.delete(`/admin/workers/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Workers')} />
            <ResourceTable
                shape="workers"
                title={__('Workers')}
                createHref="/admin/workers/create"
                createLabel={__('+ New Worker')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No workers yet.')}
            />
        </>
    );
}

WorkersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
