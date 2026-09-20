import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, Dropdown, Icon, StatusBadge, TextField } from '@openmes/ui';
import AppDataTable from '../../../components/AppDataTable';
import { woStatusBadge } from '../../admin/work-orders/fields';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';
import PageTrail from '../../../components/PageTrail';

export default function EansIndex() {
    const { workOrders = {} } = usePage().props;

    // workOrders is a Laravel paginator object with data, links, etc.
    const rows = workOrders.data ?? [];
    const pagination = workOrders;

    // Add EAN form
    const form = useForm({ work_order_id: '', ean: '' });

    const handleAddSubmit = (e) => {
        e.preventDefault();
        form.post('/packaging/eans', {
            onSuccess: () => form.reset(),
            preserveScroll: true,
        });
    };

    // Pending delete — ConfirmDialog replaces the old window.confirm()
    const [eanToDelete, setEanToDelete] = useState(null);

    const confirmDelete = () => {
        if (eanToDelete) {
            router.delete(`/packaging/eans/${eanToDelete.id}`, { preserveScroll: true });
        }
        setEanToDelete(null);
    };

    // Search state — uses a plain GET navigation
    const [searchVal, setSearchVal] = useState(() => {
        if (typeof window !== 'undefined') {
            return new URLSearchParams(window.location.search).get('search') ?? '';
        }
        return '';
    });

    const handleSearch = (e) => {
        e.preventDefault();
        const params = {};
        if (searchVal) params.search = searchVal;
        router.get('/packaging/eans', params, { preserveState: false });
    };

    const handleClear = () => {
        setSearchVal('');
        router.get('/packaging/eans', {}, { preserveState: false });
    };

    const hasSearch = searchVal !== '' ||
        (typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('search'));

    const columns = useMemo(() => [
        {
            id: 'order_no',
            accessorKey: 'order_no',
            header: __('Work Order'),
            cell: ({ row }) => (
                <span className="font-mono font-semibold text-om-ink">{row.original.order_no}</span>
            ),
        },
        {
            id: 'product',
            accessorFn: (r) => r.product_type?.name ?? '—',
            header: __('Product'),
            cell: ({ row }) => (
                <span className="text-om-ink">{row.original.product_type?.name ?? '—'}</span>
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => (
                <StatusBadge size="sm" {...woStatusBadge(row.original.status)} />
            ),
        },
        {
            id: 'eans',
            header: __('EAN codes'),
            enableSorting: false,
            cell: ({ row }) => (
                (row.original.eans ?? []).length === 0 ? (
                    <span className="text-[11.5px] text-om-faint">{__('No EAN')}</span>
                ) : (row.original.eans ?? []).map((ean) => (
                    <div key={ean.id} className="flex items-center gap-2 mb-1">
                        <span className="font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px]">
                            {ean.ean}
                        </span>
                        <button
                            type="button"
                            onClick={() => setEanToDelete(ean)}
                            className="inline-flex items-center gap-1 text-[11.5px] text-om-blocked hover:underline transition-colors"
                        >
                            <Icon name="trash-2" size={13} /> {__('Delete')}
                        </button>
                    </div>
                ))
            ),
        },
        {
            id: 'packed',
            accessorFn: (r) => r.packed_qty ?? 0,
            header: __('Packed / Planned'),
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="font-mono text-om-muted">
                    <span className="font-semibold text-om-ink">{row.original.packed_qty ?? 0}</span>
                    <span className="text-om-faint"> / {parseInt(row.original.planned_qty ?? 0, 10)}</span>
                </span>
            ),
        },
    ], []);

    return (
        <>
            <Head title={__('EAN Codes — Management')} />
            <div className="w-full px-4 py-5 sm:px-6 sm:py-6">
                {/* Breadcrumbs */}
                <PageTrail />

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-[28px] font-semibold tracking-[-0.02em] text-om-ink">{__('EAN Codes — Management')}</h1>
                        <p className="text-[12.5px] text-om-muted mt-1">{__('Assign barcodes to work orders')}</p>
                    </div>
                    <Link
                        href="/packaging"
                        className="inline-flex items-center justify-center gap-2 rounded-om-sm border border-om-line bg-om-card px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
                    >
                        <Icon name="arrow-left" size={14} /> {__('Packaging Overview')}
                    </Link>
                </div>

                {/* Add EAN form */}
                <div className="bg-om-card border border-om-line rounded-om p-5 mb-6">
                    <h2 className="flex items-center gap-2 text-[15px] font-semibold tracking-[-0.01em] text-om-ink border-b border-om-line pb-2.5 mb-4"><Icon name="barcode" size={17} />{__('Add EAN code')}</h2>
                    <form onSubmit={handleAddSubmit} className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-4">
                        <div>
                            <div className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">{__('Production work order')}</div>
                            <Dropdown
                                aria-label={__('Production work order')}
                                value={form.data.work_order_id == null ? '' : String(form.data.work_order_id)}
                                onChange={(v) => form.setData('work_order_id', v)}
                                placeholder={__('— select work order —')}
                                options={rows.map((wo) => ({
                                    value: String(wo.id),
                                    label: `${wo.order_no}${wo.product_type ? ` — ${wo.product_type.name}` : ''}`,
                                }))}
                                className="w-full"
                            />
                            {form.errors.work_order_id && (
                                <p className="text-[11.5px] text-om-blocked mt-1">{form.errors.work_order_id}</p>
                            )}
                        </div>
                        <TextField
                            label={__('EAN code')}
                            mono
                            value={form.data.ean}
                            onChange={(v) => form.setData('ean', v)}
                            error={form.errors.ean}
                            placeholder={__('e.g. 5901234123457')}
                            required
                            maxLength={100}
                        />
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                variant="primary"
                                loading={form.processing}
                                leftIcon={<Icon name="plus" size={14} />}
                                className="w-full sm:w-auto"
                            >
                                {form.processing ? __('Adding…') : __('Add EAN')}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="bg-om-card border border-om-line rounded-om px-5 py-3 mb-4">
                    <div className="flex flex-wrap gap-3">
                        <TextField
                            className="min-w-0 flex-1"
                            value={searchVal}
                            onChange={setSearchVal}
                            placeholder={__('Search by order number…')}
                        />
                        <Button type="submit" variant="outline" leftIcon={<Icon name="search" size={14} />}>{__('Search')}</Button>
                        {hasSearch && (
                            <Button variant="ghost" onClick={handleClear} leftIcon={<Icon name="x" size={14} />}>
                                {__('Clear')}
                            </Button>
                        )}
                    </div>
                </form>

                {/* Table */}
                <div className="overflow-hidden rounded-om border border-om-line bg-om-card">
                    <AppDataTable
                        data={rows}
                        columns={columns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        bodyMaxHeight="none"
                        emptyLabel={__('No results found')}
                    />

                    {/* Pagination links */}
                    {pagination.last_page > 1 && (
                        <div className="mt-3 flex items-center gap-2 flex-wrap text-[13px]">
                            {(pagination.links ?? []).map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: false })}
                                    className={`px-3 py-1 rounded-om-sm border text-[13px] transition-colors ${
                                        link.active
                                            ? 'bg-om-ink text-om-on-ink border-om-ink'
                                            : link.url
                                            ? 'border-om-line text-om-ink hover:bg-om-chip'
                                            : 'border-om-line2 text-om-faintest cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Delete-EAN confirmation (replaces window.confirm) */}
            <ConfirmDialog
                open={!!eanToDelete}
                onClose={() => setEanToDelete(null)}
                onConfirm={confirmDelete}
                title={eanToDelete ? __('Delete EAN code :ean?', { ean: eanToDelete.ean }) : ''}
                confirmLabel={__('Delete')}
                cancelLabel={__('Cancel')}
            />
        </>
    );
}

EansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
