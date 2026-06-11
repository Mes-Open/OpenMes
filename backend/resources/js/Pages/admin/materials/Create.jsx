import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialFields } from './fields';

export default function MaterialCreate() {
    const { materialTypes = [], customFields = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Material" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Material</h1>
            <ResourceForm
                action="/admin/materials"
                method="post"
                fields={materialFields(materialTypes)}
                customFields={customFields}
                initial={{
                    code: '', name: '', material_type_id: '', unit_of_measure: 'pcs',
                    tracking_type: 'none', default_scrap_percentage: '', description: '',
                    external_code: '', external_system: '', is_active: true, custom_fields: {},
                }}
                submitLabel="Create"
                cancelHref="/admin/materials"
            />
        </div>
    );
}

MaterialCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
