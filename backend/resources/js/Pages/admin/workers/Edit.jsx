import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkerForm from './WorkerForm';
import { customFieldInitial, submitForm } from '../../../lib/customFieldForm';

export default function WorkerEdit() {
    const { worker, crews = [], wageGroups = [], personnelClasses = [], skills = [], customFields = [] } = usePage().props;

    const form = useForm({
        code: worker.code ?? '',
        name: worker.name ?? '',
        email: worker.email ?? '',
        phone: worker.phone ?? '',
        crew_id: worker.crew_id != null ? String(worker.crew_id) : '',
        wage_group_id: worker.wage_group_id != null ? String(worker.wage_group_id) : '',
        personnel_class_id: worker.personnel_class_id != null ? String(worker.personnel_class_id) : '',
        is_active: !!worker.is_active,
        skills: worker.skills ?? [],
        ...customFieldInitial(worker.custom_fields),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, 'put', `/admin/workers/${worker.id}`);
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${worker.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Worker</h1>
            <WorkerForm form={form} crews={crews} wageGroups={wageGroups} personnelClasses={personnelClasses} skills={skills} customFields={customFields} isEdit onSubmit={submit} />
        </div>
    );
}

WorkerEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
