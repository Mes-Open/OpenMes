import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function ShiftsIndex() {
    const { lineNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'line', label: __('Line'), className: 'text-gray-600', render: (r) => (r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : __('Global')) },
        { key: 'start_time', label: __('Start'), className: 'text-gray-600', render: (r) => (r.start_time ?? '').slice(0, 5) },
        { key: 'end_time', label: __('End'), className: 'text-gray-600', render: (r) => (r.end_time ?? '').slice(0, 5) },
        { key: 'sort_order', label: __('Order'), className: 'text-gray-600' },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/shifts/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete shift ":name"?', { name: r.name }))) {
                    router.delete(`/admin/shifts/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Shifts')} />
            <ResourceTable
                shape="shifts"
                title={__('Shifts')}
                createHref="/admin/shifts/create"
                createLabel={__('+ New Shift')}
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText={__('No shifts yet.')}
            />
        </>
    );
}

ShiftsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
