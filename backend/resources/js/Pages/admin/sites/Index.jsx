import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { siteFields, siteInitial } from './fields';

export default function SitesIndex() {
    const { counts = {}, companyNames = {}, companies, customFields } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', link: true },
        { key: 'company', label: __('Company'), className: 'text-om-muted', value: (r) => companyNames[r.company_id] ?? '—', render: (r) => companyNames[r.company_id] ?? '—' },
        { key: 'city', label: __('City'), className: 'text-om-muted' },
        { key: 'areas', label: __('Areas'), value: (r) => counts[r.id]?.areas ?? 0, render: (r) => counts[r.id]?.areas ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/sites/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete site ":name"?', { name: r.name }),
                confirmLabel: __('Delete site'),
            },
            onClick: () => router.delete(`/admin/sites/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Sites')} />
            <ResourceTable
                shape="sites"
                detailHref={(r) => `/admin/sites/${r.id}`}
                title={__('Sites')}
                createHref="/admin/sites/create"
                onCreate={drawer.create}
                createLabel={__('New Site')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No sites yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/sites"
                fields={siteFields(companies ?? [])}
                initial={siteInitial}
                customFields={customFields}
                ensure={['companies', 'customFields']}
                ready={companies !== undefined}
                title={{ create: __('New Site'), edit: __('Edit Site') }}
            />
        </>
    );
}

SitesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
