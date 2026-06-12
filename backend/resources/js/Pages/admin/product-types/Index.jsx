import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { Button, ConfirmDialog, IconButton, StatusPill, Switch } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { realtimeCollection } from '../../../lib/realtimeCollection';

/**
 * Product Types — card grid, restyled onto the Geist White design system
 * (@openmes/ui + om-* tokens). A faithful React port of the original
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

    const [toDelete, setToDelete] = useState(null);

    const templatesOf = (id) => counts[id]?.process_templates ?? 0;
    const workOrdersOf = (id) => counts[id]?.work_orders ?? 0;

    const activeCount = list.filter((p) => p.is_active).length;
    const totalTemplates = list.reduce((sum, p) => sum + templatesOf(p.id), 0);

    const toggleActive = (pt) =>
        router.post(`/admin/product-types/${pt.id}/toggle-active`, {}, { preserveScroll: true });

    const confirmDestroy = () => {
        if (toDelete) {
            router.delete(`/admin/product-types/${toDelete.id}`, { preserveScroll: true });
        }
        setToDelete(null);
    };

    return (
        <div className="max-w-7xl mx-auto">
            <Head title="Product Types" />

            <div className="flex justify-between items-center mb-6">
                <h1 className="text-[28px] font-semibold tracking-[-0.025em] text-om-ink">Product Types</h1>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={() => router.visit('/admin/csv-import')}>
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import
                    </Button>
                    <a
                        href="/admin/import-example/product-types"
                        className="w-6 h-6 rounded-full bg-om-chip text-om-faint flex items-center justify-center font-mono text-[11px] font-bold hover:bg-om-line2 hover:text-om-ink transition-colors"
                        title="Download example CSV file for product types import"
                    >
                        ?
                    </a>
                    <Button variant="accent" onClick={() => router.visit('/admin/product-types/create')}>
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Add Product Type
                    </Button>
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
                                <div key={pt.id} className="bg-om-card border border-om-line rounded-om p-5">
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h3 className="text-[15px] font-semibold text-om-ink">{pt.name}</h3>
                                                {pt.is_active ? (
                                                    <StatusPill status="running" label="Active" />
                                                ) : (
                                                    <StatusPill status="pending" label="Inactive" />
                                                )}
                                            </div>
                                            <p className="font-mono text-[11px] text-om-faint">{pt.code}</p>
                                            {pt.unit_of_measure && (
                                                <p className="text-xs text-om-muted mt-1">Unit: {pt.unit_of_measure}</p>
                                            )}
                                        </div>
                                    </div>

                                    {pt.description && (
                                        <p className="text-sm text-om-muted mb-4 line-clamp-2">{pt.description}</p>
                                    )}

                                    {/* Stats */}
                                    <div className="grid grid-cols-2 gap-3 mb-4 p-3 bg-om-bg rounded-om-sm">
                                        <div className="text-center">
                                            <p className="font-mono text-[22px] text-om-ink">{templates}</p>
                                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Templates</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="font-mono text-[22px] text-om-ink">{workOrders}</p>
                                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Work Orders</p>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="pt-4 border-t border-om-line">
                                        <div className="flex items-center justify-between mb-3">
                                            <div className="flex items-center gap-2">
                                                <Switch
                                                    checked={!!pt.is_active}
                                                    onChange={() => toggleActive(pt)}
                                                    title={pt.is_active ? 'Deactivate' : 'Activate'}
                                                    aria-label={pt.is_active ? 'Deactivate' : 'Activate'}
                                                />
                                                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                                                    {pt.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <IconButton
                                                    onClick={() => router.visit(`/admin/product-types/${pt.id}/edit`)}
                                                    title="Edit"
                                                    aria-label="Edit"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </IconButton>
                                                {deletable ? (
                                                    <IconButton
                                                        variant="danger"
                                                        onClick={() => setToDelete(pt)}
                                                        title="Delete"
                                                        aria-label="Delete"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </IconButton>
                                                ) : (
                                                    <span
                                                        className="inline-flex size-[38px] items-center justify-center text-om-faintest"
                                                        title="Cannot delete - has templates or work orders"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <Button
                                            variant="secondary"
                                            className="w-full"
                                            onClick={() => router.visit(`/admin/product-types/${pt.id}`)}
                                        >
                                            View Details
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Summary Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Total Product Types</p>
                            <p className="mt-1 font-mono text-[28px] text-om-ink">{list.length}</p>
                        </div>

                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Active Types</p>
                            <p className="mt-1 font-mono text-[28px] text-om-accent">{activeCount}</p>
                        </div>

                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Total Templates</p>
                            <p className="mt-1 font-mono text-[28px] text-om-ink">{totalTemplates}</p>
                        </div>
                    </div>
                </>
            ) : (
                /* Empty State */
                <div className="bg-om-card border border-om-line rounded-om text-center py-12">
                    <svg className="mx-auto h-16 w-16 text-om-faintest mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p className="text-[15px] font-semibold text-om-ink">No product types yet</p>
                    <p className="text-sm text-om-muted mt-1 mb-4">Get started by creating your first product type.</p>
                    <Button variant="accent" onClick={() => router.visit('/admin/product-types/create')}>
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Create Product Type
                    </Button>
                </div>
            )}

            <ConfirmDialog
                open={toDelete !== null}
                onClose={() => setToDelete(null)}
                onConfirm={confirmDestroy}
                title={toDelete ? `Are you sure you want to delete "${toDelete.name}"?` : ''}
                confirmLabel="Delete"
                cancelLabel="Cancel"
                destructive
            />
        </div>
    );
}

ProductTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
