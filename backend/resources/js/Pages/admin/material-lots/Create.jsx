import { Head, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialLotFields, materialLotInitial } from './fields';

export default function MaterialLotCreate() {
    const { materials = [], sources = [], statuses = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Material Lot")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Material Lot")}</h1>
            <ResourceForm
                action="/admin/material-lots"
                method="post"
                fields={materialLotFields(materials, sources, statuses)}
                initial={materialLotInitial(null)}
                submitLabel="Create"
                cancelHref="/admin/material-lots"
            />
        </div>
    );
}

MaterialLotCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
