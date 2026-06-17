import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, ConfirmDialog, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDateTime } from '../../../lib/i18n';

/**
 * Admin Trash — soft-deleted rows across every domain entity, with who
 * deleted them and a one-click restore (restore cascades to the children
 * deleted together with the row). Geist White.
 */
export default function TrashIndex() {
    const { items = [], counts = {}, selectedType = null } = usePage().props;
    const [toRestore, setToRestore] = useState(null);

    const typeLabel = (type) => type.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());

    const restore = () => {
        if (toRestore) {
            router.post(`/admin/trash/${toRestore.type}/${toRestore.id}/restore`, {}, { preserveScroll: true });
        }
        setToRestore(null);
    };

    const columns = useMemo(
        () => [
            {
                id: 'type',
                accessorFn: (r) => typeLabel(r.type),
                header: __('Type'),
                cell: ({ row }) => <span className="text-om-muted">{typeLabel(row.original.type)}</span>,
            },
            {
                id: 'item',
                accessorKey: 'label',
                header: __('Item'),
                cell: ({ row }) => <span className="font-mono font-medium text-om-ink">{row.original.label}</span>,
            },
            {
                id: 'deleted_by',
                accessorKey: 'deleted_by',
                header: __('Deleted by'),
                cell: ({ row }) => <span className="text-om-muted">{row.original.deleted_by ?? '—'}</span>,
            },
            {
                id: 'deleted_at',
                accessorFn: (r) => r.deleted_at,
                header: __('Deleted at'),
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-om-muted">{formatDateTime(row.original.deleted_at)}</span>
                ),
            },
            {
                id: '_restore',
                header: '',
                enableSorting: false,
                enableHiding: false,
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <Button variant="secondary" onClick={() => setToRestore(row.original)}>
                        {__('Restore')}
                    </Button>
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    return (
        <>
            <Head title={__('Trash')} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                    <div>
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Trash')}</h1>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Deleted items are kept here and can be restored. Restoring also brings back records deleted together with the item.')}
                        </p>
                    </div>
                    <Dropdown
                        className="w-full sm:w-72"
                        value={selectedType ?? ''}
                        onChange={(v) => router.get('/admin/trash', v ? { type: v } : {}, { preserveState: true })}
                        options={[
                            { value: '', label: __('All types') },
                            ...Object.entries(counts).map(([type, count]) => ({
                                value: type,
                                label: `${typeLabel(type)} (${count})`,
                            })),
                        ]}
                    />
                </div>

                <DataTable
                    data={items}
                    columns={columns}
                    searchPlaceholder={__('Search…')}
                    columnsLabel={__('Columns')}
                    columnsMenuLabel={__('Toggle columns')}
                    emptyLabel={__('Trash is empty.')}
                    rangeLabel={(start, end, total) => (total === 0 ? __('0 results') : `${start}–${end} / ${total}`)}
                    pageSize={15}
                />
            </div>

            <ConfirmDialog
                open={!!toRestore}
                onClose={() => setToRestore(null)}
                onConfirm={restore}
                title={__('Restore this item?')}
                confirmLabel={__('Restore')}
                cancelLabel={__('Cancel')}
            >
                {__('Restoring also brings back records deleted together with it.')}
            </ConfirmDialog>
        </>
    );
}

TrashIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
