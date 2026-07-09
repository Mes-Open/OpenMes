import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { TIER_BADGE_STYLES, tierLabel } from './fields';
import { __ } from '../../../lib/i18n';

export default function CustomersIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', render: (r) => r.code ?? '—' },
        {
            key: 'tier', label: __('Tier'),
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${TIER_BADGE_STYLES[r.tier] ?? 'bg-om-chip text-om-muted'}`}>
                    {tierLabel(r.tier)}
                </span>
            ),
        },
        { key: 'payment_score', label: __('Payment'), align: 'right', className: 'text-om-muted', render: (r) => r.payment_score ?? 0 },
        { key: 'total_orders', label: __('Orders'), align: 'right', className: 'text-om-muted', render: (r) => r.total_orders ?? 0 },
        { key: 'total_revenue', label: __('Revenue'), align: 'right', className: 'text-om-muted', render: (r) => Number(r.total_revenue ?? 0).toFixed(2) },
        { key: 'work_orders', label: __('Work orders'), align: 'right', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), href: `/admin/customers/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`/admin/customers/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            onClick: () => {
                if (confirm(__('Delete customer ":name"?', { name: r.name }))) {
                    router.delete(`/admin/customers/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Customers')} />
            <ResourceTable
                shape="customers"
                title={__('Customers')}
                createHref="/admin/customers/create"
                createLabel={__('+ New Customer')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No customers yet.')}
            />
        </>
    );
}

CustomersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
