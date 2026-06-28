// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useMemo, useState, useRef } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, Dropdown, StatusPill, TextField } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const STATUS_PILLS = {
    DONE: 'done',
    IN_PROGRESS: 'running',
    PENDING: 'pending',
};

function pillStatus(status) {
    return STATUS_PILLS[status] ?? 'pending';
}

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
            header: 'Zlecenie',
            cell: ({ row }) => (
                <span className="font-mono font-semibold text-om-ink">{row.original.order_no}</span>
            ),
        },
        {
            id: 'product',
            accessorFn: (r) => r.product_type?.name ?? '—',
            header: 'Produkt',
            cell: ({ row }) => (
                <span className="text-om-ink">{row.original.product_type?.name ?? '—'}</span>
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => (
                <StatusPill
                    status={pillStatus(row.original.status)}
                    label={(row.original.status ?? '').replace(/_/g, ' ')}
                />
            ),
        },
        {
            id: 'eans',
            header: 'Kody EAN',
            enableSorting: false,
            cell: ({ row }) => (
                (row.original.eans ?? []).length === 0 ? (
                    <span className="text-[11.5px] text-om-faint">Brak EAN</span>
                ) : (row.original.eans ?? []).map((ean) => (
                    <div key={ean.id} className="flex items-center gap-2 mb-1">
                        <span className="font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px]">
                            {ean.ean}
                        </span>
                        <button
                            type="button"
                            onClick={() => setEanToDelete(ean)}
                            className="text-[11.5px] text-om-blocked hover:underline transition-colors"
                        >
                            Usuń
                        </button>
                    </div>
                ))
            ),
        },
        {
            id: 'packed',
            accessorFn: (r) => r.packed_qty ?? 0,
            header: 'Spakowano / Plan',
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
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-1 text-[13px] text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-ink hover:underline">{__('Dashboard')}</Link>
                    <span className="mx-1">/</span>
                    <Link href="/packaging" className="hover:text-om-ink hover:underline">{__('Packaging')}</Link>
                    <span className="mx-1">/</span>
                    <span className="text-om-ink">{__('EAN Codes')}</span >
                </nav>

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-om-ink">{__('EAN Codes — Management')}</h1>
                        <p className="text-[12.5px] text-om-muted mt-1">{__('Assign barcodes to work orders')}</p>
                    </div>
                    <Link
                        href="/packaging"
                        className="inline-flex items-center justify-center rounded-om-sm bg-om-chip px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
                    >
                        &larr; {__('Packaging Overview')}
                    </Link>
                </div>

                {/* Add EAN form */}
                <div className="bg-om-card border border-om-line rounded-om p-5 mb-6">
                    <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink border-b border-om-line pb-2.5 mb-4">{__('Add EAN code')}</h2>
                    <form onSubmit={handleAddSubmit} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">{__('Production work order')}</label>
                            <Dropdown
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
                                className="w-full sm:w-auto"
                            >
                                {form.processing ? __('Adding…') : __('Add EAN')}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="bg-om-card border border-om-line rounded-om px-5 py-3 mb-4">
                    <div className="flex gap-3">
                        <TextField
                            className="flex-1"
                            value={searchVal}
                            onChange={setSearchVal}
                            placeholder={__('Search by order number…')}
                        />
                        <Button type="submit" variant="secondary">{__('Search')}</Button>
                        {hasSearch && (
                            <Button variant="ghost" onClick={handleClear}>
                                {__('Clear')}
                            </Button>
                        )}
                    </div>
                </form>

                {/* Table */}
                <div>
                    <DataTable
                        data={rows}
                        columns={columns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
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
