import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function IntegrationsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'system_type', label: __('Type'), className: 'font-mono text-gray-700' },
        { key: 'system_name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'materials', label: __('Materials'), render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/integrations/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete integration ":name"?', { name: r.system_name }))) {
                    router.delete(`/admin/integrations/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Integrations')} />
            <ResourceTable
                shape="integration_configs"
                title={__('Integrations')}
                createHref="/admin/integrations/create"
                createLabel={__('+ New Integration')}
                columns={columns}
                orderBy="system_name"
                actions={actions}
                emptyText={__('No integrations configured.')}
            />
        </>
    );
}

IntegrationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
