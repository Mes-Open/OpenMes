import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Modal } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceForm from '../../../components/ResourceForm';
import { TIER_BADGE_STYLES, tierLabel, customerFields, CUSTOMER_INITIAL } from './fields';
import { __ } from '../../../lib/i18n';

export default function CustomersIndex() {
    const { counts = {} } = usePage().props;
    const [creating, setCreating] = useState(false);
    const [formKey, setFormKey] = useState(0);

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
        { label: __('Edit'), href: `/admin/customers/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`/admin/customers/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            confirm: {
                title: __('Delete customer ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/customers/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Customers')} />
            <ResourceTable
                shape="customers"
                title={__('Customers')}
                createHref="/admin/customers/create"
                onCreate={() => setCreating(true)}
                createLabel={__('New Customer')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No customers yet.')}
            />

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={__('New Customer')}
                closeLabel={__('Close')}
                className="max-w-[720px]"
                // A misclick on the scrim shouldn't cost a half-filled customer.
                keepMounted
            >
                {/* The same field config the create page renders, so a field added
                    to `customerFields()` shows up in both. `stay: 1` makes the
                    controller answer with back(), keeping this list's filters and
                    paging while the new row live-syncs in. */}
                {/* `keepMounted` holds the form's state, which is the point when
                    you close by accident. Bumping the key remounts the form for the
                    two cases that are not accidents — a finished create, and an
                    explicit Cancel — so neither lingers into the next one. */}
                <ResourceForm
                    key={formKey}
                    action="/admin/customers"
                    method="post"
                    fields={customerFields()}
                    initial={{ ...CUSTOMER_INITIAL, stay: 1 }}
                    submitLabel={__('Create')}
                    // Cancel is a deliberate discard; only the scrim click is the
                    // accident `keepMounted` exists for.
                    onCancel={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                    onSuccess={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                />
            </Modal>
        </>
    );
}

CustomersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
