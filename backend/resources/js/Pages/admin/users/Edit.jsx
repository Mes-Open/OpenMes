import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';
import AppLayout from '../../../layouts/AppLayout';
import UserForm from './UserForm';

export default function UserEdit() {
    const { user, assignments = {}, roles = [], workstations = [], crews = [], wageGroups = [], skills = [] } = usePage().props;
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
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__("Edit Account")}</h1>
            {user.role === 'Operator' && <section className="card mb-6 max-w-3xl space-y-3">
                <h2 className="font-semibold">{__('Operator assignments')}</h2>
                <p className="text-sm text-om-muted">{__('Line access, the regular worker station and the station selected at login are separate. Check all three before starting production.')}</p>
                <div>{__('Line access')}: {(assignments.lines ?? []).length ? assignments.lines.map(line => <Link key={line.id} className="ml-2 underline" href={`/admin/lines/${line.id}`}>{line.name}</Link>) : <Link className="underline" href="/admin/lines">{__('Assign a line')}</Link>}</div>
                <div>{__('Regular workstation')}: {assignments.station ? <Link className="underline" href={`/admin/lines/${assignments.station.line_id}/workstations/${assignments.station.id}/edit`}>{assignments.station.name}</Link> : <Link className="underline" href="/admin/lines">{__('Create a worker profile below, then assign it to a workstation.')}</Link>}</div>
                <p className="text-sm text-om-muted">{__('The operator selects the working station after login using Change line.')}</p>
            </section>}
            <UserForm form={form} roles={roles} workstations={workstations} crews={crews} wageGroups={wageGroups} skills={skills} isEdit onSubmit={submit} />
        </div>
    );
}

UserEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
