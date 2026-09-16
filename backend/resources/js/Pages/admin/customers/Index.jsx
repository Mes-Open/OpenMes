import { Head, router, usePage } from '@inertiajs/react';
import { StatusBadge } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { tierLabel, customerFields, customerInitial } from './fields';
import { __ } from '../../../lib/i18n';

const TIER_BADGES = {
    bronze: { icon: 'medal', color: '#b45309' },
    silver: { icon: 'award', color: 'var(--om-muted)' },
    gold: { icon: 'trophy', color: '#a16207' },
    vip: { icon: 'crown', color: 'var(--om-maint)' },
};

export default function CustomersIndex() {
    const { counts = {}, basePath } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', render: (r) => r.code ?? '—' },
        {
            key: 'tier', label: __('Tier'),
            value: (r) => r.tier,

            render: (r) => {
                const badge = TIER_BADGES[r.tier] ?? { icon: 'tag', color: 'var(--om-muted)' };
                return <StatusBadge
                    label={tierLabel(r.tier)}
                    icon={badge.icon}
                    style={{ color: badge.color, background: 'color-mix(in srgb, currentColor 10%, transparent)', borderColor: 'color-mix(in srgb, currentColor 30%, transparent)' }}
                />;
            },
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
