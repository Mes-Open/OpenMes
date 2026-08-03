import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function LinesIndex() {
    const { counts = {}, areaNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'area', label: __('Area'), className: 'text-om-muted', value: (r) => areaNames[r.area_id] ?? '—', render: (r) => areaNames[r.area_id] ?? '—' },
        { key: 'ws', label: __('Stations'), value: (r) => counts[r.id]?.workstations ?? 0, render: (r) => counts[r.id]?.workstations ?? 0 },
        { key: 'wo', label: __('Work Orders'), value: (r) => counts[r.id]?.work_orders ?? 0, render: (r) => counts[r.id]?.work_orders ?? 0 },
        { key: 'ops', label: __('Operators'), value: (r) => counts[r.id]?.operators ?? 0, render: (r) => counts[r.id]?.operators ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Configure'), href: `/admin/lines/${r.id}` },
        { label: __('Edit'), icon: 'edit', href: `/admin/lines/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/lines/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete line ":name"? (only if it has no work orders)', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/lines/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Production Lines')} />
            <ResourceTable
                shape="lines_all"
                detailHref={(r) => `/admin/lines/${r.id}`}
                title={__('Production Lines')}
                createHref="/admin/lines/create"
                createLabel={__('+ New Line')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No production lines yet.')}
            />
        </>
    );
}

LinesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
