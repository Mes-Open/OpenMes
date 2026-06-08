import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

function asArray(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v) { try { const p = JSON.parse(v); return Array.isArray(p) ? p : []; } catch { return []; } }
    return [];
}

export default function InspectionPlansIndex() {
    const { materialNames = {}, materialTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        {
            key: 'scope', label: 'Scope', className: 'text-gray-600',
            render: (r) => r.material_id ? `Material: ${materialNames[r.material_id] ?? '?'}`
                : r.material_type_id ? `Type: ${materialTypeNames[r.material_type_id] ?? '?'}`
                    : 'Generic',
        },
        { key: 'criteria', label: 'Criteria', render: (r) => asArray(r.criteria).length },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/inspection-plans/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(`Delete inspection plan "${r.name}"?`)) router.delete(`/admin/inspection-plans/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title="Inspection Plans" />
            <ResourceTable
                shape="inspection_plans"
                title="Inspection Plans"
                createHref="/admin/inspection-plans/create"
                createLabel="+ New Plan"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No inspection plans yet."
            />
        </>
    );
}

InspectionPlansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
