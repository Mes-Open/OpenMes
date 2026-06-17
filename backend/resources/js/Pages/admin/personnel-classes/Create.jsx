import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import PersonnelClassForm from './Form';
import { __ } from '../../../lib/i18n';

export default function PersonnelClassCreate() {
    const { skills = [], levels = [] } = usePage().props;
    const form = useForm({
        code: '',
        name: '',
        description: '',
        required_skill_ids: [],
        default_required_cert_level: {},
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/personnel-classes');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Personnel Class')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Personnel Class')}</h1>
            <PersonnelClassForm form={form} skills={skills} levels={levels} submitLabel={__('Create')} onSubmit={submit} />
        </div>
    );
}

PersonnelClassCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
