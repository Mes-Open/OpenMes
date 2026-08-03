import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function WorkersIndex() {
    const { crewNames = {}, wageGroupNames = {}, personnelClassNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'email', label: __('Email'), className: 'text-om-muted' },
        { key: 'crew', label: __('Crew'), className: 'text-om-muted', value: (r) => crewNames[r.crew_id] ?? '—', render: (r) => crewNames[r.crew_id] ?? '—' },
        { key: 'class', label: __('Class'), className: 'text-om-muted', value: (r) => personnelClassNames[r.personnel_class_id] ?? '—', render: (r) => personnelClassNames[r.personnel_class_id] ?? '—' },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
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
            confirm: {
                title: __('Delete worker ":name"?', { name: r.name }),
                confirmLabel: __('Delete worker'),
            },
            onClick: () => router.delete(`/admin/workers/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Workers')} />
            <ResourceTable
                shape="workers"
                detailHref={(r) => `/admin/workers/${r.id}`}
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
