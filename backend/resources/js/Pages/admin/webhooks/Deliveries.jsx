import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

const STATUS_CLASS = {
    success: 'text-om-done bg-om-done-bg',
    failed: 'text-om-blocked bg-om-blocked-bg',
    pending: 'text-om-muted bg-om-chip',
};

function StatusBadge({ status }) {
    const cls = STATUS_CLASS[status] ?? STATUS_CLASS.pending;
    return <span className={`inline-block rounded-om-sm px-2 py-0.5 text-[11.5px] font-semibold ${cls}`}>{__(status)}</span>;
}

export default function WebhookDeliveries() {
    const { webhook } = usePage().props;

    const columns = [
        {
            key: 'created_at',
            label: __('Created'),
            render: (r) => (r.created_at ? new Date(r.created_at).toLocaleString() : '—'),
        },
        { key: 'event_type', label: __('Event'), className: 'font-mono text-[12px] text-om-muted' },
        { key: 'status', label: __('Status'), render: (r) => <StatusBadge status={r.status} /> },
        { key: 'attempts', label: __('Attempts') },
        { key: 'response_code', label: __('Response'), render: (r) => r.response_code ?? '—' },
        { key: 'error', label: __('Error'), className: 'text-[12px] text-om-blocked', render: (r) => r.error ?? '—' },
    ];

    return (
        <div>
            <Head title={__('Deliveries')} />
            <Link href="/admin/webhooks" className="text-[13px] text-om-muted hover:text-om-ink mb-4 inline-block">
                ‹ {__('Webhooks')}
            </Link>
            <ResourceTable
                shape="webhook_deliveries"
                title={__('Deliveries for :name', { name: webhook.name })}
                columns={columns}
                orderBy="created_at"
                orderDir="desc"
                filterFn={(r) => Number(r.webhook_id) === Number(webhook.id)}
                emptyText={__('No deliveries yet.')}
            />
        </div>
    );
}

WebhookDeliveries.layout = (page) => <AppLayout>{page}</AppLayout>;
