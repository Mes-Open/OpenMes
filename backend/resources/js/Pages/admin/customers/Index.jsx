import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { TIER_BADGE_STYLES, tierLabel, customerFields, customerInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function CustomersIndex() {
    const { counts = {}, basePath } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', render: (r) => r.code ?? '—' },
        {
            key: 'tier', label: __('Tier'),
            value: (r) => r.tier,
           
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${TIER_BADGE_STYLES[r.tier] ?? 'bg-om-chip text-om-muted'}`}>
                    {tierLabel(r.tier)}
                </span>
            ),
        },
        { key: 'payment_score', label: __('Payment'), align: 'right', className: 'text-om-muted', value: (r) => Number(r.payment_score ?? 0), render: (r) => r.payment_score ?? 0 },
        { key: 'total_orders', label: __('Orders'), align: 'right', className: 'text-om-muted', value: (r) => Number(r.total_orders ?? 0), render: (r) => r.total_orders ?? 0 },
        { key: 'total_revenue', label: __('Revenue'), align: 'right', className: 'text-om-muted', value: (r) => Number(r.total_revenue ?? 0), render: (r) => Number(r.total_revenue ?? 0).toFixed(2) },
        { key: 'work_orders', label: __('Work orders'), align: 'right', value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        // The row is the record: `customers` syncs every column the form needs,
        // so the drawer opens filled in without a round-trip.
        { label: __('Edit'), onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`${basePath}/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            confirm: {
                title: __('Delete customer ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`${basePath}/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Customers')} />
            <ResourceTable
                shape="customers"
                title={__('Customers')}
                createHref={`${basePath}/create`}
                onCreate={drawer.create}
                createLabel={__('New Customer')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No customers yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action={basePath}
                fields={customerFields()}
                initial={customerInitial}
                title={{ create: __('New Customer'), edit: __('Edit Customer') }}
            />
        </>
    );
}

CustomersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
