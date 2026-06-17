import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function CompaniesIndex() {
    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'type', label: __('Type'), className: 'text-om-muted' },
        { key: 'email', label: __('Email'), className: 'text-om-muted' },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/companies/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/companies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete company ":name"?', { name: r.name }))) {
                    router.delete(`/admin/companies/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Companies')} />
            <ResourceTable
                shape="companies"
                title={__('Companies')}
                createHref="/admin/companies/create"
                createLabel={__('+ New Company')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No companies yet.')}
            />
        </>
    );
}

CompaniesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
