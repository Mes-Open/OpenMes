import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { areaFields, areaInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function AreasIndex() {
    // `sites` and `customFields` are optional props: the controller only sends
    // them once the drawer asks, so they're undefined until then.
    const { counts = {}, siteNames = {}, sites, customFields } = usePage().props;

    // Create and edit happen here rather than on their own pages, so you keep
    // your filters, paging and scroll. /admin/areas/create and /{id}/edit still
    // render the same form standalone.
    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text', link: true },
        { key: 'site', label: __('Site'), className: 'text-om-muted', value: (r) => siteNames[r.site_id] ?? '—', render: (r) => siteNames[r.site_id] ?? '—' },
        { key: 'lines', label: __('Lines'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        // The row is the record: `areas` syncs every column the form needs, so
        // the drawer opens filled in without a round-trip.
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/areas/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete area ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/areas/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Areas')} />
            <ResourceTable
                shape="areas"
                detailHref={(r) => `/admin/areas/${r.id}`}
                title={__('Areas')}
                createHref="/admin/areas/create"
                onCreate={drawer.create}
                createLabel={__('New Area')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No areas yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/areas"
                fields={areaFields(sites ?? [])}
                initial={areaInitial}
                customFields={customFields}
                ensure={['sites', 'customFields']}
                ready={sites !== undefined}
                title={{ create: __('New Area'), edit: __('Edit Area') }}
            />
        </>
    );
}

AreasIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
