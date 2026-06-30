import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import UserForm from './UserForm';

export default function UserEdit() {
    const { user, roles = [], workstations = [], crews = [], wageGroups = [], skills = [] } = usePage().props;
    const w = user.worker;

    const form = useForm({
        account_type: user.account_type ?? 'user',
        name: user.name ?? '',
        username: user.username ?? '',
        email: user.email ?? '',
        password: '', password_confirmation: '',
        force_password_change: !!user.force_password_change,
        role: user.role ?? '',
        workstation_id: user.workstation_id != null ? String(user.workstation_id) : '',
        worker_code: w?.code ?? '',
        worker_phone: w?.phone ?? '',
        worker_crew_id: w?.crew_id != null ? String(w.crew_id) : '',
        worker_wage_group_id: w?.wage_group_id != null ? String(w.wage_group_id) : '',
        skills: w?.skills ?? [],
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/admin/users/${user.id}`);
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${user.name}`} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">Edit Account</h1>
            <UserForm form={form} roles={roles} workstations={workstations} crews={crews} wageGroups={wageGroups} skills={skills} isEdit onSubmit={submit} />
        </div>
    );
}

UserEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
