import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

// json columns may arrive from Electric as a parsed array or a JSON string.
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
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'skills', label: 'Req. Skills', render: (r) => asArray(r.required_skill_ids).length },
        { key: 'workers', label: 'Workers', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/personnel-classes/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete personnel class "${r.name}"?`)) {
                    router.delete(`/admin/personnel-classes/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Personnel Classes" />
            <ResourceTable
                shape="personnel_classes"
                title="Personnel Classes"
                createHref="/admin/personnel-classes/create"
                createLabel="+ New Class"
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText="No personnel classes yet."
            />
        </>
    );
}

PersonnelClassesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
