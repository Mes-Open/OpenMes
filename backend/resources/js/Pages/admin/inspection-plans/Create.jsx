import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import InspectionPlanForm from './Form';

export default function InspectionPlanCreate() {
    const { materials = [], materialTypes = [] } = usePage().props;
    const form = useForm({
        name: '', description: '', scope: 'generic',
        material_id: '', material_type_id: '',
        criteria: [{ name: '', type: 'visual', required: true, unit: '', spec_min: '', spec_max: '' }],
        is_active: true,
    });
    const submit = (e) => { e.preventDefault(); form.post('/admin/inspection-plans'); };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Inspection Plan" />
            <h1 className="text-3xl font-bold text-om-ink mb-6">New Inspection Plan</h1>
            <InspectionPlanForm form={form} materials={materials} materialTypes={materialTypes} submitLabel="Create" onSubmit={submit} />
        </div>
    );
}

InspectionPlanCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
