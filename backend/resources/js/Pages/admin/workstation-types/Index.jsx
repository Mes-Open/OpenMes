import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { WORKSTATION_TYPE_FIELDS, workstationTypeInitial } from './fields';

export default function WorkstationTypesIndex() {
    const drawer = useResourceDrawer();

    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'workstations', label: __('Workstations'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/workstation-types/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete workstation type ":name"?', { name: r.name }),
                confirmLabel: __('Delete workstation type'),
            },
            onClick: () => router.delete(`/admin/workstation-types/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Workstation Types')} />
            <ResourceTable
                shape="workstation_types"
                title={__('Workstation Types')}
                createHref="/admin/workstation-types/create"
                onCreate={drawer.create}
                createLabel={__('New Type')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No workstation types yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/workstation-types"
                fields={WORKSTATION_TYPE_FIELDS}
                initial={workstationTypeInitial}
                title={{ create: __('New Workstation Type'), edit: __('Edit Workstation Type') }}
            />
        </>
    );
}

WorkstationTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
