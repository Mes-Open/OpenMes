import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function CrewsIndex() {
    const { counts = {}, divisionNames = {}, leaderNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'division', label: __('Division'), className: 'text-om-muted', value: (r) => divisionNames[r.division_id] ?? '—', render: (r) => divisionNames[r.division_id] ?? '—' },
        { key: 'leader', label: __('Leader'), className: 'text-om-muted', value: (r) => leaderNames[r.leader_id] ?? '—', render: (r) => leaderNames[r.leader_id] ?? '—' },
        { key: 'workers', label: __('Workers'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/crews/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/crews/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete crew ":name"?', { name: r.name }),
                confirmLabel: __('Delete crew'),
            },
            onClick: () => router.delete(`/admin/crews/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Crews')} />
            <ResourceTable
                shape="crews"
                title={__('Crews')}
                createHref="/admin/crews/create"
                createLabel={__('New Crew')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No crews yet.')}
            />
        </>
    );
}

CrewsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
