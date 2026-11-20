import { useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { ShipmentStatusPill } from '../../../components/shipments/shipmentUi';
import { useSyncedShape } from '../../../lib/useSyncedShape';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * Shipments (#400) for supervisors and admins. Rows stream from `shipments`;
 * the loaded / expected count per load is folded from `shipment_items`, so the
 * list moves while the dock is scanning.
 */
export default function ShipmentsIndex() {
    const { basePath, statusLabels = {} } = usePage().props;
    const { data: items } = useSyncedShape('shipment_items');

    const counts = useMemo(() => {
        const out = {};
        for (const item of items) {
            const c = (out[item.shipment_id] ??= { expected: 0, loaded: 0 });
            c.expected += 1;
            if (item.loaded_at) c.loaded += 1;
        }
        return out;
    }, [items]);

    const active = (r) => r.status === 'planned' || r.status === 'loading';

    const columns = [
        { key: 'shipment_no', label: __('Shipment no.'), className: 'font-mono font-medium text-om-ink', filter: 'text' },
        {
            key: 'status', label: __('Status'),
            value: (r) => statusLabels[r.status] ?? r.status,
            render: (r) => <ShipmentStatusPill status={r.status} labels={statusLabels} />,
        },
        {
            key: 'progress', label: __('Loaded'), align: 'right',
            value: (r) => counts[r.id]?.loaded ?? 0,
            render: (r) => `${counts[r.id]?.loaded ?? 0} / ${counts[r.id]?.expected ?? 0}`,
            filter: false,
        },
        { key: 'carrier', label: __('Carrier'), render: (r) => r.carrier ?? '—' },
        { key: 'vehicle_no', label: __('Vehicle'), className: 'font-mono', render: (r) => r.vehicle_no ?? '—' },
        { key: 'destination', label: __('Destination'), render: (r) => r.destination ?? '—' },
        { key: 'planned_at', label: __('Planned'), render: (r) => (r.planned_at ? formatDateTime(r.planned_at) : '—'), filter: false },
        { key: 'erp_reference', label: __('ERP reference'), className: 'font-mono text-om-muted', render: (r) => r.erp_reference ?? '—' },
    ];

    const actions = (r) => [
        { label: __('Open'), href: `${basePath}/${r.id}` },
        ...(active(r) ? [
            { label: __('Load'), href: `/logistics/loading/${r.id}`, variant: 'primary' },
            { label: __('Edit'), href: `${basePath}/${r.id}/edit` },
            {
                label: __('Cancel'),
                className: 'text-om-blocked hover:underline',
                confirm: { title: __('Cancel shipment ":no"?', { no: r.shipment_no }), confirmLabel: __('Cancel shipment') },
                onClick: () => router.post(`${basePath}/${r.id}/cancel`, {}, { preserveScroll: true }),
            },
        ] : []),
        ...(r.status !== 'loading' ? [{
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            confirm: { title: __('Delete shipment ":no"?', { no: r.shipment_no }), confirmLabel: __('Delete') },
            onClick: () => router.delete(`${basePath}/${r.id}`, { preserveScroll: true }),
        }] : []),
    ];

    return (
        <>
            <Head title={__('Shipments')} />
            <ResourceTable
                shape="shipments"
                title={__('Shipments')}
                createHref={`${basePath}/create`}
                createLabel={__('New shipment')}
                detailHref={(r) => `${basePath}/${r.id}`}
                columns={columns}
                orderBy="id"
                orderDir="desc"
                actions={actions}
                rowClassName={(r) => (r.status === 'cancelled' ? 'opacity-60' : '')}
                emptyText={__('No shipments yet.')}
            />
        </>
    );
}

ShipmentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
