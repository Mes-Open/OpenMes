import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { COMPANY_FIELDS, companyInitial } from './fields';

export default function CompaniesIndex() {
    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'type', label: __('Type'), className: 'text-om-muted' },
        { key: 'email', label: __('Email'), className: 'text-om-muted' },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/companies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete company ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/companies/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Companies')} />
            <ResourceTable
                shape="companies"
                title={__('Companies')}
                createHref="/admin/companies/create"
                onCreate={drawer.create}
                createLabel={__('New Company')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No companies yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/companies"
                fields={COMPANY_FIELDS}
                initial={companyInitial}
                title={{ create: __('New Company'), edit: __('Edit Company') }}
            />
        </>
    );
}

CompaniesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
