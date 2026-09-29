import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import WorkerForm from './WorkerForm';
import { moduleFieldInitial } from '../../../lib/moduleFields';
import { customFieldInitial, submitForm } from '../../../lib/customFieldForm';
import { __ } from '../../../lib/i18n';

export default function WorkerCreate() {
    const { crews = [], wageGroups = [], personnelClasses = [], skills = [], customFields = [], hooks = {} } = usePage().props;
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
        is_logistics: false,
        skills: [],
        ...customFieldInitial(),
        ...moduleFieldInitial(hooks, 'display.admin.workers.form.fields'),
    });

    const submit = (e) => {
        e.preventDefault();
        submitForm(form, 'post', '/admin/workers');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Worker')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Worker')}</h1>
            <WorkerForm form={form} crews={crews} wageGroups={wageGroups} personnelClasses={personnelClasses} customFields={customFields} skills={skills} hooks={hooks} onSubmit={submit} />
        </div>
    );
}

WorkerCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
