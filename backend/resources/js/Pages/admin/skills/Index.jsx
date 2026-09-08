import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { skillFields, skillInitial } from './fields';

export default function SkillsIndex() {
    const drawer = useResourceDrawer();

    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'description', label: __('Description'), className: 'text-om-muted' },
        { key: 'workers', label: __('Workers'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete skill ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/skills/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Skills')} />
            <ResourceTable
                shape="skills"
                title={__('Skills')}
                createHref="/admin/skills/create"
                onCreate={drawer.create}
                createLabel={__('New Skill')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No skills yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/skills"
                fields={skillFields()}
                initial={skillInitial}
                title={{ create: __('New Skill'), edit: __('Edit Skill') }}
            />
        </>
    );
}

SkillsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
