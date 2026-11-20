import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ShipmentForm from './ShipmentForm';
import { __ } from '../../../lib/i18n';

export default function ShipmentEdit({ basePath, shipment }) {
    const initial = {
        shipment_no: shipment.shipment_no ?? '',
        erp_reference: shipment.erp_reference ?? '',
        carrier: shipment.carrier ?? '',
        vehicle_no: shipment.vehicle_no ?? '',
        destination: shipment.destination ?? '',
        planned_at: shipment.planned_at ?? '',
        notes: shipment.notes ?? '',
        items_text: shipment.items_text ?? '',
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit shipment')} />
            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6">{__('Edit shipment')} <span className="font-mono">{shipment.shipment_no}</span></h1>
            <ShipmentForm action={`${basePath}/${shipment.id}`} method="put" initial={initial} submitLabel={__('Save Changes')} cancelHref={`${basePath}/${shipment.id}`} />
        </div>
    );
}

ShipmentEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
