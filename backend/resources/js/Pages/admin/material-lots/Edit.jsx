import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialLotFields } from './fields';

export default function MaterialLotEdit() {
    const { lot, materials = [], sources = [], statuses = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${lot.lot_number}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Material Lot</h1>
            <ResourceForm
                action={`/admin/material-lots/${lot.id}`}
                method="put"
                fields={materialLotFields(materials, sources, statuses)}
                initial={{
                    lot_number: lot.lot_number ?? '',
                    material_id: lot.material_id != null ? String(lot.material_id) : '',
                    source_id: lot.source_id != null ? String(lot.source_id) : '',
                    quantity_received: lot.quantity_received ?? '',
                    quantity_available: lot.quantity_available ?? '',
                    unit_of_measure: lot.unit_of_measure ?? '',
                    received_at: (lot.received_at ?? '').slice(0, 10),
                    manufacturing_date: (lot.manufacturing_date ?? '').slice(0, 10),
                    expiry_date: (lot.expiry_date ?? '').slice(0, 10),
                    status: lot.status ?? 'received',
                    supplier_lot_no: lot.supplier_lot_no ?? '',
                    supplier_reference: lot.supplier_reference ?? '',
                    source_container_no: lot.source_container_no ?? '',
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/material-lots"
            />
        </div>
    );
}

MaterialLotEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
