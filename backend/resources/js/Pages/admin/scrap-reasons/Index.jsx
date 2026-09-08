import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { scrapCategoryOptions, scrapReasonFields, scrapReasonInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function ScrapReasonsIndex() {
    const drawer = useResourceDrawer();

    const { counts = {} } = usePage().props;
    const categoryLabels = Object.fromEntries(scrapCategoryOptions().map((c) => [c.value, c.label]));

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'category', label: __('Category'), className: 'text-om-muted', value: (r) => categoryLabels[r.category] ?? r.category, render: (r) => categoryLabels[r.category] ?? r.category },
        { key: 'scrap_entries', label: __('Used'), align: 'right', value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            onClick: () => router.post(`/admin/scrap-reasons/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            className: 'text-om-blocked hover:underline',
            confirm: {
                title: __('Delete scrap reason ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/scrap-reasons/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Scrap Reasons')} />
            <ResourceTable
                shape="scrap_reasons"
                title={__('Scrap Reasons')}
                createHref="/admin/scrap-reasons/create"
                onCreate={drawer.create}
                createLabel={__('New Reason')}
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText={__('No scrap reasons yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/scrap-reasons"
                fields={scrapReasonFields()}
                initial={scrapReasonInitial}
                title={{ create: __('New Scrap Reason'), edit: __('Edit Scrap Reason') }}
            />
        </>
    );
}

ScrapReasonsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
