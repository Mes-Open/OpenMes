import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialLotFields, materialLotInitial } from './fields';

export default function MaterialLotEdit() {
    const { lot, materials = [], sources = [], statuses = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${lot.lot_number}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Material Lot")}</h1>
            <ResourceForm
                action={`/admin/material-lots/${lot.id}`}
                method="put"
                fields={materialLotFields(materials, sources, statuses)}
                initial={materialLotInitial(lot)}
                submitLabel="Save Changes"
                cancelHref="/admin/material-lots"
            />
        </div>
    );
}

MaterialLotEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
