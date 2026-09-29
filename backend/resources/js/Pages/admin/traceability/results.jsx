import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { Badge, Button, Icon, StatusBadge, Stepper } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { __, formatNumber } from '../../../lib/i18n';
import { woStatusBadge, woStatusLabel } from '../work-orders/fields';
import { materialLotStatusBadge } from '../material-lots/fields';
import { serialStatusBadge, serialStatusLabel } from '../../../lib/serialStatus';

/**
 * The trace result views, one per kind of thing the console resolves. They
 * share the work-order detail page's vocabulary - a mono identifier with its
 * status chip on top, titled cards below, mono for anything you would read
 * out - so a trace reads like the rest of admin rather than a report of its own.
 */

export function traceLink(identifier) {
    return `/admin/traceability?q=${encodeURIComponent(identifier)}`;
}

const qty = (n) => formatNumber(Number(n ?? 0), { maximumFractionDigits: 2 });

/* ── status chips ────────────────────────────────────────────────────── */

// Batch statuses share the work-order vocabulary, so their labels come from the
// same map - a batch chip next to an order chip reads 'Done' / 'Done', not
// 'Done' / 'DONE'.
const BATCH_META = {
    PENDING: { tone: 'neutral', icon: 'clock', label: () => woStatusLabel('PENDING') },
    IN_PROGRESS: { tone: 'active', icon: 'play', label: () => woStatusLabel('IN_PROGRESS') },
    DONE: { tone: 'success', icon: 'circle-check', label: () => woStatusLabel('DONE') },
    CANCELLED: { tone: 'ghost', icon: 'slash', label: () => woStatusLabel('CANCELLED') },
};

const PALLET_META = {
    open: { tone: 'active', icon: 'package-open', label: () => __('Pallet open') },
    closed: { tone: 'info', icon: 'package', label: () => __('Closed') },
    shipped: { tone: 'success', icon: 'truck', label: () => __('Shipped') },
};


const RESULT_META = {
    pass: { tone: 'success', icon: 'circle-check', label: () => __('Passed') },
    fail: { tone: 'critical', icon: 'x', label: () => __('Failed') },
    rework: { tone: 'warn', icon: 'rotate-ccw', label: () => __('Rework') },
};

// A skipped step was passed deliberately: it draws as travelled (done), and its
// caption says so, rather than as a hollow 'not yet' that reads as stuck.
const STEP_STATUS = { DONE: 'done', SKIPPED: 'done', IN_PROGRESS: 'active', READY: 'active', BLOCKED: 'blocked' };
const stepCaption = (status) => (status === 'SKIPPED' ? __('Skipped') : __(status));

function chip(meta, status, size = 'sm') {
    const m = meta[status];
    return <StatusBadge size={size} tone={m?.tone ?? 'neutral'} icon={m?.icon} label={m?.label ? m.label() : __(status)} />;
}

const OrderChip = ({ status, size = 'sm' }) => <StatusBadge size={size} {...woStatusBadge(status)} />;
const LotChip = ({ status, size = 'sm' }) => <StatusBadge size={size} {...materialLotStatusBadge(status)} />;
const BatchChip = ({ status, size = 'sm' }) => chip(BATCH_META, status, size);
const PalletChip = ({ status, size = 'sm' }) => chip(PALLET_META, status, size);
const SerialChip = ({ status, size = 'sm' }) => <StatusBadge size={size} {...serialStatusBadge(status)} />;

/* ── building blocks (the work-order page's Card / Field / Row) ──────── */

/** Mono identifier, its status, and one line of facts - the page's headline. */
function Header({ kind, id, status, facts = [], children }) {
    return (
        <div className="mb-5 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div className="min-w-0">
                <div className="mb-[5px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{kind}</div>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="font-mono text-[28px] leading-none font-semibold tracking-[-0.02em] text-om-ink">{id}</h1>
                    {status}
                </div>
                {facts.filter(Boolean).length > 0 && (
                    <p className="mt-1.5 text-[13.5px] text-om-muted">{facts.filter(Boolean).join(' · ')}</p>
                )}
            </div>
            {children}
        </div>
    );
}

/** A titled panel; `action` sits on the title row (a counter, a link). */
function Card({ title, action, children, bodyClassName = 'px-[22px] pb-5' }) {
    return (
        <section className="overflow-hidden rounded-om border border-om-line bg-om-card">
            <div className="flex items-center justify-between gap-3 px-[22px] pt-4 pb-3">
                <h2 className="text-[15px] font-semibold text-om-ink">{title}</h2>
                {action}
            </div>
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

/** One labelled fact. Mono for anything you would read out or compare. */
function Field({ label, mono = false, children, className = '' }) {
    return (
        <div className={className}>
            <div className="mb-[5px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{label}</div>
            <div className={`text-[14px] font-medium text-om-ink ${mono ? 'font-mono' : ''}`}>{children ?? '—'}</div>
        </div>
    );
}

function Row({ label, value, last = false }) {
    return (
        <div className={`flex justify-between py-[9px] ${last ? '' : 'border-b border-om-line2'}`}>
            <dt className="text-[13px] text-om-muted">{label}</dt>
            <dd className="font-mono text-[13px] font-medium text-om-ink">{value}</dd>
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-[13px] text-om-muted">{children}</p>;
}

/** An identifier that opens its own trace. */
function Ref({ id, className = '' }) {
    if (!id) return <span className="text-om-faintest">—</span>;
    return <Link href={traceLink(id)} className={`font-mono text-om-accent hover:underline ${className}`}>{id}</Link>;
}

/**
 * Identifiers as a chip row - for a handful of things, not a list. Items are
 * strings, or `{ id, tone?, title? }` when a chip carries its own state.
 */
function RefChips({ items = [] }) {
    if (items.length === 0) return <span className="text-om-faintest">—</span>;
    return (
        <div className="flex flex-wrap gap-1.5">
            {items.map((item, i) => {
                const { id, tone = 'neutral', title } = typeof item === 'string' ? { id: item } : item;
                return (
                    <Link key={i} href={traceLink(id)} title={title}>
                        <Badge variant={tone} className="hover:underline">{id}</Badge>
                    </Link>
                );
            })}
        </div>
    );
}

const dash = (v) => (v == null || v === '' ? '—' : v);

/**
 * A span of seconds as the two units that matter at that size — "42s",
 * "5m 10s", "1h 12m", "3d 4h" — in the language-neutral suffixes the rest of
 * the app uses for elapsed time.
 */
function span(seconds) {
    if (seconds == null || Number.isNaN(Number(seconds))) return null;
    const s = Math.max(0, Math.round(Number(seconds)));
    const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    if (d > 0) return h > 0 ? `${d}d ${h}h` : `${d}d`;
    if (h > 0) return m > 0 ? `${h}h ${m}m` : `${h}h`;
    if (m > 0) return sec > 0 ? `${m}m ${sec}s` : `${m}m`;
    return `${sec}s`;
}
const spanMin = (minutes) => (minutes == null ? null : span(minutes * 60));

/* ── shared sections ─────────────────────────────────────────────────── */

/**
 * Batch routing as the same Stepper the work-order page draws, with the lots
 * consumed at each step listed under it - that is the genealogy, step by step.
 */
function ProcessHistory({ steps = [], station = (s) => s.workstation, operator = (s) => s.completed_by }) {
    if (steps.length === 0) return <Empty>{__('No production steps recorded.')}</Empty>;
    return (
        <Stepper
            steps={steps.map((step) => ({
                key: step.id ?? step.step_number,
                label: step.step_number,
                title: step.name,
                status: STEP_STATUS[step.status] ?? 'pending',
                description: [
                    step.status === 'SKIPPED' ? stepCaption(step.status) : null,
                    station(step), operator(step),
                    // Start → end when the start is known; the end alone otherwise.
                    step.started_at && step.completed_at ? `${step.started_at} → ${step.completed_at}` : (step.completed_at ?? (step.started_at ? `${__('Started')} ${step.started_at}` : null)),
                ].filter(Boolean).join(' · ') || stepCaption(step.status),
                meta: (step.duration_minutes != null || step.waited_minutes != null) ? (
                    <span className="flex flex-col items-end text-om-muted">
                        {step.duration_minutes != null && <span>{spanMin(step.duration_minutes)}</span>}
                        {step.waited_minutes != null && <span className="text-[11px] text-om-faint">{__('waited :time', { time: spanMin(step.waited_minutes) })}</span>}
                    </span>
                ) : null,
                // Lots consumed at this step, under the caption rather than in the
                // Stepper's action slot: that slot cannot shrink, and a step with
                // several long material names would squeeze the title to an ellipsis.
                body: step.consumptions?.length > 0 ? <Consumptions items={step.consumptions} /> : null,
            }))}
        />
    );
}

function Consumptions({ items }) {
    return (
        <ul className="mt-1.5 flex flex-col gap-0.5 text-[12px]">
            {items.map((c, i) => (
                <li key={i} className="flex flex-wrap items-center gap-x-2">
                    <Ref id={c.lot_number} />
                    <span className="text-om-muted">{c.material}</span>
                    <span className="font-mono text-om-faint">{qty(c.quantity)}</span>
                </li>
            ))}
        </ul>
    );
}

// Built per render, not at module load: `__()` needs the catalogue, which is
// not there yet when this file is first imported.
const lotColumns = () => [
    { id: 'lot_number', accessorKey: 'lot_number', header: __('LOT'), cell: ({ row }) => <Ref id={row.original.lot_number} /> },
    { id: 'material', accessorFn: (r) => r.material ?? '', header: __('Material'), cell: ({ row }) => <span>{row.original.material ?? '—'} <span className="font-mono text-[11px] text-om-faint">{row.original.material_code}</span></span> },
    { id: 'supplier_lot_no', accessorFn: (r) => r.supplier_lot_no ?? '', header: __('Supplier LOT'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.supplier_lot_no)}</span> },
    { id: 'source_container_no', accessorFn: (r) => r.source_container_no ?? '', header: __('Source container'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.source_container_no)}</span> },
    { id: 'status', accessorKey: 'status', header: __('Status'), cell: ({ row }) => <LotChip status={row.original.status} /> },
];

function LotTable({ lots }) {
    const columns = useMemo(lotColumns, []);
    return <DataTable data={lots} columns={columns} searchable={false} columnToggle={false} paginated={false} />;
}

function QualityControls({ checks = [] }) {
    if (checks.length === 0) return <Empty>{__('No quality controls recorded for this batch.')}</Empty>;
    return (
        <ul className="flex flex-col divide-y divide-om-line2">
            {checks.map((qc, i) => (
                <li key={i} className="flex flex-wrap items-start gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
                    <StatusBadge size="sm" tone={qc.all_passed ? 'success' : 'critical'} icon={qc.all_passed ? 'circle-check' : 'x'} label={qc.all_passed ? __('Passed') : __('Failed')} />
                    <span className="text-[13px] text-om-muted">
                        {qc.checked_by && <>{__('by')} {qc.checked_by}</>}
                        {qc.checked_at && <span className="ml-2 font-mono text-[11px] text-om-faint">{qc.checked_at}</span>}
                    </span>
                    {qc.samples?.length > 0 && (
                        <div className="flex w-full flex-wrap gap-1.5">
                            {qc.samples.map((s, si) => (
                                <Badge key={si}><span className="text-om-faint">{s.parameter}:</span>&nbsp;{s.value == null ? '—' : String(s.value)}</Badge>
                            ))}
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

/** Reverse trace: the finished orders and units this component ended up in. */
function RecallImpact({ recall }) {
    const workOrders = recall.work_orders ?? [];
    const totals = recall.totals ?? { work_orders: 0, finished_serials: 0 };
    return (
        <Card
            title={__('Recall impact')}
            action={
                <div className="flex gap-2">
                    <Badge variant={totals.work_orders > 0 ? 'danger' : 'neutral'}>{totals.work_orders} {__('work orders')}</Badge>
                    <Badge>{totals.finished_serials} {__('units')}</Badge>
                </div>
            }
        >
            {workOrders.length === 0 ? (
                <Empty>{__('No downstream consumption recorded yet.')}</Empty>
            ) : (
                <ul className="flex flex-col divide-y divide-om-line2">
                    {workOrders.map((wo, i) => (
                        <li key={i} className="py-3 first:pt-0 last:pb-0">
                            <div className="flex flex-wrap items-center gap-3">
                                <Ref id={wo.order_no} className="text-[13px] font-semibold" />
                                <span className="text-[13px] text-om-muted">{wo.product ?? '—'}</span>
                                <OrderChip status={wo.status} />
                                <span className="ml-auto font-mono text-[11px] text-om-faint">
                                    {__('Consumed')}: {qty(wo.quantity_consumed)}
                                    {wo.batches?.length > 0 && <> · {__('Batches')}: #{wo.batches.join(', #')}</>}
                                </span>
                            </div>
                            {wo.finished_serials?.length > 0 && (
                                <div className="mt-2">
                                    <RefChips
                                        items={wo.finished_serials.map((u) => ({
                                            id: u.serial_no ?? u.psn,
                                            tone: u.status === 'scrapped' ? 'danger' : 'neutral',
                                            title: u.status ? serialStatusLabel(u.status) : undefined,
                                        }))}
                                    />
                                </div>
                            )}
                        </li>
                    ))}
                    {recall.truncated && <li className="pt-2 text-[12px] text-om-downtime">{__('Trace truncated (max depth reached).')}</li>}
                </ul>
            )}
        </Card>
    );
}

/* ── results ─────────────────────────────────────────────────────────── */

export function BatchResult({ data }) {
    const b = data.batch;
    const lots = data.distinct_input_lots ?? [];
    return (
        <>
            <Header
                kind={__('Finished LOT')}
                id={b.lot_number}
                status={<BatchChip status={b.status} size="md" />}
                facts={[b.work_order?.product, b.work_order?.order_no, __('Batch #:number', { number: b.batch_number })]}
            />
            <div className="flex flex-col gap-4">
                <Card title={`${__('Ingredient lots')} (${lots.length})`} bodyClassName={lots.length ? '' : 'px-[22px] pb-5'}>
                    {lots.length === 0 ? <Empty>{__('No material lots were recorded as consumed for this batch.')}</Empty> : <LotTable lots={lots} />}
                </Card>
                <Card title={__('Process history')}>
                    <ProcessHistory steps={b.steps} />
                    {b.output_lots?.length > 0 && (
                        <div className="mt-4 border-t border-om-line2 pt-4">
                            <Field label={__('Output lots')}><RefChips items={b.output_lots.map((o) => o.lot_number)} /></Field>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

export function MaterialLotResult({ forward, backward, recall, units = [] }) {
    const lot = forward.lot;
    const installedIn = units.filter((u) => u.installed);
    return (
        <>
            <Header
                kind={forward.is_finished_good ? __('Finished-goods lot') : __('Material lot')}
                id={lot.lot_number}
                status={<LotChip status={lot.status} size="md" />}
                facts={[
                    backward.material?.name,
                    backward.supplier_lot_no && `${__('Supplier LOT')} ${backward.supplier_lot_no}`,
                    backward.source_container_no && `${__('Source container')} ${backward.source_container_no}`,
                ]}
            />
            <div className="flex flex-col gap-4">
                {recall && <RecallImpact recall={recall} />}

                {units.length > 0 && (
                    <Card
                        title={__('Serialised units built with this lot')}
                        action={<Badge variant={installedIn.length ? 'outline' : 'neutral'}>{installedIn.length} {__('units')}</Badge>}
                        bodyClassName=""
                    >
                        <DataTable
                            data={units}
                            columns={[
                                { id: 'serial_no', accessorKey: 'serial_no', header: __('Serial No'), cell: ({ row }) => <Ref id={row.original.serial_no} /> },
                                { id: 'psn', accessorFn: (r) => r.psn ?? '', header: __('Process serial (PSN)'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.psn)}</span> },
                                { id: 'work_order', accessorFn: (r) => r.work_order ?? '', header: __('Work Order'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.work_order)}</span> },
                                { id: 'product', accessorFn: (r) => r.product ?? '', header: __('Product'), cell: ({ row }) => dash(row.original.product) },
                                { id: 'bound_at', accessorFn: (r) => r.bound_at ?? '', header: __('Bound'), cell: ({ row }) => <span className="font-mono text-[11px] text-om-muted">{dash(row.original.bound_at)}{row.original.unbound_at ? ` → ${row.original.unbound_at}` : ''}</span> },
                                { id: 'status', accessorKey: 'status', header: __('Status'), cell: ({ row }) => <span className="flex items-center gap-2"><SerialChip status={row.original.status} />{!row.original.installed && <Badge variant="neutral">{__('removed')}</Badge>}</span> },
                            ]}
                            searchable={false}
                            columnToggle={false}
                            paginated={units.length > 25}
                        />
                    </Card>
                )}

                <Card
                    title={__('Forward trace — where did this lot go?')}
                    action={<Badge>{forward.work_orders.length} {__('work orders')}</Badge>}
                >
                    {forward.work_orders.length === 0 ? (
                        <Empty>{forward.is_finished_good ? __('This is a finished-goods lot - it was not consumed further.') : __('This lot has not been consumed yet.')}</Empty>
                    ) : (
                        <dl className="flex flex-col">
                            {forward.work_orders.map((wo, i) => (
                                <div key={i} className="flex items-center gap-3 border-b border-om-line2 py-[9px]">
                                    <Ref id={wo.order_no} className="text-[13px] font-semibold" />
                                    <span className="text-[13px] text-om-muted">{wo.product}</span>
                                    <span className="ml-auto"><OrderChip status={wo.status} /></span>
                                </div>
                            ))}
                            <Row label={__('Total consumed')} value={qty(forward.total_consumed)} last />
                        </dl>
                    )}
                </Card>

                {forward.is_finished_good && (
                    <Card title={__('Forward trace - packed & shipped')}>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field label={`${__('Output pallets')} (${forward.pallets.length})`}>
                                {forward.pallets.length === 0 ? (
                                    <span className="text-[13px] font-normal text-om-muted">{__('This finished lot has not been packed onto a pallet yet.')}</span>
                                ) : (
                                    <ul className="flex flex-col gap-1.5">
                                        {forward.pallets.map((p, i) => (
                                            <li key={i} className="flex flex-wrap items-center gap-2 text-[13px]">
                                                <Ref id={p.pallet_no} />
                                                <PalletChip status={p.status} />
                                                <span className="font-mono text-[11px] text-om-faint">{p.qty}{p.location ? ` · ${p.location}` : ''}{p.shipped_at ? ` · ${p.shipped_at}` : ''}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Field>
                            <Field label={`${__('Customer orders')} (${forward.customer_orders.length})`}><RefChips items={forward.customer_orders} /></Field>
                        </div>
                    </Card>
                )}

                <Card title={__('Backward trace — what fed into this lot?')}>
                    {backward.source_batch_id ? (
                        <>
                            <p className="mb-3 text-[13px] text-om-muted">
                                {__('Produced by batch')} #{backward.source_batch?.batch_number ?? backward.source_batch_id}
                                {backward.source_batch?.lot_number && <> (<Ref id={backward.source_batch.lot_number} />)</>}
                            </p>
                            <IngredientTree node={backward} />
                        </>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-3">
                            <Field label={__('Origin')}><span className="font-normal">{__('Inbound raw lot (terminal).')}</span></Field>
                            <Field label={__('Supplier reference')} mono>{dash(backward.supplier_reference)}</Field>
                            <Field label={__('Inbound inspection')} mono>{backward.inspection_id ? `#${backward.inspection_id}` : '—'}</Field>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

/** Recursive backward genealogy: each ingredient lot, then what fed *it*. */
function IngredientTree({ node }) {
    if (!node.ingredients || node.ingredients.length === 0) return null;
    return (
        <ul className="ml-2 flex flex-col gap-2 border-l border-om-line2 pl-4">
            {node.ingredients.map((child, i) => (
                <li key={i}>
                    <div className="flex flex-wrap items-center gap-2 text-[13px]">
                        <Ref id={child.lot?.lot_number} className="font-medium" />
                        <span className="text-om-muted">{child.material?.name ?? ''}</span>
                        {child.lot?.status && <LotChip status={child.lot.status} />}
                        {child.source_batch_id && <Badge variant="outline">{__('semi-finished')}</Badge>}
                        {(child.supplier_lot_no || child.source_container_no) && (
                            <span className="font-mono text-[11px] text-om-faint">
                                {child.supplier_lot_no && <>{__('Supplier LOT')} {child.supplier_lot_no}</>}
                                {child.supplier_lot_no && child.source_container_no && ' · '}
                                {child.source_container_no && <>{__('Source container')} {child.source_container_no}</>}
                            </span>
                        )}
                    </div>
                    {child.truncated ? <p className="mt-1 text-[12px] text-om-downtime">{__('Trace truncated (max depth reached).')}</p> : <IngredientTree node={child} />}
                </li>
            ))}
        </ul>
    );
}

export function PalletResult({ data }) {
    const p = data.pallet;
    const wo = data.work_order;
    const batch = data.batch;
    return (
        <>
            <Header
                kind={__('Pallet')}
                id={p.pallet_no}
                status={<PalletChip status={p.status} size="md" />}
                facts={[wo?.product, wo?.order_no, `${qty(p.qty)} ${__('pcs')}`, p.location, p.created_at]}
            />
            <div className="flex flex-col gap-4">
                <Card title={__('Origin')}>
                    <div className="grid grid-cols-2 gap-x-6 gap-y-[18px] md:grid-cols-4">
                        <Field label={__('Work Order')} mono><Ref id={wo?.order_no} /></Field>
                        <Field label={__('Customer Order No')} mono><Ref id={wo?.customer_order_no} /></Field>
                        <Field label={__('Batch')} mono>{batch ? <span className="flex items-center gap-2">#{batch.batch_number}{batch.lot_number && <Ref id={batch.lot_number} />}<BatchChip status={batch.status} /></span> : '—'}</Field>
                        <Field label={__('Machine')}>{dash(batch?.machine)}</Field>
                    </div>
                </Card>

                {!batch ? (
                    <Card title={__('Genealogy')}><Empty>{__('This pallet is not linked to a batch, so no genealogy is available.')}</Empty></Card>
                ) : (
                    <>
                        <Card title={__('Process history')}>
                            <ProcessHistory steps={batch.steps} station={(s) => s.machine} operator={(s) => s.operator} />
                        </Card>
                        <Card title={`${__('Ingredient lots')} (${batch.input_lots?.length ?? 0})`} bodyClassName={batch.input_lots?.length ? '' : 'px-[22px] pb-5'}>
                            {batch.input_lots?.length ? <LotTable lots={batch.input_lots} /> : <Empty>{__('No material lots were recorded as consumed for this batch.')}</Empty>}
                        </Card>
                        <Card title={`${__('Quality controls')} (${batch.quality_checks.length})`}>
                            <QualityControls checks={batch.quality_checks} />
                        </Card>
                    </>
                )}

                {data.units?.length > 0 && (
                    <Card
                        title={`${__('Serial units on the pallet')} (${data.units.length})`}
                        bodyClassName=""
                        action={<Button variant="outline" size="sm" leftIcon={<Icon name="file-text" size={13} />} onClick={() => window.open(`/packaging/labels/pallet/${p.id}/packing-list`, '_blank', 'noopener')}>{__('Packing list')}</Button>}
                    >
                        <DataTable
                            data={data.units}
                            columns={[
                                { id: 'serial_no', accessorKey: 'serial_no', header: __('Serial No'), cell: ({ row }) => <Ref id={row.original.serial_no} /> },
                                { id: 'psn', accessorFn: (r) => r.psn ?? '', header: __('Process serial (PSN)'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.psn)}</span> },
                                { id: 'carton_no', accessorFn: (r) => r.carton_no ?? '', header: __('Carton'), cell: ({ row }) => <span className="font-mono text-om-muted">{dash(row.original.carton_no)}</span> },
                                { id: 'packed_at', accessorFn: (r) => r.packed_at ?? '', header: __('Packed'), cell: ({ row }) => <span className="font-mono text-[11px] text-om-muted">{dash(row.original.packed_at)}</span> },
                                { id: 'status', accessorKey: 'status', header: __('Status'), cell: ({ row }) => <SerialChip status={row.original.status} /> },
                            ]}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </Card>
                )}
            </div>
        </>
    );
}

/** One work order (by its number) or every order under a customer order. */
export function OrderResult({ data }) {
    const wos = data.work_orders ?? [];
    const single = Boolean(data.order_no);
    return (
        <>
            <Header
                kind={single ? __('Work Order') : __('Customer order')}
                id={data.order_no ?? data.customer_order_no}
                status={single && wos[0] ? <OrderChip status={wos[0].status} size="md" /> : <Badge>{wos.length} {__('work orders')}</Badge>}
                facts={single ? [wos[0]?.product, data.customer_order_no && `${__('Customer Order No')} ${data.customer_order_no}`] : []}
            />
            <div className="flex flex-col gap-4">
                {wos.length === 0 && <Card title={__('Work Orders')}><Empty>{__('No work orders match this customer order.')}</Empty></Card>}
                {wos.map((wo, i) => (
                    <Card
                        key={i}
                        title={single ? __('Production') : <span className="flex items-center gap-3"><Ref id={wo.order_no} /><span className="font-normal text-om-muted">{wo.product}</span></span>}
                        action={single ? null : <OrderChip status={wo.status} />}
                    >
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field label={`${__('Batches')} (${wo.batches.length})`}>
                                {wo.batches.length === 0 ? '—' : (
                                    <ul className="flex flex-col gap-3">
                                        {wo.batches.map((b, bi) => (
                                            <li key={bi} className="flex flex-col gap-1.5 text-[13px]">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-om-muted">#{b.batch_number}</span>
                                                    <Ref id={b.lot_number} />
                                                    <BatchChip status={b.status} />
                                                </div>
                                                {b.output_lots?.length > 0 && <div className="flex items-center gap-2"><span className="font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{__('Output lots')}</span><RefChips items={b.output_lots.map((o) => o.lot_number)} /></div>}
                                                {b.components?.length > 0 && <div className="flex items-center gap-2"><span className="font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{__('Components used')}</span><RefChips items={b.components.map((c) => ({ id: c.lot_number, title: c.material }))} /></div>}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Field>
                            <Field label={`${__('Pallets')} (${wo.pallets.length})`}>
                                {wo.pallets.length === 0 ? '—' : (
                                    <ul className="flex flex-col gap-1.5">
                                        {wo.pallets.map((p, pi) => (
                                            <li key={pi} className="flex flex-wrap items-center gap-2 text-[13px]">
                                                <Ref id={p.pallet_no} />
                                                <PalletChip status={p.status} />
                                                {p.batch_lot && <span className="font-mono text-[11px] text-om-faint">{p.batch_lot}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Field>
                        </div>
                    </Card>
                ))}
            </div>
        </>
    );
}

/**
 * A history row's headline, from the event the station recorded: "Label
 * applied" reads better than the bench's name, and a row with no station at
 * all (an import, a supervisor's action from the office) still says what happened.
 */
const EVENT_TITLE = {
    issued: () => __('Numbers issued'),
    started: (p) => __('Started on process serial :psn', { psn: p.psn ?? '' }),
    subassembly_registered: (p) => __('Sub-assembly registered: :material', { material: p.material ?? '' }),
    // A hold the tests put on names the count; a hand-made one its error code.
    blocked: (p) => (p.hold_source === 'test'
        ? __('Blocked after :count failed tests', { count: p.failed_attempts ?? '' })
        : __('Blocked: :code :reason', { code: p.reason_code ?? '', reason: p.reason ?? '' })),
    unblocked: (p) => (p.released_by === 'test' ? __('Released by a passing retest') : __('Unit released')),
    weight_check: (p) => __('Weight :weight g outside :expected ± :tolerance g', { weight: p.weight_g, expected: p.expected_g, tolerance: p.tolerance_g }),
    label_applied: () => __('Label applied'),
    process_serial_rebound: () => __('Process serial re-bound'),
    component_bound: (p) => __('Component :id bound', { id: p.identifier ?? '' }),
    component_unbound: (p) => __('Component :id removed', { id: p.identifier ?? '' }),
    packed: (p) => (p.carton_no ? __('Packed into :carton', { carton: p.carton_no }) : __('Packed')),
    palletised: (p) => __('Put on pallet :pallet', { pallet: p.pallet_no ?? '' }),
    shipped: (p) => __('Shipped on pallet :pallet', { pallet: p.pallet_no ?? '' }),
    scrapped: () => __('Scrapped'),
    test: (p) => __('Test at :station', { station: p.station ?? '—' }),
};
const HIDDEN_PARAMS = new Set(['event', 'steps', 'source', 'run_id', 'psn', 'carton_no', 'pallet_no', 'identifier', 'started_at', 'ended_at', 'reason_code', 'reason', 'category', 'hold_source', 'released_by', 'failed_attempts', 'verdict', 'expected_g', 'tolerance_g']);

/** A history row's headline (the event, at its station) - also used by the operator's order view. */
export function historyTitle(h) {
    const p = h.parameters ?? {};
    const byEvent = EVENT_TITLE[p.event]?.(p);
    if (byEvent) return h.workstation ? `${byEvent} · ${h.workstation}` : byEvent;
    return h.workstation ?? h.step ?? __('Unknown');
}

export function SerialResult({ unit, recall, components, installed = [], installedIn = [] }) {
    const history = unit.history ?? [];
    return (
        <>
            <Header
                kind={__('Serial unit')}
                id={unit.serial_no ?? unit.psn}
                status={<SerialChip status={unit.status} size="md" />}
                facts={[
                    unit.psn && `${__('Process serial (PSN)')} ${unit.psn}`,
                    unit.product,
                    unit.work_order && `${__('Work Order')} ${unit.work_order}`,
                    unit.carton_no && `${__('Carton')} ${unit.carton_no}`,
                    unit.pallet_no && `${__('Pallet')} ${unit.pallet_no}`,
                    unit.shipped_at && `${__('Shipped')} ${unit.shipped_at}`,
                    unit.lead_time_seconds != null && (unit.lead_time_to_shipping
                        ? __('Lead time :time (first record to shipping)', { time: span(unit.lead_time_seconds) })
                        : __('Lead time :time so far', { time: span(unit.lead_time_seconds) })),
                ]}
            />
            <div className="flex flex-col gap-4">
                <Card title={`${__('Process history')} (${history.length})`}>
                    {history.length === 0 ? <Empty>{__('No processing steps recorded for this unit yet.')}</Empty> : (
                        <Stepper
                            steps={history.map((h, i) => ({
                                key: i,
                                // The result sits by the title, the way a batch row carries
                                // its status: the caption below stays one full line and the
                                // parameters on the right keep one shared edge.
                                title: (
                                    // Wraps rather than truncates: a tester's long step name would
                                    // otherwise hide the result chip on a narrow screen.
                                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1 whitespace-normal">
                                        {historyTitle(h)}
                                        {h.result && h.parameters?.event !== 'scrapped' && chip(RESULT_META, h.result)}
                                    </span>
                                ),
                                status: h.result === 'fail' || h.parameters?.event === 'blocked' ? 'blocked' : 'done',
                                description: [
                                    h.line, h.step, h.operator && `${__('by')} ${h.operator}`, h.processed_at,
                                    // How long after the previous record, and a test's own run time.
                                    h.since_previous_seconds != null && `+${span(h.since_previous_seconds)}`,
                                    h.duration_seconds != null && __('took :time', { time: span(h.duration_seconds) }),
                                    h.notes,
                                ].filter(Boolean).join(' · '),
                                // Measurements and the like; the identifiers already sit in the title.
                                // Under the caption, not in the action slot: that slot cannot
                                // shrink, and a test's parameters would cut the title and date off.
                                body: h.parameters && Object.keys(h.parameters).some((k) => !HIDDEN_PARAMS.has(k)) ? (
                                    <div className="flex flex-wrap gap-1.5">
                                        {Object.entries(h.parameters).filter(([k]) => !HIDDEN_PARAMS.has(k)).map(([k, v]) => <Badge key={k} className="whitespace-nowrap"><span className="text-om-faint">{k}:</span>&nbsp;{typeof v === 'object' ? JSON.stringify(v) : String(v)}</Badge>)}
                                    </div>
                                ) : null,
                            }))}
                        />
                    )}
                </Card>

                {/* A sub-assembly registered by its serial: which products it went into. */}
                {installedIn.length > 0 && <Card title={__('Installed in')}><InstalledInList units={installedIn} /></Card>}
                <InstalledComponents items={installed} />

                {(components?.length > 0 || installed.length === 0) && <ComponentJourneys components={components} />}

                {recall && <RecallImpact recall={recall} />}
            </div>
        </>
    );
}

const COMPONENT_KIND = {
    serial_unit: () => __('Serialised unit'),
    material_lot: () => __('Material lot'),
    identifier: () => __('Identifier'),
};

/** What was scanned into the unit, as built and as it is now. */
function InstalledComponents({ items }) {
    const columns = useMemo(() => [
        { id: 'identifier', accessorKey: 'identifier', header: __('Component'), cell: ({ row }) => <Ref id={row.original.identifier} className="font-semibold" /> },
        { id: 'material', accessorFn: (r) => r.material ?? '', header: __('Material'), cell: ({ row }) => <span>{row.original.material ?? '—'} <span className="font-mono text-[11px] text-om-faint">{row.original.material_code}</span></span> },
        { id: 'kind', accessorKey: 'kind', header: __('Resolved as'), cell: ({ row }) => <Badge variant={row.original.kind === 'identifier' ? 'neutral' : 'outline'}>{COMPONENT_KIND[row.original.kind]?.() ?? row.original.kind}</Badge> },
        { id: 'quantity', accessorFn: (r) => Number(r.quantity ?? 1), header: __('Quantity'), meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{qty(row.original.quantity)}</span> },
        { id: 'bound_at', accessorKey: 'bound_at', header: __('Bound'), cell: ({ row }) => <span className="font-mono text-[11px] text-om-muted">{row.original.bound_at ?? '—'}{row.original.workstation ? ` · ${row.original.workstation}` : ''}{row.original.bound_by ? ` · ${row.original.bound_by}` : ''}</span> },
        { id: 'installed', accessorKey: 'installed', header: __('Status'), cell: ({ row }) => row.original.installed
            ? <StatusBadge size="sm" tone="success" icon="circle-check" label={__('Installed')} />
            : <StatusBadge size="sm" tone="ghost" icon="minus" label={`${__('Removed')} ${row.original.unbound_at ?? ''}`} /> },
    ], []);

    return (
        <Card title={`${__('Installed components')} (${items.filter((i) => i.installed).length})`} bodyClassName={items.length ? '' : 'px-[22px] pb-5'}>
            {items.length === 0
                ? <Empty>{__('No components were scanned into this unit.')}</Empty>
                : <DataTable data={items} columns={columns} searchable={false} columnToggle={false} paginated={false} />}
        </Card>
    );
}

/** The units a component went into: installed now, or taken out since. */
function InstalledInList({ units }) {
    return units.length === 0 ? <Empty>{__('This component has not been scanned into any unit.')}</Empty> : (
        <ul className="flex flex-col divide-y divide-om-line2">
            {units.map((u, i) => (
                <li key={i} className="flex flex-wrap items-center gap-3 py-3 text-[13px] first:pt-0 last:pb-0">
                    <Ref id={u.serial_no ?? u.psn} className="font-semibold" />
                    {u.psn && <span className="font-mono text-om-muted">{u.psn}</span>}
                    <span className="text-om-muted">{u.product ?? ''}</span>
                    {u.work_order && <Ref id={u.work_order} />}
                    {u.status && <SerialChip status={u.status} />}
                    <span className="ml-auto font-mono text-[11px] text-om-faint">
                        {u.installed ? `${__('Bound')} ${u.bound_at ?? ''}` : `${__('Removed')} ${u.unbound_at ?? ''}`}
                        {u.workstation ? ` · ${u.workstation}` : ''}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** A component known by the serial on its label: every unit it was scanned into. */
export function ComponentResult({ data }) {
    const units = data.units ?? [];
    const installedIn = units.filter((u) => u.installed);
    return (
        <>
            <Header
                kind={__('Component')}
                id={data.identifier}
                status={<Badge variant={installedIn.length ? 'outline' : 'neutral'}>{installedIn.length} {__('units')}</Badge>}
                facts={[data.material?.name, data.material?.code]}
            />
            <div className="flex flex-col gap-4">
                <Card title={__('Installed in')}>
                    <InstalledInList units={units} />
                </Card>
            </div>
        </>
    );
}

/** Each component of a unit, and the lines it passed through being made. */
function ComponentJourneys({ components }) {
    const list = components ?? [];
    return (
        <Card title={__('Components & production lines')}>
            {list.length === 0 ? <Empty>{__('No components recorded for this unit.')}</Empty> : (
                <ul className="flex flex-col divide-y divide-om-line2">
                    {list.map((c, i) => (
                        <li key={i} className="py-3 first:pt-0 last:pb-0">
                            <div className="flex flex-wrap items-center gap-2 text-[13px]">
                                <Ref id={c.lot_number} className="font-semibold" />
                                <span className="text-om-muted">{c.material ?? ''}</span>
                                {c.material_code && <span className="font-mono text-[11px] text-om-faint">{c.material_code}</span>}
                                {c.lines?.map((ln, j) => <Badge key={j} variant="outline">{ln.name}</Badge>)}
                            </div>
                            {c.is_raw ? (
                                <p className="mt-1 text-[12px] text-om-faint">
                                    {__('Raw material (supplied) - no internal production line.')}
                                    {c.supplier_lot_no && <> · {__('Supplier LOT')} <span className="font-mono">{c.supplier_lot_no}</span></>}
                                </p>
                            ) : c.steps?.length > 0 ? (
                                <div className="mt-3"><ProcessHistory steps={c.steps} station={(s) => [s.line, s.workstation].filter(Boolean).join(' / ')} /></div>
                            ) : (
                                <p className="mt-1 text-[12px] text-om-faint">{__('No production steps recorded for this component.')}</p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
