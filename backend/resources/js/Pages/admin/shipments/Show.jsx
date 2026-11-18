import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, InlineAlert } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import useConfirm from '../../../components/useConfirm';
import {
    ExpectedList, LoadTotals, Panel, ScanLog, ShipmentStatusPill, useShipmentLive,
} from '../../../components/shipments/shipmentUi';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * One shipment (#400): header, live totals, the expected list and the dock's
 * scan log. Everything below the header streams from the synced collections,
 * so this page follows the loading terminal as it scans.
 */
export default function ShipmentShow() {
    const { basePath, shipment: initial, statusLabels = {}, userNames = {}, terminalHref } = usePage().props;
    const { shipment: live, items, scans, totals } = useShipmentLive(initial.id, initial);
    const shipment = { ...initial, ...live };
    const active = shipment.status === 'planned' || shipment.status === 'loading';
    const { confirm, dialog } = useConfirm();

    const cancel = () => confirm(
        { title: __('Cancel shipment ":no"?', { no: shipment.shipment_no }), confirmLabel: __('Cancel shipment') },
        () => router.post(`${basePath}/${shipment.id}/cancel`, {}, { preserveScroll: true }),
    );

    const meta = [
        [__('Carrier'), shipment.carrier],
        [__('Vehicle'), shipment.vehicle_no],
        [__('Destination'), shipment.destination],
        [__('Planned'), shipment.planned_at && formatDateTime(shipment.planned_at)],
        [__('ERP reference'), shipment.erp_reference],
        [__('Loading started'), shipment.started_at && `${formatDateTime(shipment.started_at)} · ${userNames[shipment.started_by_id] ?? initial.started_by ?? ''}`],
        [__('Confirmed'), shipment.confirmed_at && `${formatDateTime(shipment.confirmed_at)} · ${userNames[shipment.confirmed_by_id] ?? initial.confirmed_by ?? ''}`],
    ];

    return (
        <div className="max-w-7xl mx-auto space-y-5">
            <Head title={`${__('Shipment')} ${shipment.shipment_no}`} />

            <Link href={basePath} className="text-[13px] text-om-muted hover:text-om-ink">‹ {__('Shipments')}</Link>

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink font-mono" data-testid="shipment-no">{shipment.shipment_no}</h1>
                        <span data-testid="shipment-status"><ShipmentStatusPill status={shipment.status} labels={statusLabels} /></span>
                    </div>
                    {shipment.notes && <p className="text-[13px] text-om-muted mt-1">{shipment.notes}</p>}
                </div>
                {active && (
                    <div className="flex items-center gap-2">
                        <Link href={terminalHref}><Button variant="accent">{__('Open loading terminal')}</Button></Link>
                        <Link href={`${basePath}/${shipment.id}/edit`}><Button variant="secondary">{__('Edit')}</Button></Link>
                        <Button variant="danger" onClick={cancel}>{__('Cancel shipment')}</Button>
                    </div>
                )}
            </div>

            {shipment.short_reason && (
                <InlineAlert severity="warning" title={__('Shipped short')}>{shipment.short_reason}</InlineAlert>
            )}

            <dl className="grid grid-cols-2 md:grid-cols-4 gap-3 bg-om-card border border-om-line rounded-om p-4">
                {meta.map(([label, value]) => (
                    <div key={label}>
                        <dt className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</dt>
                        <dd className="text-[13px] text-om-ink mt-0.5">{value || '—'}</dd>
                    </div>
                ))}
            </dl>

            <LoadTotals totals={totals} />

            <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">
                <Panel title={__('Expected list')} className="lg:col-span-3">
                    <ExpectedList items={items} userNames={userNames} />
                </Panel>
                <Panel title={__('Scan log')} className="lg:col-span-2">
                    <ScanLog scans={scans} userNames={userNames} limit={200} />
                </Panel>
            </div>
            {dialog}
        </div>
    );
}

ShipmentShow.layout = (page) => <AppLayout>{page}</AppLayout>;
