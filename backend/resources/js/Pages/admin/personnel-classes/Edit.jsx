import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import PersonnelClassForm from './Form';
import { __ } from '../../../lib/i18n';

export default function PersonnelClassEdit() {
    const { personnelClass, skills = [], levels = [] } = usePage().props;
    const form = useForm({
        code: personnelClass.code ?? '',
        name: personnelClass.name ?? '',
        description: personnelClass.description ?? '',
        required_skill_ids: personnelClass.required_skill_ids ?? [],
        default_required_cert_level: personnelClass.default_required_cert_level ?? {},
        is_active: !!personnelClass.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/admin/personnel-classes/${personnelClass.id}`);
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: personnelClass.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Personnel Class')}</h1>
            <PersonnelClassForm form={form} skills={skills} levels={levels} submitLabel={__('Save Changes')} onSubmit={submit} />
        </div>
    );
}

PersonnelClassEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
