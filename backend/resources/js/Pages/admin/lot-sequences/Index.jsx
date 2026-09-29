import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import LotSequenceForm, { lotSequenceInitial } from './LotSequenceForm';
import { __ } from '../../../lib/i18n';

const RESET_LABEL = {
    none: () => __('No reset'),
    yearly: () => __('Yearly'),
    monthly: () => __('Monthly'),
    daily: () => __('Daily'),
    hourly: () => __('Hourly'),
};

const PURPOSE_LABEL = {
    lot: () => __('Batch LOT numbers'),
    process_serial: () => __('Process serial numbers (PSN)'),
    unit_serial: () => __('Unit serial numbers'),
};

export default function LotSequencesIndex() {
    const drawer = useResourceDrawer();

    const { productTypeNames = {}, productTypes, patternTokens } = usePage().props;
    const formReady = productTypes !== undefined && patternTokens !== undefined;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'product_type', label: __('Product Type'), className: 'text-om-muted', value: (r) => productTypeNames[r.product_type_id] ?? __('Global'), render: (r) => productTypeNames[r.product_type_id] ?? __('Global') },
        { key: 'purpose', label: __('Numbers'), className: 'text-om-muted', value: (r) => PURPOSE_LABEL[r.purpose]?.() ?? r.purpose, render: (r) => PURPOSE_LABEL[r.purpose]?.() ?? r.purpose },
        {
            key: 'format',
            label: __('Format'),
            className: 'font-mono text-om-muted',
            value: (r) => r.pattern || r.prefix,
            render: (r) => r.pattern || r.prefix,
        },
        { key: 'next_number', label: __('Next #'), className: 'text-om-muted' },
        { key: 'pad_size', label: __('Counter digits'), className: 'text-om-muted' },
        {
            key: 'reset_period',
            label: __('Counter starts over'),
            className: 'text-om-muted',
            value: (r) => RESET_LABEL[r.reset_period ?? 'none']?.() ?? r.reset_period,
            render: (r) => RESET_LABEL[r.reset_period ?? 'none']?.() ?? r.reset_period,
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete LOT sequence ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/lot-sequences/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('LOT Sequences')} />
            <ResourceTable
                shape="lot_sequences"
                title={__('LOT Sequences')}
                createHref="/admin/lot-sequences/create"
                onCreate={drawer.create}
                createLabel={__('New Sequence')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No LOT sequences yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                ensure={['productTypes', 'patternTokens']}
                ready={formReady}
                title={{ create: __('New LOT Sequence'), edit: __('Edit LOT Sequence') }}
                render={({ editing, record, finish, dismiss }) => (
                    <LotSequenceForm
                        bare
                        action={editing ? `/admin/lot-sequences/${record.id}` : '/admin/lot-sequences'}
                        method={editing ? 'put' : 'post'}
                        initial={{ ...lotSequenceInitial(editing ? record : null), stay: 1 }}
                        submitLabel={editing ? __('Save Changes') : __('Create')}
                        onSuccess={finish}
                        onCancel={dismiss}
                    />
                )}
            />
        </>
    );
}

LotSequencesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
