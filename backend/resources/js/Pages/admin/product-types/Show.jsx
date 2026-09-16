import { Head, Link, router } from '@inertiajs/react';
import { Button, Icon } from '@openmes/ui';
import AppDataTable from '../../../components/AppDataTable';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
// Explicit extension: `components/engineeringDocuments.js` (the helper module)
// differs only in case, so an extensionless import resolves to the wrong file on a
// case-insensitive filesystem (macOS) and breaks the build.
import EngineeringDocuments from '../../../components/EngineeringDocuments.jsx';
import { __ } from '../../../lib/i18n';
import PageTrail from '../../../components/PageTrail';

const WO_STATUS_LABELS = {
    PENDING:     'Pending',
    ACCEPTED:    'Accepted',
    IN_PROGRESS: 'In Progress',
    BLOCKED:     'Blocked',
    PAUSED:      'Paused',
    CHANGE_HOLD: 'Change hold',
    DONE:        'Done',
    REJECTED:    'Rejected',
    CANCELLED:   'Cancelled',
};

const WO_STATUS_STYLES = {
    PENDING:     'bg-om-downtime-bg text-om-downtime',
    IN_PROGRESS: 'bg-om-chip text-om-accent',
    COMPLETED:   'bg-om-running-bg text-om-running',
    BLOCKED:     'bg-om-blocked-bg text-om-blocked',
    DONE:        'bg-om-running-bg text-om-running',
    REJECTED:    'bg-om-blocked-bg text-om-blocked',
    CANCELLED:   'bg-om-line2 text-om-muted',
    ACCEPTED:    'bg-om-chip text-om-accent',
    PAUSED:      'bg-om-downtime-bg text-om-downtime',
    CHANGE_HOLD: 'bg-om-downtime-bg text-om-downtime',
};

const SERIAL_STATUS_STYLES = {
    in_production: 'bg-om-chip text-om-accent',
    completed:    'bg-om-running-bg text-om-running',
    scrapped:     'bg-om-blocked-bg text-om-blocked',
    shipped:      'bg-om-line2 text-om-muted',
};

function trimQty(val) {
    if (val == null) return '0';
    return parseFloat(Number(val).toFixed(4)).toString();
}

function ucWords(str) {
    if (!str) return '—';
    return str.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const linkButton = 'inline-flex items-center justify-center gap-2 rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-[12px] font-medium text-om-ink transition-colors hover:bg-om-chip';

function SetupLink({ icon, href, children, detail }) {
    return <Link href={href} className="group flex min-w-0 items-center gap-3 rounded-om-sm px-3 py-2.5 hover:bg-om-chip transition-colors">
        <Icon name={icon} size={16} className="shrink-0 text-om-muted" />
        <span className="min-w-0 flex-1 text-[13px]">{children}</span>
        {detail != null && <span className="shrink-0 font-mono text-[11px] text-om-muted">{detail}</span>}
        <Icon name="chevron-right" size={14} className="shrink-0 text-om-faint" />
    </Link>;
}

export default function ProductTypeShow({
    productType,
    recentWorkOrders = [],
    componentsUsed = [],
    serials = { total: 0, status_counts: {}, recent: [] },
    customFields = [],
    setup = {},
}) {
    const templateCount = productType.process_templates?.length ?? 0;
    const workOrderCount = productType.work_order_count ?? recentWorkOrders.length;
    const totalWorkOrders = productType.total_work_order_count ?? workOrderCount;
    const serialStatusCounts = serials.status_counts ?? {};
    const template = productType.process_templates?.find(t => t.is_active);
    const templateUrl = template ? `/admin/product-types/${productType.id}/process-templates/${template.id}` : `/admin/product-types/${productType.id}/process-templates/create`;

    const handleToggleActive = () => {
        router.post(`/admin/product-types/${productType.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={__("Product Type Details")} />

            {/* Breadcrumbs */}
            <PageTrail append={[{ label: productType.name }]} />

            <div className="w-full px-4 py-5 sm:px-6 sm:py-6">
                <header className="mb-5 flex flex-col justify-between gap-4 xl:flex-row">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            {productType.image_url && <img src={productType.image_url} alt={productType.name} className="size-12 rounded-om-sm border border-om-line object-cover" />}
                            <h1 className="text-[26px] font-semibold tracking-tight text-om-ink break-words">{productType.name}</h1>
                            <span className={`rounded-full px-2.5 py-1 font-mono text-[10px] uppercase ${productType.is_active ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-muted'}`}>{__(productType.is_active ? 'Active' : 'Inactive')}</span>
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-om-muted">
                            <span className="font-mono">{productType.code}</span>
                            {productType.unit_of_measure && <span>{__('Unit')}: {productType.unit_of_measure}</span>}
                            <span className="inline-flex items-center gap-1.5"><Icon name="workflow" size={14} />{__('Process Templates')}: {templateCount}</span>
                            <span className="inline-flex items-center gap-1.5"><Icon name="clipboard-list" size={14} />{__('Work Orders')}: {totalWorkOrders}</span>
                        </div>
                        {productType.description && <p className="mt-2 text-sm text-om-muted">{productType.description}</p>}
                    </div>
                    <div className="flex flex-wrap items-start gap-2">
                        <Link href="/admin/product-types" className={linkButton}><Icon name="arrow-left" size={14} />{__('Back')}</Link>
                        <Link href={`/admin/product-types/${productType.id}/edit`} className={linkButton}><Icon name="pencil" size={14} />{__('Edit Product Type')}</Link>
                        <Button size="sm" variant="outline" onClick={handleToggleActive} leftIcon={<Icon name={productType.is_active ? 'circle-slash' : 'circle-check'} size={14} />}>{__(productType.is_active ? 'Deactivate' : 'Activate')}</Button>
                    </div>
                </header>

                <details open className="group/setup mb-4 rounded-om border border-om-line bg-om-card">
                    <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-4 [&::-webkit-details-marker]:hidden">
                        <Icon name="list-checks" size={17} className="text-om-muted" />
                        <h2 className="flex-1 text-[15px] font-semibold">{__('Prepare this product for production')}</h2>
                        <Icon name="chevron-down" size={16} className="text-om-muted transition-transform group-open/setup:rotate-180" />
                    </summary>
                    <div className="border-t border-om-line px-2 py-2 sm:px-3">
                        <p className="px-3 py-2 text-xs text-om-muted">{__('Follow these links to configure the product. A BOM is optional; assign stations only when using station routing.')}</p>
                        <div className="grid grid-cols-1 lg:grid-cols-2">
                            <SetupLink icon="clock" href="/settings/system?tab=general" detail={setup.timezone}>{__('Check plant timezone')}</SetupLink>
                            <SetupLink icon="workflow" href={templateUrl} detail={template?.steps?.length ?? 0}>{__('Define the process steps')}</SetupLink>
                            <SetupLink icon="layers" href={template ? `${templateUrl}/bom` : templateUrl} detail={template?.bom_count ?? 0}>{__('Configure the BOM')}</SetupLink>
                            <SetupLink icon="package-plus" href="/admin/materials">{__('Receive the materials required by the BOM')}</SetupLink>
                            <SetupLink icon="factory" href="/admin/lines">{__('Create the line and its workstations')}</SetupLink>
                            <SetupLink icon="monitor" href={templateUrl} detail={`${template?.steps?.filter(s => s.workstation_id).length ?? 0}/${template?.steps?.length ?? 0}`}>{__('Assign a workstation to each process step')}</SetupLink>
                            <SetupLink icon="users" href="/admin/users">{__('Configure operator accounts and assignments')}</SetupLink>
                            <SetupLink icon="clipboard-plus" href="/admin/work-orders">{__('Create a work order')}</SetupLink>
                            <SetupLink icon="calendar-days" href="/admin/schedule">{__('Plan the production start')}</SetupLink>
                        </div>
                        {setup.lines?.length > 0 && <div className="mt-2 flex flex-wrap items-center gap-2 border-t border-om-line px-3 pt-3 pb-2 text-xs text-om-muted">
                            {__('Lines')}:
                            {setup.lines.map(line => <Link key={line.id} className={linkButton} href={`/admin/lines/${line.id}`}><Icon name="factory" size={13} />{line.name}</Link>)}
                        </div>}
                    </div>
                </details>
                <div className="mb-6">
                    <CustomFieldsDisplay definitions={customFields} values={productType.custom_fields ?? {}} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {/* Process Templates */}
                    <div className="min-w-0 rounded-om border border-om-line bg-om-card p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <h2 className="text-[15px] font-semibold text-om-ink">{__("Process Templates")}</h2>
                            <div className="flex gap-2">
                                <Link
                                    href={`/admin/product-types/${productType.id}/process-templates`}
                                    className={linkButton}
                                >
                                    {__("View All")}
                                </Link>
                                <Link
                                    href={`/admin/product-types/${productType.id}/process-templates/create`}
                                    className="inline-flex items-center gap-2 rounded-om-sm bg-om-ink px-3 py-2 text-[12px] font-semibold text-om-on-ink hover:bg-om-ink-hover"
                                >
                                    <Icon name="plus" size={16} className="shrink-0" />
                                    {__("Create")}
                                </Link>
                            </div>
                        </div>

                        {productType.process_templates && productType.process_templates.length > 0 ? (
                            <div className="space-y-2">
                                {productType.process_templates.map((template) => (
                                    <Link
                                        key={template.id}
                                        href={`/admin/product-types/${productType.id}/process-templates/${template.id}`}
                                        className="block p-3 bg-om-panel rounded-om-sm hover:bg-om-chip transition-colors"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <p className="font-medium text-om-ink">{template.name}</p>
                                                    {template.is_active ? (
                                                        <span className="px-2 py-1 bg-om-running-bg text-om-running rounded-full text-xs font-medium">{__("Active")}</span>
                                                    ) : (
                                                        <span className="px-2 py-1 bg-om-chip text-om-muted rounded-full text-xs font-medium">{__("Inactive")}</span>
                                                    )}
                                                </div>
                                                <p className="text-xs text-om-muted">
                                                    {__("Version")} {template.version} &bull; {__(":count steps", { count: template.steps?.length ?? 0 })}
                                                </p>
                                            </div>
                                            <Icon name="chevron-right" size={16} className="shrink-0 text-om-faint" />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 bg-om-panel rounded-om-sm">
                                <Icon name="file-text" size={24} className="mx-auto mb-3 text-om-faint" />
                                <p className="text-om-muted mb-2">{__("No process templates yet")}</p>
                                <p className="text-sm text-om-muted">{__("Process templates define how this product is manufactured.")}</p>
                            </div>
                        )}
                    </div>

                    {/* Recent Work Orders */}
                    <div className="min-w-0 rounded-om border border-om-line bg-om-card p-5">
                        <h2 className="text-[15px] font-semibold text-om-ink mb-4">{__("Recent Work Orders")}</h2>
                        {recentWorkOrders.length > 0 ? (
                            <>
                                <div className="space-y-2">
                                    {recentWorkOrders.map((wo) => (
                                        <div key={wo.id} className="p-3 bg-om-panel rounded-om-sm">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-om-ink">{wo.work_order_number}</p>
                                                    <p className="text-sm text-om-muted">{wo.product_name}</p>
                                                    <p className="text-xs text-om-muted mt-1">
                                                        {__("Quantity:")} {wo.planned_qty} | {wo.created_at ? wo.created_at.substring(0, 16).replace('T', ' ') : '—'}
                                                    </p>
                                                </div>
                                                <span className={`px-2 py-1 text-xs font-medium rounded-full ${WO_STATUS_STYLES[wo.status] ?? 'bg-om-chip text-om-ink'}`}>
                                                    {__(WO_STATUS_LABELS[wo.status] ?? wo.status)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                {totalWorkOrders > 10 && (
                                    <p className="text-sm text-om-muted text-center mt-4">
                                        {__("Showing 10 most recent of :total total work orders", { total: totalWorkOrders })}
                                    </p>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-8 bg-om-panel rounded-om-sm">
                                <Icon name="clipboard-list" size={24} className="mx-auto mb-3 text-om-faint" />
                                <p className="text-om-muted">{__("No work orders yet")}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Components & serials used */}
                <div className="mt-6">
                    <h2 className="text-[15px] font-semibold text-om-ink mb-1">{__('Components & serials used')}</h2>
                    <p className="text-sm text-om-muted mb-4">
                        {__("Materials actually consumed and serialized units produced across this product's work orders.")}
                    </p>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {/* Components consumed */}
                        <div className="min-w-0 rounded-om border border-om-line bg-om-card p-5">
                            <h3 className="text-sm font-semibold text-om-muted uppercase tracking-wide mb-4">
                                {__('Components consumed')} ({componentsUsed.length})
                            </h3>
                            {componentsUsed.length > 0 ? (
                                <AppDataTable
                                    data={componentsUsed}
                                    searchable={false}
                                    columnToggle={false}
                                    paginated={false}
                                    bodyMaxHeight="none"
                                    columns={[
                                        { accessorKey: 'name', header: __('Material'), cell: ({ row }) => <Link href={`/admin/materials/${row.original.id}`} className="font-medium hover:underline">{row.original.name}<span className="block text-xs font-mono text-om-muted">{row.original.code}</span></Link> },
                                        { accessorKey: 'total_consumed', header: __('Consumed'), meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{trimQty(row.original.total_consumed)} {row.original.unit_of_measure}</span> },
                                        { accessorKey: 'lot_count', header: __('Lots'), meta: { align: 'right' } },
                                    ]}
                                />
                            ) : (
                                <div className="text-center py-8 bg-om-panel rounded-om-sm">
                                    <p className="text-om-muted">{__('No material consumption recorded yet')}</p>
                                    <p className="text-sm text-om-muted mt-1">
                                        {__("Components appear here once lots are consumed against this product's batches.")}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Serialized units */}
                        <div className="min-w-0 rounded-om border border-om-line bg-om-card p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                                <h3 className="text-sm font-semibold text-om-muted uppercase tracking-wide">
                                    {__('Serialized units')} ({serials.total ?? 0})
                                </h3>
                                {Object.keys(serialStatusCounts).length > 0 && (
                                    <div className="flex flex-wrap gap-1 justify-end">
                                        {Object.entries(serialStatusCounts).map(([status, count]) => (
                                            <span
                                                key={status}
                                                className={`px-2 py-0.5 rounded-full text-xs font-medium ${SERIAL_STATUS_STYLES[status] ?? 'bg-om-chip text-om-muted'}`}
                                            >
                                                {__(ucWords(status))}: {count}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {serials.recent && serials.recent.length > 0 ? (
                                <>
                                    <div className="space-y-2">
                                        {serials.recent.map((s) => (
                                            <Link
                                                key={s.id}
                                                href={`/admin/traceability?q=${encodeURIComponent(s.serial_no)}`}
                                                className="block p-3 bg-om-panel rounded-om-sm hover:bg-om-chip transition-colors"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex-1 min-w-0">
                                                        <p className="font-mono font-medium text-om-ink truncate">{s.serial_no}</p>
                                                        <p className="text-xs text-om-muted mt-0.5">
                                                            {s.work_order ?? '—'}
                                                            {s.batch && <> &bull; {s.batch}</>}
                                                        </p>
                                                    </div>
                                                    <span className={`px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap ${SERIAL_STATUS_STYLES[s.status] ?? 'bg-om-chip text-om-muted'}`}>
                                                        {__(ucWords(s.status))}
                                                    </span>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                    {(serials.total ?? 0) > serials.recent.length && (
                                        <p className="text-sm text-om-muted text-center mt-4">
                                            {__('Showing :count most recent of :total units', { count: serials.recent.length, total: serials.total })}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-8 bg-om-panel rounded-om-sm">
                                    <p className="text-om-muted">{__('No serialized units yet')}</p>
                                    <p className="text-sm text-om-muted mt-1">
                                        {__("Units registered against this product's work orders will be listed here.")}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <EngineeringDocuments entityType="product_type" entityId={productType.id} />
            </div>
        </>
    );
}

ProductTypeShow.layout = (page) => <AppLayout>{page}</AppLayout>;
