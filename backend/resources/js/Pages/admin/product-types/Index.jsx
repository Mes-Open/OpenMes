import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { Button, ConfirmDialog, Icon, IconButton, StatusPill, Switch } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { realtimeCollection } from '../../../lib/realtimeCollection';
import Tooltip from '../../../components/Tooltip';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { PRODUCT_TYPE_FIELDS, productTypeInitial } from './fields';
import { __ } from '../../../lib/i18n';

/**
 * Product Types — card grid, restyled onto the Geist White design system
 * (@openmes/ui + om-* tokens). A faithful React port of the original
 * index.blade.php (lost to the generic ResourceTable in the React migration):
 * per-card stats, a "View Details" link into the rich Show page, a summary-stats
 * row, and the CSV Import buttons. Rows live-sync from the `product_types`
 * shape; cross-table counts come from the `counts` prop (keyed by id).
 */
export default function ProductTypesIndex() {
    // `customFields` is optional — only sent once the drawer asks for it.
    const { counts = {}, customFields, imageUrls } = usePage().props;

    // This list draws its own cards rather than using ResourceTable, so the
    // drawer is wired to the two controls by hand; the behaviour is the same.
    const drawer = useResourceDrawer();

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
        <div className="w-full px-4 py-5 sm:px-6 sm:py-6">
            <Head title={__('Product Types')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center mb-5">
                <h1 className="text-[28px] font-semibold tracking-[-0.025em] text-om-ink">{__('Product Types')}</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" onClick={() => router.visit('/admin/import/product-types')}>
                        <Icon name="upload" size={16} />
                        {__('Import')}
                    </Button>
                    <Tooltip label={__('Download example CSV file for product types import')}>
                        <a
                            href="/admin/import-example/product-types"
                            className="size-[38px] rounded-om-sm border border-om-line text-om-muted flex items-center justify-center hover:bg-om-chip hover:text-om-ink transition-colors"
                            aria-label={__('Download example CSV file for product types import')}
                        >
                            <Icon name="download" size={15} />
                        </a>
                    </Tooltip>
                    <Button variant="accent" onClick={drawer.create}>
                        <Icon name="plus" size={16} />
                        {__('Add Product Type')}
                    </Button>
                </div>
            </div>

            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                {[
                    { label: __('Total Product Types'), value: list.length, icon: 'package' },
                    { label: __('Active Types'), value: activeCount, icon: 'circle-check' },
                    { label: __('Total Templates'), value: totalTemplates, icon: 'workflow' },
                ].map(stat => (
                    <div key={stat.label} className="flex items-center justify-between gap-3 rounded-om border border-om-line bg-om-card px-5 py-4">
                        <div>
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{stat.label}</p>
                            <p className="mt-2 font-mono text-[26px] leading-none text-om-ink">{stat.value}</p>
                        </div>
                        <Icon name={stat.icon} size={20} className="shrink-0 text-om-muted" />
                    </div>
                ))}
            </div>

            {list.length > 0 ? (
                <>
                    {/* Product Types Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {list.map((pt) => {
                            const templates = templatesOf(pt.id);
                            const workOrders = workOrdersOf(pt.id);
                            const deletable = templates === 0 && workOrders === 0;

                            return (
                                <div key={pt.id} className="bg-om-card border border-om-line rounded-om p-5">
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2 mb-1">
                                                <h3 className="text-[15px] font-semibold text-om-ink break-words">{pt.name}</h3>
                                                {pt.is_active ? (
                                                    <StatusPill status="running" label={__('Active')} />
                                                ) : (
                                                    <StatusPill status="pending" label={__('Inactive')} />
                                                )}
                                            </div>
                                            <p className="font-mono text-[11px] text-om-faint">{pt.code}</p>
                                            {pt.unit_of_measure && (
                                                <p className="text-xs text-om-muted mt-1">{__('Unit:')} {pt.unit_of_measure}</p>
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
                                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Templates')}</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="font-mono text-[22px] text-om-ink">{workOrders}</p>
                                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Work Orders')}</p>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="pt-4 border-t border-om-line">
                                        <div className="flex items-center justify-between mb-3">
                                            <div className="flex items-center gap-2">
                                                <Tooltip label={pt.is_active ? __('Deactivate') : __('Activate')}>
                                                    <Switch
                                                        checked={!!pt.is_active}
                                                        onChange={() => toggleActive(pt)}
                                                        aria-label={pt.is_active ? __('Deactivate') : __('Activate')}
                                                    />
                                                </Tooltip>
                                                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                                                    {pt.is_active ? __('Active') : __('Inactive')}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Tooltip label={__('Edit')}>
                                                    <IconButton
                                                        onClick={() => drawer.edit(pt)}
                                                        aria-label={__('Edit')}
                                                    >
                                                        <Icon name="pencil" size={16} />
                                                    </IconButton>
                                                </Tooltip>
                                                {deletable ? (
                                                    <Tooltip label={__('Delete')}>
                                                        <IconButton
                                                            variant="danger"
                                                            onClick={() => setToDelete(pt)}
                                                            aria-label={__('Delete')}
                                                        >
                                                            <Icon name="trash-2" size={16} />
                                                        </IconButton>
                                                    </Tooltip>
                                                ) : (
                                                    <Tooltip label={__('Cannot delete - has templates or work orders')}>
                                                        <span className="inline-flex size-[38px] items-center justify-center text-om-faintest">
                                                            <Icon name="lock-keyhole" size={16} />
                                                        </span>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </div>
                                        <Button
                                            variant="secondary"
                                            className="w-full"
                                            onClick={() => router.visit(`/admin/product-types/${pt.id}`)}
                                        >
                                            {__('View Details')}
                                            <Icon name="arrow-right" size={14} />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                </>
            ) : (
                /* Empty State */
                <div className="bg-om-card border border-om-line rounded-om text-center py-12">
                    <Icon name="package" size={40} className="mx-auto text-om-faintest mb-4" />
                    <p className="text-[15px] font-semibold text-om-ink">{__('No product types yet')}</p>
                    <p className="text-sm text-om-muted mt-1 mb-4">{__('Get started by creating your first product type.')}</p>
                    <Button variant="accent" onClick={drawer.create}>
                        <Icon name="plus" size={16} />
                        {__('Create Product Type')}
                    </Button>
                </div>
            )}

            <ConfirmDialog
                open={toDelete !== null}
                onClose={() => setToDelete(null)}
                onConfirm={confirmDestroy}
                title={toDelete ? __('Are you sure you want to delete ":name"?', { name: toDelete.name }) : ''}
                confirmLabel={__('Delete')}
                cancelLabel={__('Cancel')}
                destructive
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/product-types"
                fields={PRODUCT_TYPE_FIELDS}
                initial={(record) => productTypeInitial(record, { imageUrls })}
                customFields={customFields}
                ensure={['customFields', 'imageUrls']}
                ready={customFields !== undefined}
                title={{ create: __('New Product Type'), edit: __('Edit Product Type') }}
            />
        </div>
    );
}

ProductTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
