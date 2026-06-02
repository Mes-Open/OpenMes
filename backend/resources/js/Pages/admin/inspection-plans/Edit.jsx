import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import InspectionPlanForm from './Form';

export default function InspectionPlanEdit() {
    const { plan, materials = [], materialTypes = [] } = usePage().props;
    const form = useForm({
        name: plan.name ?? '',
        description: plan.description ?? '',
        scope: plan.scope ?? 'generic',
        material_id: plan.material_id != null ? String(plan.material_id) : '',
        material_type_id: plan.material_type_id != null ? String(plan.material_type_id) : '',
        criteria: plan.criteria ?? [],
        is_active: !!plan.is_active,
    });
    const submit = (e) => { e.preventDefault(); form.put(`/admin/inspection-plans/${plan.id}`); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${plan.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Inspection Plan</h1>
            <InspectionPlanForm form={form} materials={materials} materialTypes={materialTypes} submitLabel="Save Changes" onSubmit={submit} />
        </div>
    );
}

InspectionPlanEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
