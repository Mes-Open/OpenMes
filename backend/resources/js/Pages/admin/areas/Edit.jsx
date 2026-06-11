import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { areaFields } from './fields';

export default function AreaEdit() {
    const { area, sites = [] } = usePage().props;

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${area.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Area</h1>
            <ResourceForm
                action={`/admin/areas/${area.id}`}
                method="put"
                fields={areaFields(sites)}
                initial={{
                    site_id: area.site_id != null ? String(area.site_id) : '',
                    code: area.code ?? '',
                    name: area.name ?? '',
                    description: area.description ?? '',
                    is_active: !!area.is_active,
                    custom_fields: area.custom_fields ?? {},
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/areas"
            />
        </div>
    );
}

AreaEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
