import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { webhookFields, webhookInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function WebhooksIndex() {
    const { events = [], generatedSecret } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'url', label: __('URL'), className: 'font-mono text-[12px] text-om-muted' },
        {
            key: 'events',
            label: __('Events'),
            sortable: false,
            value: (r) => (Array.isArray(r.events) ? r.events.length : 0),
           
            render: (r) => (Array.isArray(r.events) ? r.events.length : 0),
        },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
        {
            key: 'last_triggered_at', filter: 'date',
            label: __('Last triggered'),
            render: (r) => (r.last_triggered_at ? new Date(r.last_triggered_at).toLocaleString() : '—'),
        },
    ];

    const actions = (r) => [
        // The row is the record — except its secret, which the collection
        // deliberately never carries. Editing can set a new one, not reveal it.
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
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
            confirm: {
                title: __('Delete webhook ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/webhooks/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Webhooks')} />
            <ResourceTable
                shape="webhooks"
                title={__('Webhooks')}
                createHref="/admin/webhooks/create"
                onCreate={drawer.create}
                createLabel={__('New Webhook')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No webhooks yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/webhooks"
                fields={(mode) => webhookFields(events, { isEdit: mode === 'edit' })}
                initial={(record) => webhookInitial(record, { generatedSecret })}
                ensure={['generatedSecret']}
                ready={generatedSecret !== undefined}
                title={{ create: __('New Webhook'), edit: __('Edit Webhook') }}
            />

        </>
    );
}

WebhooksIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
