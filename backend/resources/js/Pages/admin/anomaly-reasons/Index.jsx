import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function AnomalyReasonsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'category', label: __('Category'), className: 'text-gray-600' },
        { key: 'anomalies', label: __('Used'), render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/anomaly-reasons/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/anomaly-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete anomaly reason ":name"?', { name: r.name }))) {
                    router.delete(`/admin/anomaly-reasons/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Anomaly Reasons')} />
            <ResourceTable
                shape="anomaly_reasons"
                title={__('Anomaly Reasons')}
                createHref="/admin/anomaly-reasons/create"
                createLabel={__('+ New Reason')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No anomaly reasons yet.')}
            />
        </>
    );
}

AnomalyReasonsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
