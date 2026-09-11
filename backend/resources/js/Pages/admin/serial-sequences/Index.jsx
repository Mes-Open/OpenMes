import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import SerialSequenceForm, { serialSequenceInitial } from './SerialSequenceForm';
import { __ } from '../../../lib/i18n';

export default function SerialSequencesIndex() {
    const drawer = useResourceDrawer();

    const { productTypeNames = {}, productTypes, patternTokens } = usePage().props;
    const formReady = productTypes !== undefined && patternTokens !== undefined;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'product_type', label: __('Product Type'), className: 'text-om-muted', value: (r) => productTypeNames[r.product_type_id] ?? __('Global'), render: (r) => productTypeNames[r.product_type_id] ?? __('Global') },
        {
            key: 'format',
            label: __('Format'),
            className: 'font-mono text-om-muted',
            value: (r) => r.pattern || r.prefix,
            render: (r) => r.pattern || r.prefix,
        },
        { key: 'next_number', label: __('Next #'), className: 'text-om-muted' },
        { key: 'pad_size', label: __('Pad'), className: 'text-om-muted' },
        {
            key: 'reset_period',
            label: __('Reset'),
            className: 'text-om-muted',
            render: (r) => (r.reset_period && r.reset_period !== 'none' ? r.reset_period : '—'),
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete serial sequence ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/serial-sequences/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Serial Sequences')} />
            <ResourceTable
                shape="serial_sequences"
                title={__('Serial Sequences')}
                createHref="/admin/serial-sequences/create"
                onCreate={drawer.create}
                createLabel={__('New Sequence')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No serial sequences yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                ensure={['productTypes', 'patternTokens']}
                ready={formReady}
                title={{ create: __('New Serial Sequence'), edit: __('Edit Serial Sequence') }}
                render={({ editing, record, finish, dismiss }) => (
                    <SerialSequenceForm
                        bare
                        action={editing ? `/admin/serial-sequences/${record.id}` : '/admin/serial-sequences'}
                        method={editing ? 'put' : 'post'}
                        initial={{ ...serialSequenceInitial(editing ? record : null), stay: 1 }}
                        submitLabel={editing ? __('Save Changes') : __('Create')}
                        onSuccess={finish}
                        onCancel={dismiss}
                    />
                )}
            />
        </>
    );
}

SerialSequencesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
