import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function WebhooksIndex() {
    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'url', label: __('URL'), className: 'font-mono text-[12px] text-om-muted' },
        {
            key: 'events',
            label: __('Events'),
            sortable: false,
            render: (r) => (Array.isArray(r.events) ? r.events.length : 0),
        },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
        {
            key: 'last_triggered_at',
            label: __('Last triggered'),
            render: (r) => (r.last_triggered_at ? new Date(r.last_triggered_at).toLocaleString() : '—'),
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/webhooks/${r.id}/edit` },
        {
            label: __('Deliveries'),
            variant: 'secondary',
            href: `/admin/webhooks/${r.id}/deliveries`,
        },
        {
            label: __('Send test'),
            variant: 'secondary',
            onClick: () => router.post(`/admin/webhooks/${r.id}/test`, {}, { preserveScroll: true }),
        },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/webhooks/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete webhook ":name"?', { name: r.name }))) {
                    router.delete(`/admin/webhooks/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Webhooks')} />
            <ResourceTable
                shape="webhooks"
                title={__('Webhooks')}
                createHref="/admin/webhooks/create"
                createLabel={__('+ New Webhook')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No webhooks yet.')}
            />
        </>
    );
}

WebhooksIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
