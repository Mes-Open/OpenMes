import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { materialFields } from './fields';

export default function MaterialEdit() {
    const { material, materialTypes = [] } = usePage().props;
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${material.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Material</h1>
            <ResourceForm
                action={`/admin/materials/${material.id}`}
                method="put"
                fields={materialFields(materialTypes)}
                initial={{
                    code: material.code ?? '',
                    name: material.name ?? '',
                    material_type_id: material.material_type_id != null ? String(material.material_type_id) : '',
                    unit_of_measure: material.unit_of_measure ?? '',
                    tracking_type: material.tracking_type ?? 'none',
                    default_scrap_percentage: material.default_scrap_percentage ?? '',
                    description: material.description ?? '',
                    external_code: material.external_code ?? '',
                    external_system: material.external_system ?? '',
                    is_active: !!material.is_active,
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/materials"
            />
        </div>
    );
}

MaterialEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
