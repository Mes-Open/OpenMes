import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import PersonnelClassForm from './Form';
import { __ } from '../../../lib/i18n';

// json columns may arrive from live sync as a parsed array or a JSON string.
function asArray(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v) {
        try { const p = JSON.parse(v); return Array.isArray(p) ? p : []; } catch { return []; }
    }
    return [];
}

// Same normalisation for the levels map: a synced json column arrives as a string.
function asObject(v) {
    if (v && typeof v === 'object' && !Array.isArray(v)) return v;
    if (typeof v === 'string' && v) {
        try { const p = JSON.parse(v); return p && typeof p === 'object' && !Array.isArray(p) ? p : {}; } catch { return {}; }
    }
    return {};
}

/** The drawer's half of the split form: it owns the useForm the shared PersonnelClassForm binds to. */
function PersonnelClassDrawerForm({ editing, record, finish, skills, levels }) {
    const form = useForm({
        code: record?.code ?? '',
        name: record?.name ?? '',
        description: record?.description ?? '',
        required_skill_ids: asArray(record?.required_skill_ids).map(Number),
        default_required_cert_level: asObject(record?.default_required_cert_level),
        is_active: record ? !!record.is_active : true,
        stay: 1,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: finish };
        if (editing) form.put(`/admin/personnel-classes/${record.id}`, opts);
        else form.post('/admin/personnel-classes', opts);
    };

    return (
        <PersonnelClassForm
            bare
            form={form}
            skills={skills}
            levels={levels}
            submitLabel={editing ? __('Save Changes') : __('Create')}
            onSubmit={submit}
            onCancel={finish}
        />
    );
}

export default function PersonnelClassesIndex() {
    const drawer = useResourceDrawer();

    const { counts = {}, skills, levels } = usePage().props;
    const formReady = skills !== undefined && levels !== undefined;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', link: true },
        { key: 'skills', label: __('Req. Skills'), value: (r) => asArray(r.required_skill_ids).length, render: (r) => asArray(r.required_skill_ids).length },
        { key: 'workers', label: __('Workers'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete personnel class ":name"?', { name: r.name }),
                confirmLabel: __('Delete personnel class'),
            },
            onClick: () => router.delete(`/admin/personnel-classes/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Personnel Classes')} />
            <ResourceTable
                shape="personnel_classes"
                detailHref={(r) => `/admin/personnel-classes/${r.id}`}
                title={__('Personnel Classes')}
                createHref="/admin/personnel-classes/create"
                onCreate={drawer.create}
                createLabel={__('New Class')}
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText={__('No personnel classes yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                ensure={['skills', 'levels']}
                ready={formReady}
                title={{ create: __('New Personnel Class'), edit: __('Edit Personnel Class') }}
                render={({ editing, record, finish }) => (
                    <PersonnelClassDrawerForm
                        editing={editing}
                        record={record}
                        finish={finish}
                        skills={skills ?? []}
                        levels={levels ?? []}
                    />
                )}
            />
        </>
    );
}

PersonnelClassesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
