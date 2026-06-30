import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function ViewTemplatesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'description', label: __('Description'), className: 'text-om-muted' },
        { key: 'lines', label: __('Lines using'), render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/view-templates/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(__('Delete view template ":name"?', { name: r.name }))) router.delete(`/admin/view-templates/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title={__('View Templates')} />
            <ResourceTable
                shape="view_templates"
                title={__('View Templates')}
                createHref="/admin/view-templates/create"
                createLabel={__('+ New Template')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No view templates yet.')}
            />
        </>
    );
}

ViewTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
