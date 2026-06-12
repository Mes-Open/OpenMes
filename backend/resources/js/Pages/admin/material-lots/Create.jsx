import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialLotFields } from './fields';

export default function MaterialLotCreate() {
    const { materials = [], sources = [], statuses = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Material Lot" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Material Lot</h1>
            <ResourceForm
                action="/admin/material-lots"
                method="post"
                fields={materialLotFields(materials, sources, statuses)}
                initial={{
                    lot_number: '',
                    material_id: '',
                    source_id: '',
                    quantity_received: '',
                    quantity_available: '',
                    unit_of_measure: 'pcs',
                    received_at: '',
                    manufacturing_date: '',
                    expiry_date: '',
                    status: 'received',
                    supplier_lot_no: '',
                    supplier_reference: '',
                    source_container_no: '',
                }}
                submitLabel="Create"
                cancelHref="/admin/material-lots"
            />
        </div>
    );
}

MaterialLotCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
