import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Breadcrumbs, Button, Icon, StatusBadge, Tabs, TextField } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import PageTitle from '../../../components/PageTitle';
import ResourceTable from '../../../components/ResourceTable';
import { woColumns } from '../work-orders/columns';
import { STATUS_STYLES as LOT_STATUS_STYLES, materialLotStatusLabel } from '../material-lots/fields';
import { serialStatusBadge, serialStatusLabel } from '../../../lib/serialStatus';
import { __ } from '../../../lib/i18n';
import { BatchResult, ComponentResult, MaterialLotResult, OrderResult, PalletResult, SerialResult, traceLink } from './results';

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
    component: 'orders',
    work_order: 'orders',
    customer_order: 'orders',
    batch: 'lots',
    material_lot: 'lots',
    serial: 'units',
    pallet: 'pallets',
};

export default function TraceabilityIndex() {
    const { term = '', result = null } = usePage().props;
    const [q, setQ] = useState(term);
    const [tab, setTab] = useState(() => {
        const param = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
        return ['orders', 'units', 'lots', 'pallets'].includes(param) ? param : 'orders';
    });

    // The tab lives in the address (?tab=…) so a refresh, a shared link or the
    // browser's Back lands on the same list. Replaced in place, not pushed: it is
    // a view of one page, not a new one, and the tables must not remount.
    const selectTab = (next) => {
        setTab(next);
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (next === 'orders') url.searchParams.delete('tab'); else url.searchParams.set('tab', next);
            window.history.replaceState(window.history.state, '', url);
        }
    };

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
                        onChange={selectTab}
                        tabs={[
                            { value: 'orders', label: __('Work Orders') },
                            { value: 'units', label: __('Serial units') },
                            { value: 'lots', label: __('Material Lots') },
                            { value: 'pallets', label: __('Pallets') },
                        ]}
                    />
                )}
            </div>

            {!tracing && <BrowseTable tab={tab} />}

            {tracing && (
                <div className="px-6 pb-6 max-w-5xl mx-auto">
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
                    {(result?.type === 'work_order' || result?.type === 'customer_order') && <OrderResult data={result.data} />}
                    {result?.type === 'batch' && <BatchResult data={result.data} />}
                    {result?.type === 'material_lot' && <MaterialLotResult forward={result.forward} backward={result.backward} recall={result.recall} units={result.units ?? []} />}
                    {result?.type === 'serial' && <SerialResult unit={result.data} recall={result.recall} components={result.components} installed={result.installed} installedIn={result.installed_in} />}
                    {result?.type === 'component' && <ComponentResult data={result.data} />}
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

    if (tab === 'units') {
        return (
            <ResourceTable
                key="units"
                shape="serial_units"
                title={__('Traceability')}
                detailHref={(r) => traceLink(r.serial_no ?? r.psn)}
                columns={[
                    { key: 'serial_no', label: __('Serial No'), className: 'font-mono', filter: 'text', link: true },
                    { key: 'psn', label: __('Process serial (PSN)'), className: 'font-mono text-om-muted', filter: 'text', render: (r) => r.psn || '—' },
                    { key: 'work_order', label: __('Work Order'), className: 'font-mono text-om-muted', value: (r) => workOrderNumbers[r.work_order_id] ?? '', render: (r) => workOrderNumbers[r.work_order_id] ?? '—' },
                    { key: 'material', label: __('Material'), className: 'text-om-muted', value: (r) => materialNames[r.material_id] ?? '', render: (r) => materialNames[r.material_id] ?? '—' },
                    {
                        key: 'status',
                        label: __('Status'),
                        value: (r) => serialStatusLabel(r.status),
                        render: (r) => <StatusBadge size="sm" {...serialStatusBadge(r.status)} />,
                    },
                    { key: 'produced_at', label: __('Produced'), filter: 'date', className: 'text-om-muted', render: (r) => (r.produced_at ? String(r.produced_at).slice(0, 10) : '—') },
                    { key: 'created_at', label: __('Registered'), filter: 'date', className: 'text-om-muted', render: (r) => (r.created_at ? String(r.created_at).slice(0, 10) : '—') },
                ]}
                orderBy="created_at"
                orderDir="desc"
                actions={traceAction((r) => r.serial_no ?? r.psn)}
                enableSelection={false}
                emptyText="No serial units yet."
            />
        );
    }

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
