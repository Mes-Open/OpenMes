import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function AnomalyReasonsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'category', label: 'Category', className: 'text-gray-600' },
        { key: 'anomalies', label: 'Used', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/anomaly-reasons/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/anomaly-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete anomaly reason "${r.name}"?`)) {
                    router.delete(`/admin/anomaly-reasons/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Anomaly Reasons" />
            <ResourceTable
                shape="anomaly_reasons"
                title="Anomaly Reasons"
                createHref="/admin/anomaly-reasons/create"
                createLabel="+ New Reason"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No anomaly reasons yet."
            />
        </>
    );
}

AnomalyReasonsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
