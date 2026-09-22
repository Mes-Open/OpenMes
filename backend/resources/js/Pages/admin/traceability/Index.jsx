import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Breadcrumbs, Button, Icon, Tabs, TextField } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import PageTitle from '../../../components/PageTitle';
import ResourceTable from '../../../components/ResourceTable';
import { woColumns } from '../work-orders/columns';
import { STATUS_STYLES as LOT_STATUS_STYLES, materialLotStatusLabel } from '../material-lots/fields';
import { __, formatNumber } from '../../../lib/i18n';

/**
 * Traceability / genealogy console.
 *
 * With nothing traced it is a place to find the thing: work orders, material
 * lots and pallets as searchable tables whose rows open their trace, so nobody
 * has to copy a number out of another screen first. The identifier box stays for
 * what no table lists - a scanned label, a serial number, a supplier LOT, a
 * customer order - and resolves server-side.
 *
 * Props: { term, result, lineNames, productTypeNames, customerNames,
 * materialNames, workOrderNumbers } - result.type is 'work_order' |
 * 'customer_order' | 'pallet' | 'batch' | 'material_lot' | 'serial'.
 */
const RESULT_TAB = {
    work_order: 'orders',
    customer_order: 'orders',
    batch: 'lots',
    material_lot: 'lots',
    serial: 'orders',
    pallet: 'pallets',
};

export default function TraceabilityIndex() {
    const { term = '', result = null } = usePage().props;
    const [q, setQ] = useState(term);
    const [tab, setTab] = useState(() => {
        const param = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
        return ['orders', 'lots', 'pallets'].includes(param) ? param : 'orders';
    });

    const submit = (e) => {
        e.preventDefault();
        if (q.trim() === '') return;
        router.get('/admin/traceability', { q: q.trim() });
    };

    const tracing = term !== '';

    return (
        <>
            <Head title={__('Traceability')} />

            <div className={`px-6 pt-6 space-y-4 ${tracing ? 'pb-6 max-w-5xl mx-auto' : ''}`}>
                <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
                    {tracing && (
                        <Link
                            href={`/admin/traceability?tab=${RESULT_TAB[result?.type] ?? 'orders'}`}
                            className="inline-flex items-center gap-1.5 text-sm text-om-muted hover:text-om-ink"
                        >
                            <Icon name="arrow-left" size={16} />
                            {__('Back to the list')}
                        </Link>
                    )}
                    <TextField
                        mono
                        value={q}
                        onChange={setQ}
                        autoFocus
                        aria-label={__('Identifier to trace')}
                        placeholder={__('Exact number: order, LOT, pallet, serial, supplier LOT, container…')}
                        className="min-w-64 flex-1"
                    />
                    <Button type="submit" variant="primary" className="whitespace-nowrap">
                        {__('Trace')}
                    </Button>
                </form>

                {!tracing && (
                    <Tabs
                        label={__('What to trace')}
                        value={tab}
                        onChange={setTab}
                        tabs={[
                            { value: 'orders', label: __('Work Orders') },
                            { value: 'lots', label: __('Material Lots') },
                            { value: 'pallets', label: __('Pallets') },
                        ]}
                    />
                )}
            </div>

            {!tracing && <BrowseTable tab={tab} />}

            {tracing && (
                <div className="px-6 pb-6 max-w-5xl mx-auto space-y-6">
                    {/* The browse tables put this trail in the header; a result has no table to do it. */}
                    <PageTitle>
                        <Breadcrumbs linkAs={Link} items={[{ label: __('Traceability'), href: '/admin/traceability' }, { label: term }]} />
                    </PageTitle>

                    {!result && (
                        <div className="bg-om-card rounded-om border border-om-line2 p-12 text-center">
                            <Icon name="search" size={40} className="mx-auto text-om-faintest" />
                            <p className="mt-3 text-om-muted">
                                {__('Nothing traceable matches')} <strong>{term}</strong>.
                            </p>
                        </div>
                    )}

                    {result?.type === 'pallet' && <PalletResult data={result.data} />}
                    {result?.type === 'work_order' && <CustomerOrderResult data={result.data} />}
                    {result?.type === 'customer_order' && <CustomerOrderResult data={result.data} />}
                    {result?.type === 'batch' && <BatchResult data={result.data} />}
                    {result?.type === 'material_lot' && <MaterialLotResult forward={result.forward} backward={result.backward} recall={result.recall} />}
                    {result?.type === 'serial' && <SerialResult unit={result.data} recall={result.recall} components={result.components} />}
                </div>
            )}
        </>
    );
}

/* ── Browse: find the thing to trace ─────────────────────────────────── */

const WO_BROWSE_COLUMNS = ['order_no', 'customer', 'line', 'product', 'qty', 'status', 'due_date', 'completed_at', 'customer_order_no', 'created_at'];

const PALLET_STATUS_STYLES = {
    open: 'bg-om-running-bg text-om-running',
    closed: 'bg-om-chip text-om-accent',
    shipped: 'bg-om-chip text-om-muted',
};

/**
 * One table per kind of traceable thing. Each is the same synced collection its
 * own admin list shows, so it is live and needs no endpoint of its own; the row's
 * identifier and its Trace action both open the trace.
 */
function BrowseTable({ tab }) {
    const {
        lineNames = {}, productTypeNames = {}, customerNames = {}, materialNames = {}, workOrderNumbers = {},
    } = usePage().props;

    const traceAction = (identifier) => (r) => [{ label: 'Trace', icon: 'open', href: traceLink(identifier(r)) }];

    if (tab === 'lots') {
        return (
            <ResourceTable
                key="lots"
                shape="material_lots"
                title={__('Traceability')}
                detailHref={(r) => traceLink(r.lot_number)}
                columns={[
                    { key: 'lot_number', label: __('Lot Number'), className: 'font-mono', filter: 'text', link: true },
                    { key: 'material', label: __('Material'), className: 'text-om-muted', value: (r) => materialNames[r.material_id] ?? '', render: (r) => materialNames[r.material_id] ?? '—' },
                    { key: 'supplier_lot_no', label: __('Supplier LOT'), className: 'font-mono text-om-muted', render: (r) => r.supplier_lot_no || '—' },
                    { key: 'source_container_no', label: __('Source container'), className: 'font-mono text-om-muted', render: (r) => r.source_container_no || '—' },
                    { key: 'received_at', label: __('Received'), filter: 'date', className: 'text-om-muted', render: (r) => (r.received_at ? String(r.received_at).slice(0, 10) : '—') },
                    {
                        key: 'status',
                        label: __('Status'),
                        value: (r) => r.status,
                        render: (r) => (
                            <span className={`text-xs px-2 py-0.5 rounded font-medium ${LOT_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>
                                {materialLotStatusLabel(r.status)}
                            </span>
                        ),
                    },
                ]}
                orderBy="received_at"
                orderDir="desc"
                actions={traceAction((r) => r.lot_number)}
                enableSelection={false}
                emptyText="No material lots yet."
            />
        );
    }

    if (tab === 'pallets') {
        return (
            <ResourceTable
                key="pallets"
                shape="pallets"
                title={__('Traceability')}
                detailHref={(r) => traceLink(r.pallet_no)}
                columns={[
                    { key: 'pallet_no', label: __('Pallet number'), className: 'font-mono', filter: 'text', link: true },
                    { key: 'work_order', label: __('Work Order'), className: 'font-mono text-om-muted', value: (r) => workOrderNumbers[r.work_order_id] ?? '', render: (r) => workOrderNumbers[r.work_order_id] ?? '—' },
                    { key: 'qty', label: __('Quantity') },
                    {
                        key: 'status',
                        label: __('Status'),
                        value: (r) => r.status,
                        render: (r) => (
                            <span className={`text-xs px-2 py-0.5 rounded font-medium ${PALLET_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{r.status}</span>
                        ),
                    },
                    { key: 'destination', label: __('Destination'), className: 'text-om-muted', render: (r) => r.destination || '—' },
                    { key: 'created_at', label: __('Created'), filter: 'date', className: 'text-om-muted', render: (r) => (r.created_at ? String(r.created_at).slice(0, 10) : '—') },
                ]}
                orderBy="pallet_no"
                orderDir="desc"
                actions={traceAction((r) => r.pallet_no)}
                enableSelection={false}
                emptyText="No pallets yet."
            />
        );
    }

    // The work orders list's own column definitions, narrowed to what helps pick
    // an order out - so status pills, quantities and dates read the same here.
    const columns = woColumns({ lineNames, productTypeNames, customerNames, detailHref: (r) => traceLink(r.order_no) })
        .filter((c) => WO_BROWSE_COLUMNS.includes(c.key));

    return (
        <ResourceTable
            key="orders"
            shape="work_orders_all"
            title={__('Traceability')}
            detailHref={(r) => traceLink(r.order_no)}
            columns={columns}
            orderBy="created_at"
            orderDir="desc"
            actions={traceAction((r) => r.order_no)}
            enableSelection={false}
            emptyText="No work orders yet."
        />
    );
}

TraceabilityIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── helpers ─────────────────────────────────────────────────────────── */

function Card({ children, className = '' }) {
    return (
        <div className={`bg-om-card rounded-om border border-om-line2 p-5 ${className}`}>
            {children}
        </div>
    );
}

function traceLink(lotNumber) {
    return `/admin/traceability?q=${encodeURIComponent(lotNumber)}`;
}

/* ── Finished batch (backward genealogy) ─────────────────────────────── */

const INGREDIENT_LOT_COLUMNS = [
    {
        id: 'material',
        accessorFn: (r) => r.material ?? '',
        header: __('Material'),
        cell: ({ row }) => (
            <span className="text-om-ink">
                {row.original.material ?? '—'} <span className="text-xs text-om-faint font-mono">{row.original.material_code}</span>
            </span>
        ),
    },
    {
        id: 'lot_number',
        accessorKey: 'lot_number',
        header: __('LOT'),
        cell: ({ row }) => (
            <span className="font-mono">
                <Link href={traceLink(row.original.lot_number)} className="text-om-accent hover:underline">{row.original.lot_number}</Link>
            </span>
        ),
    },
    {
        id: 'supplier_lot_no',
        accessorFn: (r) => r.supplier_lot_no ?? '',
        header: __('Supplier LOT'),
        cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.supplier_lot_no ?? '—'}</span>,
    },
    {
        id: 'source_container_no',
        accessorFn: (r) => r.source_container_no ?? '',
        header: __('Source container'),
        cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.source_container_no ?? '—'}</span>,
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: __('Status'),
        cell: ({ row }) => (
            <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{row.original.status}</span>
        ),
    },
];

function BatchResult({ data }) {
    const b = data.batch;
    const lots = data.distinct_input_lots ?? [];

    return (
        <div className="space-y-4">
            <Card>
                <div className="flex items-start justify-between flex-wrap gap-2">
                    <div>
                        <span className="text-xs font-semibold uppercase text-om-faint">{__('Finished LOT')}</span>
                        <h2 className="text-2xl font-bold text-om-ink font-mono">{b.lot_number}</h2>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Work Order')}: <span className="font-medium">{b.work_order?.order_no ?? '—'}</span>
                            {' · '}{__('Product')}: <span className="font-medium">{b.work_order?.product ?? '—'}</span>
                            {' · '}{__('Batch')} #{b.batch_number}
                        </p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-om-done-bg text-om-done">
                        {__('Backward trace')}
                    </span>
                </div>
            </Card>

            <Card>
                <h3 className="text-lg font-bold text-om-ink mb-3">{__('Ingredient lots')} ({lots.length})</h3>
                {lots.length === 0 ? (
                    <p className="text-sm text-om-muted">{__('No material lots were recorded as consumed for this batch.')}</p>
                ) : (
                    <DataTable
                        data={lots}
                        columns={INGREDIENT_LOT_COLUMNS}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                    />
                )}
            </Card>

            <Card>
                <h3 className="text-lg font-bold text-om-ink mb-3">{__('Process history')}</h3>
                <div className="space-y-3">
                    {b.steps.map((step) => (
                        <div key={step.id} className={`border-l-2 pl-4 py-1 ${step.status === 'DONE' ? 'border-om-done' : 'border-om-line2'}`}>
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-bold text-om-ink">{__('Step')} {step.step_number}: {step.name}</span>
                                {step.workstation && <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-accent">{step.workstation}</span>}
                                {step.completed_by && <span className="text-xs text-om-muted">{__('by')} {step.completed_by}</span>}
                                {step.completed_at && <span className="text-xs text-om-faint">{step.completed_at}</span>}
                            </div>
                            {step.consumptions.length > 0 && (
                                <ul className="mt-1 ml-1 text-sm text-om-muted space-y-0.5">
                                    {step.consumptions.map((c, i) => (
                                        <li key={i} className="flex items-center gap-2">
                                            <span className="w-1 h-1 rounded-full bg-om-faintest" />
                                            <span className="font-mono">{c.lot_number}</span>
                                            <span className="text-om-faint">{c.material}</span>
                                            <span className="text-om-muted">— {formatNumber(c.quantity, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}
                </div>

                {b.output_lots.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-om-line2">
                        <h4 className="text-sm font-semibold text-om-muted mb-2">{__('Output lots')}</h4>
                        {b.output_lots.map((out, i) => (
                            <Link key={i} href={traceLink(out.lot_number)} className="inline-block mr-2 mb-2 px-2 py-1 rounded bg-om-chip text-om-accent text-xs font-mono hover:bg-om-line2">
                                {out.lot_number}
                            </Link>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}

/* ── Material lot (forward + backward) ───────────────────────────────── */

function MaterialLotResult({ forward, backward, recall }) {
    return (
        <div className="space-y-4">
            <Card>
                <span className="text-xs font-semibold uppercase text-om-faint">{__('Material lot')}</span>
                <h2 className="text-2xl font-bold text-om-ink font-mono">{forward.lot.lot_number}</h2>
                {backward.supplier_lot_no && (
                    <p className="text-sm text-om-muted mt-1">{__('Supplier LOT')}: <span className="font-mono">{backward.supplier_lot_no}</span></p>
                )}
                {backward.source_container_no && (
                    <p className="text-sm text-om-muted mt-1">{__('Source container')}: <span className="font-mono">{backward.source_container_no}</span></p>
                )}
            </Card>

            {recall && <RecallImpact recall={recall} />}

            {/* Forward */}
            <Card>
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-lg font-bold text-om-ink">{__('Forward trace — where did this lot go?')}</h3>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-om-downtime-bg text-om-downtime">
                        {forward.work_orders.length} {__('work orders')}
                    </span>
                </div>
                {forward.work_orders.length === 0 ? (
                    <p className="text-sm text-om-muted">
                        {forward.is_finished_good ? __('This is a finished-goods lot - it was not consumed further.') : __('This lot has not been consumed yet.')}
                    </p>
                ) : (
                    <>
                        <ul className="space-y-2">
                            {forward.work_orders.map((wo, i) => (
                                <li key={i} className="flex items-center gap-3 text-sm">
                                    <span className="font-mono font-semibold text-om-ink">{wo.order_no}</span>
                                    <span className="text-om-muted">{wo.product}</span>
                                    <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{wo.status}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-om-faint mt-3">
                            {__('Total consumed')}: {formatNumber(Number(forward.total_consumed), { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                    </>
                )}
            </Card>

            {/* Finished-goods forward leg: output pallet(s) + customer order(s) */}
            {forward.is_finished_good && (
                <Card>
                    <h3 className="text-lg font-bold text-om-ink mb-3">{__('Forward trace - packed & shipped')}</h3>

                    <div className="grid sm:grid-cols-2 gap-4">
                        <div>
                            <h4 className="text-sm font-semibold text-om-muted mb-2">{__('Output pallets')} ({forward.pallets.length})</h4>
                            {forward.pallets.length === 0 ? (
                                <p className="text-sm text-om-muted">{__('This finished lot has not been packed onto a pallet yet.')}</p>
                            ) : (
                                <ul className="space-y-1">
                                    {forward.pallets.map((p, i) => (
                                        <li key={i} className="flex items-center gap-2 text-sm">
                                            <Link href={traceLink(p.pallet_no)} className="font-mono text-om-accent hover:underline">{p.pallet_no}</Link>
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{p.status}</span>
                                            <span className="text-xs text-om-faint">{p.qty}{p.location ? ` · ${p.location}` : ''}{p.shipped_at ? ` · ${p.shipped_at}` : ''}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <div>
                            <h4 className="text-sm font-semibold text-om-muted mb-2">{__('Customer orders')} ({forward.customer_orders.length})</h4>
                            {forward.customer_orders.length === 0 ? (
                                <p className="text-xs text-om-faint">—</p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {forward.customer_orders.map((co, i) => (
                                        <Link key={i} href={traceLink(co)} className="px-2 py-1 rounded bg-om-chip text-om-accent text-xs font-mono hover:bg-om-line2">{co}</Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {/* Backward */}
            <Card>
                <h3 className="text-lg font-bold text-om-ink mb-3">{__('Backward trace — what fed into this lot?')}</h3>
                {backward.source_batch_id ? (
                    <>
                        <p className="text-sm text-om-muted mb-2">
                            {__('Produced by batch')} #{backward.source_batch?.batch_number ?? backward.source_batch_id}
                            {backward.source_batch?.lot_number && (
                                <> (<Link href={traceLink(backward.source_batch.lot_number)} className="text-om-accent hover:underline font-mono">{backward.source_batch.lot_number}</Link>)</>
                            )}
                        </p>
                        <IngredientTree node={backward} />
                    </>
                ) : (
                    <div className="text-sm text-om-muted">
                        <p>{__('Inbound raw lot (terminal).')}</p>
                        {backward.supplier_reference && <p className="mt-1">{__('Supplier reference')}: <span className="font-mono">{backward.supplier_reference}</span></p>}
                        {backward.inspection_id && <p className="mt-1">{__('Inbound inspection')} #{backward.inspection_id}</p>}
                    </div>
                )}
            </Card>
        </div>
    );
}

/** Recursive backward genealogy node (mirrors the old _ingredient-tree partial). */
function IngredientTree({ node }) {
    if (!node.ingredients || node.ingredients.length === 0) return null;

    return (
        <ul className="ml-4 border-l border-om-line2 pl-4 space-y-2">
            {node.ingredients.map((child, i) => (
                <li key={i}>
                    <div className="flex items-center gap-2 flex-wrap text-sm">
                        <Link href={traceLink(child.lot?.lot_number)} className="font-mono font-medium text-om-accent hover:underline">
                            {child.lot?.lot_number ?? '—'}
                        </Link>
                        <span className="text-om-muted">{child.material?.name ?? ''}</span>
                        {child.supplier_lot_no && (
                            <span className="text-xs text-om-faint">{__('Supplier LOT')}: <span className="font-mono">{child.supplier_lot_no}</span></span>
                        )}
                        {child.source_container_no && (
                            <span className="text-xs text-om-faint">{__('Source container')}: <span className="font-mono">{child.source_container_no}</span></span>
                        )}
                        {child.source_batch_id && (
                            <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-accent">{__('semi-finished')}</span>
                        )}
                    </div>
                    {child.truncated ? (
                        <p className="text-xs text-om-downtime ml-1">{__('Trace truncated (max depth reached).')}</p>
                    ) : (
                        <IngredientTree node={child} />
                    )}
                </li>
            ))}
        </ul>
    );
}

/* ── Pallet (pallet → batch → lots → machine → operator → controls) ──── */

function PalletResult({ data }) {
    const p = data.pallet;
    const wo = data.work_order;
    const batch = data.batch;

    return (
        <div className="space-y-4">
            <Card>
                <div className="flex items-start justify-between flex-wrap gap-2">
                    <div>
                        <span className="text-xs font-semibold uppercase text-om-faint">{__('Pallet')}</span>
                        <h2 className="text-2xl font-bold text-om-ink font-mono">{p.pallet_no}</h2>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Work Order')}: <span className="font-medium">{wo?.order_no ?? '—'}</span>
                            {' · '}{__('Product')}: <span className="font-medium">{wo?.product ?? '—'}</span>
                            {wo?.customer_order_no && <> {' · '}{__('Customer Order No')}: <span className="font-medium">{wo.customer_order_no}</span></>}
                        </p>
                        <p className="text-xs text-om-faint mt-1">
                            {__('Quantity')}: {p.qty}{p.location ? ` · ${p.location}` : ''}{p.created_at ? ` · ${p.created_at}` : ''}
                        </p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-om-chip text-om-muted">{p.status}</span>
                </div>
            </Card>

            {!batch ? (
                <Card>
                    <p className="text-sm text-om-muted">{__('This pallet is not linked to a batch, so no genealogy is available.')}</p>
                </Card>
            ) : (
                <>
                    <Card>
                        <div className="flex items-start justify-between flex-wrap gap-2">
                            <div>
                                <span className="text-xs font-semibold uppercase text-om-faint">{__('Batch')}</span>
                                <h3 className="text-xl font-bold text-om-ink">
                                    #{batch.batch_number}
                                    {batch.lot_number && <span className="ml-2 font-mono text-om-accent text-base">{batch.lot_number}</span>}
                                </h3>
                                {batch.machine && <p className="text-sm text-om-muted mt-1">{__('Machine')}: <span className="font-medium">{batch.machine}</span></p>}
                            </div>
                            <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{batch.status}</span>
                        </div>
                    </Card>

                    <Card>
                        <h3 className="text-lg font-bold text-om-ink mb-3">{__('Process history')}</h3>
                        <div className="space-y-3">
                            {batch.steps.map((step, idx) => (
                                <div key={idx} className={`border-l-2 pl-4 py-1 ${step.status === 'DONE' ? 'border-om-done' : 'border-om-line2'}`}>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-bold text-om-ink">{__('Step')} {step.step_number}: {step.name}</span>
                                        {step.machine && <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-accent">{__('Machine')}: {step.machine}</span>}
                                        {step.line && <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-muted">{__('Line')}: {step.line}</span>}
                                        {step.operator && <span className="text-xs text-om-muted">{__('Operator')}: {step.operator}</span>}
                                        {step.completed_at && <span className="text-xs text-om-faint">{step.completed_at}</span>}
                                    </div>
                                    {step.consumptions.length > 0 && (
                                        <ul className="mt-1 ml-1 text-sm text-om-muted space-y-0.5">
                                            {step.consumptions.map((c, i) => (
                                                <li key={i} className="flex items-center gap-2">
                                                    <span className="w-1 h-1 rounded-full bg-om-faintest" />
                                                    <Link href={traceLink(c.lot_number)} className="font-mono text-om-accent hover:underline">{c.lot_number}</Link>
                                                    <span className="text-om-faint">{c.material}</span>
                                                    <span className="text-om-muted">— {formatNumber(c.quantity, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card>
                        <h3 className="text-lg font-bold text-om-ink mb-3">{__('Quality controls')} ({batch.quality_checks.length})</h3>
                        {batch.quality_checks.length === 0 ? (
                            <p className="text-sm text-om-muted">{__('No quality controls recorded for this batch.')}</p>
                        ) : (
                            <div className="space-y-3">
                                {batch.quality_checks.map((qc, i) => (
                                    <div key={i} className={`border-l-2 pl-4 py-1 ${qc.all_passed ? 'border-om-done' : 'border-om-blocked'}`}>
                                        <div className="flex items-center gap-2 flex-wrap text-sm">
                                            <span className={`text-xs px-2 py-0.5 rounded-full ${qc.all_passed ? 'bg-om-done-bg text-om-done' : 'bg-om-blocked-bg text-om-blocked'}`}>
                                                {qc.all_passed ? __('Passed') : __('Failed')}
                                            </span>
                                            {qc.checked_by && <span className="text-om-muted">{__('by')} {qc.checked_by}</span>}
                                            {qc.checked_at && <span className="text-xs text-om-faint">{qc.checked_at}</span>}
                                        </div>
                                        {qc.samples.length > 0 && (
                                            <div className="mt-1 ml-1 flex flex-wrap gap-2">
                                                {qc.samples.map((s, si) => (
                                                    <span key={si} className="text-xs bg-om-panel border border-om-line2 rounded px-2 py-0.5 text-om-muted">
                                                        <span className="text-om-faint">{s.parameter}:</span> {s.value == null ? '—' : String(s.value)}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </>
            )}
        </div>
    );
}

/* ── Customer order (aggregate of work orders) ───────────────────────── */

function CustomerOrderResult({ data }) {
    const wos = data.work_orders ?? [];

    return (
        <div className="space-y-4">
            <Card>
                {/* One work order (traced by its own number) or every order of a customer order. */}
                <span className="text-xs font-semibold uppercase text-om-faint">{data.order_no ? __('Work Order') : __('Customer order')}</span>
                <h2 className="text-2xl font-bold text-om-ink font-mono">{data.order_no ?? data.customer_order_no}</h2>
                {data.order_no ? (
                    data.customer_order_no && (
                        <p className="text-sm text-om-muted mt-1">
                            {__('Customer Order No')}: <Link href={traceLink(data.customer_order_no)} className="font-mono text-om-accent hover:underline">{data.customer_order_no}</Link>
                        </p>
                    )
                ) : (
                    <p className="text-sm text-om-muted mt-1">{wos.length} {__('work orders')}</p>
                )}
            </Card>

            {wos.length === 0 ? (
                <Card><p className="text-sm text-om-muted">{__('No work orders match this customer order.')}</p></Card>
            ) : (
                wos.map((wo, i) => (
                    <Card key={i}>
                        <div className="flex items-center gap-3 flex-wrap mb-3">
                            <span className="font-mono font-semibold text-om-ink">{wo.order_no}</span>
                            <span className="text-om-muted text-sm">{wo.product}</span>
                            <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{wo.status}</span>
                        </div>

                        <div className="grid sm:grid-cols-2 gap-4">
                            <div>
                                <h4 className="text-sm font-semibold text-om-muted mb-2">{__('Pallets')} ({wo.pallets.length})</h4>
                                {wo.pallets.length === 0 ? (
                                    <p className="text-xs text-om-faint">—</p>
                                ) : wo.pallets.map((p, pi) => (
                                    <div key={pi} className="flex items-center gap-2 text-sm mb-1">
                                        <Link href={traceLink(p.pallet_no)} className="font-mono text-om-accent hover:underline">{p.pallet_no}</Link>
                                        <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{p.status}</span>
                                        {p.batch_lot && <span className="text-xs text-om-faint font-mono">{p.batch_lot}</span>}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold text-om-muted mb-2">{__('Batches')} ({wo.batches.length})</h4>
                                {wo.batches.length === 0 ? (
                                    <p className="text-xs text-om-faint">—</p>
                                ) : wo.batches.map((b, bi) => (
                                    <div key={bi} className="mb-2">
                                        <div className="flex items-center gap-2 text-sm">
                                            <span className="text-om-muted">#{b.batch_number}</span>
                                            {b.lot_number && <Link href={traceLink(b.lot_number)} className="font-mono text-om-accent hover:underline">{b.lot_number}</Link>}
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{b.status}</span>
                                        </div>
                                        {b.output_lots?.length > 0 && (
                                            <div className="ml-3 mt-0.5">
                                                <span className="text-xs text-om-faint mr-1">{__('Output lots')}:</span>
                                                {b.output_lots.map((o, oi) => (
                                                    <Link key={oi} href={traceLink(o.lot_number)} className="inline-block mr-1 mb-0.5 font-mono text-xs text-om-accent hover:underline" title={o.material ?? ''}>{o.lot_number}</Link>
                                                ))}
                                            </div>
                                        )}
                                        {b.components?.length > 0 && (
                                            <div className="ml-3 mt-0.5">
                                                <span className="text-xs text-om-faint mr-1">{__('Components used')}:</span>
                                                {b.components.map((c, ci) => (
                                                    <Link key={ci} href={traceLink(c.lot_number)} className="inline-block mr-1 mb-0.5 font-mono text-xs text-om-accent hover:underline" title={c.material ?? ''}>{c.lot_number}</Link>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                ))
            )}
        </div>
    );
}

/* ── Recall impact (reverse trace — affected finished goods) ─────────── */

const SERIAL_STATUS_BADGE = {
    scrapped: 'bg-om-blocked-bg text-om-blocked',
    shipped: 'bg-om-done-bg text-om-done',
    completed: 'bg-om-done-bg text-om-done',
};

function RecallImpact({ recall }) {
    const workOrders = recall.work_orders ?? [];
    const totals = recall.totals ?? { work_orders: 0, finished_serials: 0, quantity_consumed: 0 };

    return (
        <Card className="border-om-blocked/40">
            <div className="flex items-center justify-between gap-2 flex-wrap mb-1">
                <h3 className="text-lg font-bold text-om-ink">{__('Recall impact')}</h3>
                <div className="flex gap-2">
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-om-blocked-bg text-om-blocked">
                        {totals.work_orders} {__('work orders')}
                    </span>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-om-chip text-om-muted">
                        {totals.finished_serials} {__('units')}
                    </span>
                </div>
            </div>
            <p className="text-sm text-om-muted mb-4">{__('Finished work orders and units that contain this component.')}</p>

            {workOrders.length === 0 ? (
                <p className="text-sm text-om-muted">{__('No downstream consumption recorded yet.')}</p>
            ) : (
                <div className="space-y-3">
                    {workOrders.map((wo, i) => (
                        <div key={i} className="border-l-2 border-om-blocked pl-4 py-1">
                            <div className="flex items-center gap-2 flex-wrap text-sm">
                                <span className="font-mono font-semibold text-om-ink">{wo.order_no}</span>
                                <span className="text-om-muted">{wo.product ?? '—'}</span>
                                <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{wo.status}</span>
                                <span className="text-xs text-om-faint">
                                    {__('Consumed')}: {formatNumber(Number(wo.quantity_consumed), { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                                {wo.batches?.length > 0 && (
                                    <span className="text-xs text-om-faint">{__('Batches')}: #{wo.batches.join(', #')}</span>
                                )}
                            </div>
                            {wo.finished_serials?.length > 0 && (
                                <div className="mt-1.5 ml-1">
                                    <span className="text-xs text-om-faint mr-2">{__('Units')} ({wo.finished_serials.length}):</span>
                                    {wo.finished_serials.map((u, j) => (
                                        <Link
                                            key={j}
                                            href={traceLink(u.serial_no)}
                                            className={`inline-block mr-1.5 mb-1.5 px-2 py-0.5 rounded text-xs font-mono hover:underline ${SERIAL_STATUS_BADGE[u.status] ?? 'bg-om-chip text-om-accent'}`}
                                            title={u.status}
                                        >
                                            {u.serial_no}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                    {recall.truncated && (
                        <p className="text-xs text-om-downtime">{__('Trace truncated (max depth reached).')}</p>
                    )}
                </div>
            )}
        </Card>
    );
}

/* ── Component journeys (finished unit → component → lines passed through) ─ */

const COMPONENT_STATUS_DONE = 'DONE';

function ComponentJourneys({ components }) {
    const list = components ?? [];

    return (
        <Card>
            <h3 className="text-lg font-bold text-om-ink mb-1">{__('Components & production lines')}</h3>
            <p className="text-sm text-om-muted mb-4">
                {__('Lines and workstations each component passed through during its own production.')}
            </p>

            {list.length === 0 ? (
                <p className="text-sm text-om-muted">{__('No components recorded for this unit.')}</p>
            ) : (
                <div className="space-y-4">
                    {list.map((c, i) => (
                        <div key={i} className="border-l-2 border-om-line2 pl-4">
                            <div className="flex items-center gap-2 flex-wrap text-sm">
                                <Link href={traceLink(c.lot_number)} className="font-mono font-semibold text-om-accent hover:underline">
                                    {c.lot_number ?? '—'}
                                </Link>
                                <span className="text-om-muted">{c.material ?? ''}</span>
                                {c.material_code && <span className="text-xs text-om-faint font-mono">{c.material_code}</span>}
                                {c.lines?.length > 0 && (
                                    <span className="flex items-center gap-1 flex-wrap">
                                        {c.lines.map((ln, j) => (
                                            <span key={j} className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-accent" title={__('Production line')}>
                                                {ln.name}
                                            </span>
                                        ))}
                                    </span>
                                )}
                            </div>

                            {c.is_raw ? (
                                <p className="text-xs text-om-faint mt-1">
                                    {__('Raw material (supplied) - no internal production line.')}
                                    {c.supplier_lot_no && <> · {__('Supplier LOT')}: <span className="font-mono">{c.supplier_lot_no}</span></>}
                                </p>
                            ) : c.steps?.length > 0 ? (
                                <ul className="mt-1.5 ml-1 space-y-0.5 text-sm">
                                    {c.steps.map((s, j) => (
                                        <li key={j} className="flex items-center gap-2 flex-wrap">
                                            <span className={`w-1.5 h-1.5 rounded-full ${s.status === COMPONENT_STATUS_DONE ? 'bg-om-done' : 'bg-om-faintest'}`} />
                                            <span className="text-om-muted">{__('Step')} {s.step_number}: {s.name}</span>
                                            {s.line && <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-accent">{s.line}</span>}
                                            {s.workstation && <span className="text-xs text-om-faint">{s.workstation}</span>}
                                            {s.completed_by && <span className="text-xs text-om-muted">{__('by')} {s.completed_by}</span>}
                                            {s.completed_at && <span className="text-xs text-om-faint">{s.completed_at}</span>}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-xs text-om-faint mt-1">{__('No production steps recorded for this component.')}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

/* ── Serial unit (per-unit history) ──────────────────────────────────── */

function SerialResult({ unit, recall, components }) {
    const RESULT_BADGE = {
        pass: 'bg-om-done-bg text-om-done',
        fail: 'bg-om-blocked-bg text-om-blocked',
    };

    return (
        <div className="space-y-4">
            <Card>
                <span className="text-xs font-semibold uppercase text-om-faint">{__('Serial unit')}</span>
                <h2 className="text-2xl font-bold text-om-ink font-mono">{unit.serial_no}</h2>
                <p className="text-sm text-om-muted mt-1">
                    {__('Product')}: <span className="font-medium">{unit.product ?? '—'}</span>
                    {unit.work_order && <> · {__('Work Order')}: <span className="font-medium">{unit.work_order}</span></>}
                    {' · '}<span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-muted">{unit.status}</span>
                </p>
            </Card>

            <ComponentJourneys components={components} />

            {recall && <RecallImpact recall={recall} />}

            <Card>
                <h3 className="text-lg font-bold text-om-ink mb-3">{__('Process history')} ({unit.history.length})</h3>
                {unit.history.length === 0 ? (
                    <p className="text-sm text-om-muted">{__('No processing steps recorded for this unit yet.')}</p>
                ) : (
                    <div className="space-y-3">
                        {unit.history.map((h, i) => (
                            <div key={i} className={`border-l-2 pl-4 py-1 ${h.result === 'fail' ? 'border-om-blocked' : 'border-om-done'}`}>
                                <div className="flex items-center gap-2 flex-wrap text-sm">
                                    <span className="font-semibold text-om-ink">{h.workstation ?? __('Unknown')}</span>
                                    {h.line && <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-accent">{h.line}</span>}
                                    {h.step && <span className="text-xs text-om-muted">{h.step}</span>}
                                    {h.operator && <span className="text-xs text-om-muted">{__('by')} {h.operator}</span>}
                                    {h.processed_at && <span className="text-xs text-om-faint">{h.processed_at}</span>}
                                    {h.result && (
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${RESULT_BADGE[h.result] ?? 'bg-om-downtime-bg text-om-downtime'}`}>
                                            {h.result}
                                        </span>
                                    )}
                                </div>
                                {h.parameters && Object.keys(h.parameters).length > 0 && (
                                    <div className="mt-1 ml-1 flex flex-wrap gap-2">
                                        {Object.entries(h.parameters).map(([pk, pv]) => (
                                            <span key={pk} className="text-xs bg-om-panel border border-om-line2 rounded px-2 py-0.5 text-om-muted">
                                                <span className="text-om-faint">{pk}:</span> {typeof pv === 'object' ? JSON.stringify(pv) : String(pv)}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
