import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function IntegrationsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'system_type', label: 'Type', className: 'font-mono text-gray-700' },
        { key: 'system_name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'materials', label: 'Materials', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/integrations/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete integration "${r.system_name}"?`)) {
                    router.delete(`/admin/integrations/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Integrations" />
            <ResourceTable
                shape="integration_configs"
                title="Integrations"
                createHref="/admin/integrations/create"
                createLabel="+ New Integration"
                columns={columns}
                orderBy="system_name"
                actions={actions}
                emptyText="No integrations configured."
            />
        </>
    );
}

IntegrationsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
