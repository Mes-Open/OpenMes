import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function CustomFieldsIndex() {
    const { definitions = [], entities = [] } = usePage().props;
    const [entity, setEntity] = useState('');

    const rows = entity ? definitions.filter((d) => d.entity_type === entity) : definitions;

    const toggle = (d) => router.post(`/admin/custom-fields/${d.id}/toggle-active`, {}, { preserveScroll: true });
    const destroy = (d) => {
        if (confirm(__('Delete custom field ":label"? Stored values on existing records are left untouched.', { label: d.label }))) {
            router.delete(`/admin/custom-fields/${d.id}`, { preserveScroll: true });
        }
    };

    const columns = useMemo(() => [
        {
            id: 'entity',
            accessorKey: 'entity_label',
            header: __('Entity'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.entity_label}</span>,
        },
        {
            id: 'key',
            accessorKey: 'key',
            header: __('Key'),
            cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.key}</span>,
        },
        {
            id: 'label',
            accessorKey: 'label',
            header: __('Label'),
            meta: { flex: true },
            cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.label}</span>,
        },
        {
            id: 'type',
            accessorKey: 'type_label',
            header: __('Type'),
            cell: ({ row }) => (
                <span className="text-om-muted">
                    {row.original.type_label}
                    {row.original.options_count > 0 && <span className="text-om-faint"> ({row.original.options_count})</span>}
                </span>
            ),
        },
        {
            id: 'required',
            accessorFn: (r) => (r.required ? 1 : 0),
            header: __('Required'),
            meta: { align: 'center' },
            cell: ({ row }) => (row.original.required ? __('Yes') : '—'),
        },
        {
            id: 'position',
            accessorKey: 'position',
            header: __('Position'),
            meta: { align: 'center' },
            cell: ({ row }) => <span className="text-om-muted">{row.original.position}</span>,
        },
        {
            id: 'status',
            accessorFn: (r) => (r.is_active ? 1 : 0),
            header: __('Status'),
            meta: { align: 'center' },
            cell: ({ row }) => (
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${row.original.is_active ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-muted'}`}>
                    {row.original.is_active ? __('Active') : __('Inactive')}
                </span>
            ),
        },
        {
            id: 'actions',
            header: __('Actions'),
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => {
                const d = row.original;
                return (
                    <div className="flex items-center justify-end gap-3 text-sm">
                        <Link href={`/admin/custom-fields/${d.id}/edit`} className="text-om-accent hover:text-om-accent">{__('Edit')}</Link>
                        <button type="button" onClick={() => toggle(d)} className="text-om-muted hover:text-om-ink">
                            {d.is_active ? __('Deactivate') : __('Activate')}
                        </button>
                        <button type="button" onClick={() => destroy(d)} className="text-om-blocked hover:text-om-blocked">{__('Delete')}</button>
                    </div>
                );
            },
        },
    ], []);

    return (
        <>
            <Head title={__('Custom Fields')} />
            <div className="max-w-6xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{__('Custom Fields')}</h1>
                        <p className="text-sm text-om-muted mt-1">{__('Admin-defined fields attached to records across the system.')}</p>
                    </div>
                    <Link href="/admin/custom-fields/create" className="btn-touch btn-primary text-sm">{__('+ New Custom Field')}</Link>
                </div>

                <div className="mb-4">
                    <Dropdown
                        value={entity == null ? '' : String(entity)}
                        onChange={(v) => setEntity(v)}
                        options={[
                            { value: '', label: __('All entities') },
                            ...entities.map((o) => ({ value: String(o.value), label: o.label })),
                        ]}
                        className="w-full"
                    />
                </div>

                <DataTable
                    data={rows}
                    columns={columns}
                    searchPlaceholder={__('Search custom fields…')}
                    emptyLabel={__('No custom fields yet.')}
                />
            </div>
        </>
    );
}

CustomFieldsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
