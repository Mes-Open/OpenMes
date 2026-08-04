import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

// json columns may arrive from live sync as a parsed array or a JSON string.
function asArray(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v) {
        try { const p = JSON.parse(v); return Array.isArray(p) ? p : []; } catch { return []; }
    }
    return [];
}

export default function PersonnelClassesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'skills', label: __('Req. Skills'), value: (r) => asArray(r.required_skill_ids).length, render: (r) => asArray(r.required_skill_ids).length },
        { key: 'workers', label: __('Workers'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/personnel-classes/${r.id}/edit` },
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
                createLabel={__('New Class')}
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText={__('No personnel classes yet.')}
            />
        </>
    );
}

PersonnelClassesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
