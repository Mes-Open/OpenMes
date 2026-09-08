import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { INTEGRATION_FIELDS, integrationInitial } from './fields';

export default function IntegrationsIndex() {
    const drawer = useResourceDrawer();

    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'system_type', label: __('Type'), className: 'font-mono text-om-muted' },
        { key: 'system_name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'materials', label: __('Materials'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete integration ":name"?', { name: r.system_name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/integrations/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Integrations')} />
            <ResourceTable
                shape="integration_configs"
                title={__('Integrations')}
                createHref="/admin/integrations/create"
                onCreate={drawer.create}
                createLabel={__('New Integration')}
                columns={columns}
                orderBy="system_name"
                actions={actions}
                emptyText={__('No integrations configured.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/integrations"
                fields={INTEGRATION_FIELDS}
                initial={integrationInitial}
                title={{ create: __('New Integration'), edit: __('Edit Integration') }}
            />
        </>
    );
}

IntegrationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
