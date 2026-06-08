import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

function asArray(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v) {
        try {
            const p = JSON.parse(v);
            return Array.isArray(p) ? p : [];
        } catch {
            return [];
        }
    }
    return [];
}

function planStatus(r) {
    if (!r.published_at) return { label: __('Draft'), cls: 'bg-amber-100 text-amber-800' };
    if (r.is_active) return { label: __('Published'), cls: 'bg-green-100 text-green-800' };
    return { label: __('Archived'), cls: 'bg-gray-100 text-gray-500' };
}

export default function InspectionPlansIndex() {
    const { materialNames = {}, materialTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        {
            key: 'version',
            label: __('Version'),
            className: 'text-gray-600 font-mono',
            render: (r) => `v${r.version ?? 1}`,
        },
        {
            key: 'scope',
            label: __('Scope'),
            className: 'text-gray-600',
            render: (r) =>
                r.material_id
                    ? `${__('Material')}: ${materialNames[r.material_id] ?? '?'}`
                    : r.material_type_id
                    ? `${__('Type')}: ${materialTypeNames[r.material_type_id] ?? '?'}`
                    : __('Generic'),
        },
        { key: 'criteria', label: __('Criteria'), render: (r) => asArray(r.criteria).length },
        {
            key: 'status',
            label: __('Status'),
            render: (r) => {
                const s = planStatus(r);
                return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${s.cls}`}>{s.label}</span>;
            },
        },
    ];

    const actions = (r) => {
        const items = [{ label: __('Edit'), href: `/admin/inspection-plans/${r.id}/edit` }];
        if (!r.published_at) {
            items.push({
                label: __('Publish'),
                className: 'text-green-700 hover:underline',
                onClick: () => {
                    if (confirm(__('Publish this version? It becomes the live plan used for new inspections.'))) {
                        router.post(`/admin/inspection-plans/${r.id}/publish`, {}, { preserveScroll: true });
                    }
                },
            });
        }
        items.push({
            label: __('Delete'),
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(__('Delete inspection plan ":name"?', { name: r.name }))) {
                    router.delete(`/admin/inspection-plans/${r.id}`, { preserveScroll: true });
                }
            },
        });
        return items;
    };

    return (
        <>
            <Head title={__('Inspection Plans')} />
            <ResourceTable
                shape="inspection_plans"
                title={__('Inspection Plans')}
                createHref="/admin/inspection-plans/create"
                createLabel={`+ ${__('New Plan')}`}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No inspection plans yet.')}
            />
        </>
    );
}

InspectionPlansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
