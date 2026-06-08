import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkerForm from './WorkerForm';

export default function WorkerCreate() {
    const { crews = [], wageGroups = [], personnelClasses = [], skills = [] } = usePage().props;
    const form = useForm({
        code: '',
        name: '',
        email: '',
        phone: '',
        crew_id: '',
        wage_group_id: '',
        personnel_class_id: '',
        pay_type: '',
        pay_rate: '',
        is_active: true,
        skills: [],
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/workers');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Worker" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Worker</h1>
            <WorkerForm form={form} crews={crews} wageGroups={wageGroups} personnelClasses={personnelClasses} skills={skills} onSubmit={submit} />
        </div>
    );
}

WorkerCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
