import { useState, useRef } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const STATUS_STYLES = {
    DONE: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    IN_PROGRESS: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    PENDING: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

function statusStyle(status) {
    return STATUS_STYLES[status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
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
                <nav className="flex items-center gap-1 text-sm text-gray-500 mb-4">
                    <a href="/admin/dashboard" className="hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
                    <span className="mx-1">/</span>
                    <a href="/packaging" className="hover:text-gray-700 dark:hover:text-gray-300">Pakowanie</a>
                    <span className="mx-1">/</span>
                    <span className="text-gray-700 dark:text-gray-300">Kody EAN</span>
                </nav>

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-800 dark:text-white">Kody EAN &mdash; Zarządzanie</h1>
                        <p className="text-sm text-gray-500 mt-1">Przypisuj kody kreskowe do zleceń produkcyjnych</p>
                    </div>
                    <a href="/packaging" className="btn-touch btn-secondary">&larr; Przegląd pakowania</a>
                </div>

                {/* Add EAN form */}
                <div className="card mb-6">
                    <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Dodaj kod EAN</h2>
                    <form onSubmit={handleAddSubmit} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="form-label">Zlecenie produkcyjne</label>
                            <select
                                name="work_order_id"
                                value={form.data.work_order_id}
                                onChange={(e) => form.setData('work_order_id', e.target.value)}
                                className="form-input w-full"
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
                                <p className="text-xs text-red-600 mt-1">{form.errors.work_order_id}</p>
                            )}
                        </div>
                        <div>
                            <label className="form-label">Kod EAN</label>
                            <input
                                type="text"
                                value={form.data.ean}
                                onChange={(e) => form.setData('ean', e.target.value)}
                                className="form-input w-full font-mono"
                                placeholder="np. 5901234123457"
                                required
                                maxLength={100}
                            />
                            {form.errors.ean && (
                                <p className="text-xs text-red-600 mt-1">{form.errors.ean}</p>
                            )}
                        </div>
                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-touch btn-primary w-full sm:w-auto disabled:opacity-50"
                            >
                                {form.processing ? 'Dodawanie…' : 'Dodaj EAN'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="card mb-4 py-3">
                    <div className="flex gap-3">
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            className="form-input flex-1"
                            placeholder="Szukaj po numerze zlecenia…"
                        />
                        <button type="submit" className="btn-touch btn-secondary text-sm">Szukaj</button>
                        {hasSearch && (
                            <button type="button" onClick={handleClear} className="btn-touch btn-secondary text-sm">
                                Wyczyść
                            </button>
                        )}
                    </div>
                </form>

                {/* Table */}
                <div className="card overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Zlecenie</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Kody EAN</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Spakowano / Plan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10 text-center text-gray-400 text-sm">Brak wyników</td>
                                    </tr>
                                ) : rows.map((wo) => (
                                    <tr key={wo.id}>
                                        <td className="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{wo.order_no}</td>
                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{wo.product_type?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusStyle(wo.status)}`}>
                                                {(wo.status ?? '').replace(/_/g, ' ')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {(wo.eans ?? []).length === 0 ? (
                                                <span className="text-xs text-gray-400">Brak EAN</span>
                                            ) : (wo.eans ?? []).map((ean) => (
                                                <div key={ean.id} className="flex items-center gap-2 mb-1">
                                                    <span className="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">
                                                        {ean.ean}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm(`Usunąć kod EAN ${ean.ean}?`)) {
                                                                router.delete(`/packaging/eans/${ean.id}`, { preserveScroll: true });
                                                            }
                                                        }}
                                                        className="text-xs text-red-500 hover:text-red-700 transition-colors"
                                                    >
                                                        Usuń
                                                    </button>
                                                </div>
                                            ))}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium text-gray-700 dark:text-gray-300">
                                            <span className="font-bold">{wo.packed_qty ?? 0}</span>
                                            <span className="text-gray-400"> / {parseInt(wo.planned_qty ?? 0, 10)}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination links */}
                    {pagination.last_page > 1 && (
                        <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center gap-2 flex-wrap text-sm">
                            {(pagination.links ?? []).map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: false })}
                                    className={`px-3 py-1 rounded border text-sm transition-colors ${
                                        link.active
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : link.url
                                            ? 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

EansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
