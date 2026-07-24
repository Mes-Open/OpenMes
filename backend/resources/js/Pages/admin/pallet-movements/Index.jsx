import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * Physical pallet movement history (#103). Rows stream live from the
 * `pallet_movements` Electric shape; operator and pallet names are resolved
 * from the lookup maps the controller passes as props (same pattern as Crews).
 */
export default function PalletMovementsIndex() {
    const { operatorNames = {}, palletNumbers = {} } = usePage().props;

    const columns = [
        {
            key: 'moved_at',
            label: __('When'),
            className: 'font-mono text-om-muted text-xs',
            render: (r) => (r.moved_at ? formatDateTime(r.moved_at) : '—'),
        },
        {
            key: 'pallet',
            label: __('Pallet'),
            className: 'font-medium text-om-ink',
            render: (r) => palletNumbers[r.pallet_id] ?? `#${r.pallet_id}`,
        },
        {
            key: 'from_location',
            label: __('From'),
            className: 'font-mono text-om-muted',
            render: (r) => r.from_location || '—',
        },
        {
            key: 'to_location',
            label: __('To'),
            className: 'font-mono text-om-ink',
            render: (r) => r.to_location || '—',
        },
        {
            key: 'operator',
            label: __('Operator'),
            className: 'text-om-muted',
            render: (r) => operatorNames[r.worker_id] ?? '—',
        },
        {
            key: 'notes',
            label: __('Notes'),
            className: 'text-om-muted',
            render: (r) => r.notes || '—',
        },
    ];

    return (
        <>
            <Head title={__('Pallet Movements')} />
            <ResourceTable
                shape="pallet_movements"
                title={__('Pallet Movements')}
                columns={columns}
                orderBy="moved_at"
                orderDir="desc"
                actions={() => []}
                emptyText={__('No pallet movements recorded yet.')}
            />
        </>
    );
}

PalletMovementsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
