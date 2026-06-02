import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { SCRAP_CATEGORIES } from './fields';

const CATEGORY_LABELS = Object.fromEntries(SCRAP_CATEGORIES.map((c) => [c.value, c.label]));

export default function ScrapReasonsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'category', label: 'Category', className: 'text-gray-600', render: (r) => CATEGORY_LABELS[r.category] ?? r.category },
        { key: 'scrap_entries', label: 'Used', align: 'right', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/scrap-reasons/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/scrap-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete scrap reason "${r.name}"?`)) {
                    router.delete(`/admin/scrap-reasons/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Scrap Reasons" />
            <ResourceTable
                shape="scrap_reasons"
                title="Scrap Reasons"
                createHref="/admin/scrap-reasons/create"
                createLabel="+ New Reason"
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText="No scrap reasons yet."
            />
        </>
    );
}

ScrapReasonsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
