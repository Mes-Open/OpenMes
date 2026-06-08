import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

export default function SkillsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'description', label: 'Description', className: 'text-gray-600' },
        { key: 'workers', label: 'Workers', render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/skills/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete skill "${r.name}"?`)) {
                    router.delete(`/admin/skills/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Skills" />
            <ResourceTable
                shape="skills"
                title="Skills"
                createHref="/admin/skills/create"
                createLabel="+ New Skill"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No skills yet."
            />
        </>
    );
}

SkillsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
