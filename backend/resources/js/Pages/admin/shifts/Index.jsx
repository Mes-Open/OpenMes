import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { shiftFields, shiftInitial } from './fields';

export default function ShiftsIndex() {
    const { lineNames = {}, lines, customFields } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted', filter: 'text' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'line', label: __('Line'), className: 'text-om-muted', value: (r) => (r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : __('Global')), render: (r) => (r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : __('Global')) },
        { key: 'start_time', label: __('Start'), className: 'text-om-muted', render: (r) => (r.start_time ?? '').slice(0, 5) },
        { key: 'end_time', label: __('End'), className: 'text-om-muted', render: (r) => (r.end_time ?? '').slice(0, 5) },
        { key: 'sort_order', label: __('Order'), className: 'text-om-muted' },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete shift ":name"?', { name: r.name }),
                confirmLabel: __('Delete shift'),
            },
            onClick: () => router.delete(`/admin/shifts/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Shifts')} />
            <ResourceTable
                shape="shifts"
                title={__('Shifts')}
                createHref="/admin/shifts/create"
                onCreate={drawer.create}
                createLabel={__('New Shift')}
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText={__('No shifts yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/shifts"
                fields={shiftFields(lines ?? [])}
                initial={shiftInitial}
                customFields={customFields}
                ensure={['lines', 'customFields']}
                ready={lines !== undefined}
                title={{ create: __('New Shift'), edit: __('Edit Shift') }}
            />
        </>
    );
}

ShiftsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
