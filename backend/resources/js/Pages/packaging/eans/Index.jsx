// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useState, useRef } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, StatusPill, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';

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

    return (
        <>
            <Head title="Zarządzanie kodami EAN" />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-1 text-[13px] text-om-muted mb-4">
                    <Link href="/admin/dashboard" className="hover:text-om-ink hover:underline">Dashboard</Link>
                    <span className="mx-1">/</span>
                    <Link href="/packaging" className="hover:text-om-ink hover:underline">Pakowanie</Link>
                    <span className="mx-1">/</span>
                    <span className="text-om-ink">Kody EAN</span>
                </nav>

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-[-0.02em] text-om-ink">Kody EAN &mdash; Zarządzanie</h1>
                        <p className="text-[12.5px] text-om-muted mt-1">Przypisuj kody kreskowe do zleceń produkcyjnych</p>
                    </div>
                    <Link
                        href="/packaging"
                        className="inline-flex items-center justify-center rounded-om-sm bg-om-chip px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
                    >
                        &larr; Przegląd pakowania
                    </Link>
                </div>

                {/* Add EAN form */}
                <div className="bg-om-card border border-om-line rounded-om p-5 mb-6">
                    <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink border-b border-om-line pb-2.5 mb-4">Dodaj kod EAN</h2>
                    <form onSubmit={handleAddSubmit} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">Zlecenie produkcyjne</label>
                            <select
                                name="work_order_id"
                                value={form.data.work_order_id}
                                onChange={(e) => form.setData('work_order_id', e.target.value)}
                                className="w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]"
                                required
                            >
                                <option value="">— wybierz zlecenie —</option>
                                {rows.map((wo) => (
                                    <option key={wo.id} value={wo.id}>
                                        {wo.order_no}{wo.product_type ? ` — ${wo.product_type.name}` : ''}
                                    </option>
                                ))}
                            </select>
                            {form.errors.work_order_id && (
                                <p className="text-[11.5px] text-om-blocked mt-1">{form.errors.work_order_id}</p>
                            )}
                        </div>
                        <TextField
                            label="Kod EAN"
                            mono
                            value={form.data.ean}
                            onChange={(v) => form.setData('ean', v)}
                            error={form.errors.ean}
                            placeholder="np. 5901234123457"
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
                                {form.processing ? 'Dodawanie…' : 'Dodaj EAN'}
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
                            placeholder="Szukaj po numerze zlecenia…"
                        />
                        <Button type="submit" variant="secondary">Szukaj</Button>
                        {hasSearch && (
                            <Button variant="ghost" onClick={handleClear}>
                                Wyczyść
                            </Button>
                        )}
                    </div>
                </form>

                {/* Table */}
                <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-om-line text-[13px]">
                            <thead className="bg-om-panel">
                                <tr>
                                    <th className="px-4 py-2.5 text-left font-mono text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint">Zlecenie</th>
                                    <th className="px-4 py-2.5 text-left font-mono text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint">Produkt</th>
                                    <th className="px-4 py-2.5 text-left font-mono text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint">Status</th>
                                    <th className="px-4 py-2.5 text-left font-mono text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint">Kody EAN</th>
                                    <th className="px-4 py-2.5 text-right font-mono text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint">Spakowano / Plan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10 text-center text-om-faint text-[12.5px]">Brak wyników</td>
                                    </tr>
                                ) : rows.map((wo) => (
                                    <tr key={wo.id}>
                                        <td className="px-4 py-3 font-mono font-semibold text-om-ink">{wo.order_no}</td>
                                        <td className="px-4 py-3 text-om-ink">{wo.product_type?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <StatusPill
                                                status={pillStatus(wo.status)}
                                                label={(wo.status ?? '').replace(/_/g, ' ')}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {(wo.eans ?? []).length === 0 ? (
                                                <span className="text-[11.5px] text-om-faint">Brak EAN</span>
                                            ) : (wo.eans ?? []).map((ean) => (
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
                                            ))}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-om-muted">
                                            <span className="font-semibold text-om-ink">{wo.packed_qty ?? 0}</span>
                                            <span className="text-om-faint"> / {parseInt(wo.planned_qty ?? 0, 10)}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination links */}
                    {pagination.last_page > 1 && (
                        <div className="px-4 py-3 border-t border-om-line flex items-center gap-2 flex-wrap text-[13px]">
                            {(pagination.links ?? []).map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: false })}
                                    className={`px-3 py-1 rounded-om-sm border text-[13px] transition-colors ${
                                        link.active
                                            ? 'bg-om-ink text-white border-om-ink'
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
                title={eanToDelete ? `Usunąć kod EAN ${eanToDelete.ean}?` : ''}
                confirmLabel="Usuń"
                cancelLabel="Anuluj"
            />
        </>
    );
}

EansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
