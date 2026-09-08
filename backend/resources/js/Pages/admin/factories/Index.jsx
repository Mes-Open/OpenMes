import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { FACTORY_FIELDS, factoryInitial } from './fields';

export default function FactoriesIndex() {
    const drawer = useResourceDrawer();

    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text', link: true },
        { key: 'divisions', label: __('Divisions'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/factories/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete factory ":name"?', { name: r.name }),
                confirmLabel: __('Delete factory'),
            },
            onClick: () => router.delete(`/admin/factories/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Factories')} />
            <ResourceTable
                shape="factories"
                detailHref={(r) => `/admin/factories/${r.id}`}
                title={__('Factories')}
                createHref="/admin/factories/create"
                onCreate={drawer.create}
                createLabel={__('New Factory')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No factories yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/factories"
                fields={FACTORY_FIELDS}
                initial={factoryInitial}
                title={{ create: __('New Factory'), edit: __('Edit Factory') }}
            />
        </>
    );
}

FactoriesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
