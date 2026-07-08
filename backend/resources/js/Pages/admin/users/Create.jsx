import { Head, useForm, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import UserForm from './UserForm';

export default function UserCreate() {
    const { roles = [], workstations = [], crews = [], wageGroups = [], skills = [] } = usePage().props;
    const form = useForm({
        account_type: 'user',
        name: '', username: '', email: '',
        password: '', password_confirmation: '',
        force_password_change: false,
        role: '', workstation_id: '',
        worker_code: '', worker_phone: '', worker_crew_id: '', worker_wage_group_id: '',
        skills: [],
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/users');
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__("New Account")} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("New Account")}</h1>
            <UserForm form={form} roles={roles} workstations={workstations} crews={crews} wageGroups={wageGroups} skills={skills} onSubmit={submit} />
        </div>
    );
}

UserCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
