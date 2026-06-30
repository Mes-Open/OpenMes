import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkerForm from './WorkerForm';
import { customFieldInitial, submitForm } from '../../../lib/customFieldForm';
import { __ } from '../../../lib/i18n';

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
        pay_type: worker.pay_type ?? '',
        pay_rate: worker.pay_rate != null ? String(worker.pay_rate) : '',
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
            <Head title={__('Edit :name', { name: worker.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Worker')}</h1>
            <WorkerForm form={form} crews={crews} wageGroups={wageGroups} personnelClasses={personnelClasses} customFields={customFields} skills={skills} isEdit onSubmit={submit} />
        </div>
    );
}

WorkerEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
