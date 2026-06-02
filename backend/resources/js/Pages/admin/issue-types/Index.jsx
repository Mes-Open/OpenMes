import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { SEVERITY_LABELS } from './fields';

function severityBadgeClass(severity) {
    if (severity === 'CRITICAL' || severity === 'HIGH') {
        return 'bg-red-100 text-red-800';
    }
    if (severity === 'MEDIUM') {
        return 'bg-yellow-100 text-yellow-800';
    }
    return 'bg-gray-200 text-gray-600';
}

export default function IssueTypesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        {
            key: 'severity',
            label: 'Severity',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${severityBadgeClass(r.severity)}`}>
                    {SEVERITY_LABELS[r.severity] ?? r.severity}
                </span>
            ),
        },
        { key: 'is_blocking', label: 'Blocking', render: (r) => (r.is_blocking ? 'Yes' : 'No') },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/issue-types/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/issue-types/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete issue type "${r.name}"?`)) {
                    router.delete(`/admin/issue-types/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Issue Types" />
            <ResourceTable
                shape="issue_types_all"
                title="Issue Types"
                createHref="/admin/issue-types/create"
                createLabel="+ New Issue Type"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No issue types yet."
            />
        </>
    );
}

IssueTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
