import { useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import AppLayout from '../../../layouts/AppLayout';
import { realtimeCollection } from '../../../lib/realtimeCollection';

/**
 * Product Types — card grid. A faithful React port of the original
 * index.blade.php (lost to the generic ResourceTable in the React migration):
 * per-card stats, a "View Details" link into the rich Show page, a summary-stats
 * row, and the CSV Import buttons. Rows live-sync from the `product_types`
 * shape; cross-table counts come from the `counts` prop (keyed by id).
 */
export default function ProductTypesIndex() {
    const { counts = {} } = usePage().props;

    const collection = useMemo(() => realtimeCollection('product_types'), []);
    const { data: rows } = useLiveQuery((q) =>
        q.from({ r: collection }).orderBy(({ r }) => r.name, 'asc'),
    );
    const list = rows ?? [];

    const templatesOf = (id) => counts[id]?.process_templates ?? 0;
    const workOrdersOf = (id) => counts[id]?.work_orders ?? 0;

    const activeCount = list.filter((p) => p.is_active).length;
    const totalTemplates = list.reduce((sum, p) => sum + templatesOf(p.id), 0);

    const toggleActive = (pt) =>
        router.post(`/admin/product-types/${pt.id}/toggle-active`, {}, { preserveScroll: true });

    const destroy = (pt) => {
        if (confirm(`Are you sure you want to delete "${pt.name}"?`)) {
            router.delete(`/admin/product-types/${pt.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Product Types" />

            <div className="flex justify-between items-center mb-6">
                <h1 className="text-3xl font-bold text-gray-800">Product Types</h1>
                <div className="flex items-center gap-2">
                    <Link
                        href="/admin/csv-import"
                        className="btn-touch bg-gray-100 text-gray-700 hover:bg-gray-200 flex items-center gap-1.5"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import
                    </Link>
                    <a
                        href="/admin/import-example/product-types"
                        className="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs font-bold hover:bg-blue-100 hover:text-blue-600 transition"
                        title="Download example CSV file for product types import"
                    >
                        ?
                    </a>
                    <Link href="/admin/product-types/create" className="btn-touch btn-primary">
                        <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Add Product Type
                    </Link>
                </div>
            </div>

            {list.length > 0 ? (
                <>
                    {/* Product Types Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {list.map((pt) => {
                            const templates = templatesOf(pt.id);
                            const workOrders = workOrdersOf(pt.id);
                            const deletable = templates === 0 && workOrders === 0;

                            return (
                                <div key={pt.id} className="card hover:shadow-lg transition-shadow">
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="text-lg font-bold text-gray-800">{pt.name}</h3>
                                                {pt.is_active ? (
                                                    <span className="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                                ) : (
                                                    <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                                )}
                                            </div>
                                            <p className="text-sm text-gray-500 font-mono">{pt.code}</p>
                                            {pt.unit_of_measure && (
                                                <p className="text-xs text-gray-600 mt-1">Unit: {pt.unit_of_measure}</p>
                                            )}
                                        </div>
                                    </div>

                                    {pt.description && (
                                        <p className="text-sm text-gray-600 mb-4 line-clamp-2">{pt.description}</p>
                                    )}

                                    {/* Stats */}
                                    <div className="grid grid-cols-2 gap-3 mb-4 p-3 bg-gray-50 rounded-lg">
                                        <div className="text-center">
                                            <p className="text-2xl font-bold text-blue-600">{templates}</p>
                                            <p className="text-xs text-gray-600">Templates</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-2xl font-bold text-purple-600">{workOrders}</p>
                                            <p className="text-xs text-gray-600">Work Orders</p>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex gap-2 pt-4 border-t border-gray-200">
                                        <Link
                                            href={`/admin/product-types/${pt.id}`}
                                            className="flex-1 btn-touch btn-secondary text-center text-sm"
                                        >
                                            View Details
                                        </Link>
                                        <Link
                                            href={`/admin/product-types/${pt.id}/edit`}
                                            className="text-blue-600 hover:text-blue-800 p-2"
                                            title="Edit"
                                            aria-label="Edit"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => toggleActive(pt)}
                                            className="text-gray-600 hover:text-gray-800 p-2"
                                            title={pt.is_active ? 'Deactivate' : 'Activate'}
                                            aria-label={pt.is_active ? 'Deactivate' : 'Activate'}
                                        >
                                            {pt.is_active ? (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            ) : (
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            )}
                                        </button>
                                        {deletable ? (
                                            <button
                                                type="button"
                                                onClick={() => destroy(pt)}
                                                className="text-red-600 hover:text-red-800 p-2"
                                                title="Delete"
                                                aria-label="Delete"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        ) : (
                                            <span className="text-gray-300 p-2" title="Cannot delete - has templates or work orders">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Summary Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <div className="card">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Product Types</p>
                                    <p className="text-2xl font-bold text-gray-900">{list.length}</p>
                                </div>
                                <div className="bg-blue-100 rounded-full p-3">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Active Types</p>
                                    <p className="text-2xl font-bold text-green-600">{activeCount}</p>
                                </div>
                                <div className="bg-green-100 rounded-full p-3">
                                    <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div className="card">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Templates</p>
                                    <p className="text-2xl font-bold text-gray-900">{totalTemplates}</p>
                                </div>
                                <div className="bg-purple-100 rounded-full p-3">
                                    <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </>
            ) : (
                /* Empty State */
                <div className="card text-center py-12">
                    <svg className="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p className="text-lg font-medium text-gray-700">No product types yet</p>
                    <p className="text-sm text-gray-500 mt-1 mb-4">Get started by creating your first product type.</p>
                    <Link href="/admin/product-types/create" className="inline-block btn-touch btn-primary">
                        <svg className="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Create Product Type
                    </Link>
                </div>
            )}
        </div>
    );
}

ProductTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
