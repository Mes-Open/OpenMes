import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Breadcrumbs, Button, Icon, ProgressBar, StatusBadge, StatusPill, Stepper, useToast } from '@openmes/ui';

import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
import PageTitle from '../../../components/PageTitle';
import useConfirm from '../../../components/useConfirm';
import usePrompt from '../../../components/usePrompt';
import DueCountdown from '../../../components/DueCountdown';
import StopProductionModal from './StopProductionModal';
import ChangeRequestModal from './ChangeRequestModal';
import { apiCall } from '../../../lib/http';
import { woStatusBadge } from './fields';
import { TIER_BADGE_STYLES, tierLabel } from '../customers/fields';
import { formatDate, formatNumber, timeAgo, elapsed, __ } from '../../../lib/i18n';

/**
 * One work order, end to end (design ref: OpenMES Order Detail.dc.html).
 *
 * Two columns: what the order *is* and what its batches are doing on the left,
 * what it adds up to on the right. The routing inside a batch is the shared
 * `Stepper`, which now carries each step's own Start/Complete — an admin
 * checking on a stalled order could previously see which step it was sitting on
 * and do nothing about it without walking to the station.
 */
const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

/**
 * Statuses that offer a Resume button (#182).
 *
 * BLOCKED is deliberately absent even though the backend counts it as held: it is set
 * and cleared by the issue workflow, so the way out of it is resolving the issue, not
 * a Resume button that the service would refuse anyway.
 */
const HELD = ['PAUSED', 'CHANGE_HOLD'];

const CR_STATUS_STYLES = {
    DRAFT: 'bg-om-chip text-om-muted',
    SUBMITTED: 'bg-om-chip text-om-accent',
    APPROVED: 'bg-om-running-bg text-om-running',
    APPLIED: 'bg-om-running-bg text-om-running',
    REJECTED: 'bg-om-blocked-bg text-om-blocked',
    CANCELLED: 'bg-om-chip text-om-faint',
};

function fmtDuration(minutes) {
    if (minutes == null) return '—';
    if (minutes < 60) return __(':n min', { n: minutes });
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m ? __(':h h :m min', { h, m }) : __(':h h', { h });
}

/** Routing-step status → the stepper's vocabulary. */
const STEP_STATUS = {
    DONE: 'done',
    IN_PROGRESS: 'active',
    READY: 'active',
    BLOCKED: 'blocked',
};

const BATCH_TONE = {
    PENDING: { tone: 'neutral', icon: 'clock' },
    IN_PROGRESS: { tone: 'active', icon: 'play' },
    DONE: { tone: 'success', icon: 'circle-check' },
    CANCELLED: { tone: 'ghost', icon: 'slash' },
};

/** Activity tone → the dot's colour. */
const ACTIVITY_TONE = {
    running: 'bg-om-running',
    accent: 'bg-om-accent',
    blocked: 'bg-om-blocked',
    downtime: 'bg-om-downtime',
    muted: 'bg-om-faintest',
};

function fmtQty(n) {
    return formatNumber(Number(n ?? 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return null;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return formatDate(dt, { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function AdminWorkOrderShow() {
    const {
        workOrder, customFields = [],
        // Change control (#182) and materials reconciliation (#99).
        stops = [], changeRequests = [], changeControl = {},
        canReclassify = false, materials = [], allocations = [],
    } = usePage().props;
    const { confirm, dialog } = useConfirm();
    const { prompt, dialog: promptDialog } = usePrompt();
    const [showStopModal, setShowStopModal] = useState(false);
    const [showChangeModal, setShowChangeModal] = useState(false);

    const post = (verb, data = {}) =>
        router.post(`/admin/work-orders/${workOrder.id}/${verb}`, data, { preserveScroll: true });

    const status = workOrder.status;
    const isTerminal = TERMINAL.includes(status);

    // An order held for a configuration change may only resume once an approved
    // change has actually been applied — resume then carries which one (#182).
    const needsChange = !!changeControl.requires_change;
    const appliedChangeId = changeControl.applied_change_request_id ?? null;
    const resumeBlocked = needsChange && !appliedChangeId;
    const resume = () => post('resume', appliedChangeId ? { change_request_id: appliedChangeId } : {});
    const planned = Number(workOrder.planned_qty ?? 0);
    const produced = Number(workOrder.produced_qty ?? 0);
    const pct = planned > 0 ? Math.min((produced / planned) * 100, 100) : 0;
    const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < new Date() && !isTerminal;

    // Completing asks for the produced quantity, so it can't be a bare button —
    // the same prompt the list uses, so "Done" means the same thing from either.
    const promptComplete = () => prompt(
        {
            title: __('Complete'),
            label: __('Produced quantity'),
            defaultValue: workOrder.planned_qty,
            type: 'number',
            min: 0,
            confirmLabel: __('Complete'),
        },
        (qty) => post('complete', { produced_qty: qty }),
    );

    return (
        <>
            <Head title={__('Work Order :no', { no: workOrder.order_no })} />

            {/* The trail belongs in the app header's title slot, beside the clock —
                same as every list page. Items match the work-order list's, so the
                shared ancestors look identical whichever page you arrived from. */}
            <PageTitle>
                <Breadcrumbs
                    linkAs={Link}
                    items={[
                        { label: __('Dashboard'), href: '/admin/dashboard', icon: 'layout-dashboard' },
                        { label: __('Work Orders'), href: '/admin/work-orders', icon: 'clipboard-list' },
                        { label: `#${workOrder.order_no}` },
                    ]}
                />
            </PageTitle>

            <div className="mx-auto w-full max-w-[1480px]">
                <Header
                    workOrder={workOrder}
                    status={status}
                    isTerminal={isTerminal}
                    isDuePast={isDuePast}
                    post={post}
                    confirm={confirm}
                    promptComplete={promptComplete}
                    changeControl={changeControl}
                    resume={resume}
                    resumeBlocked={resumeBlocked}
                    onStopProduction={() => setShowStopModal(true)}
                    onRequestChange={() => setShowChangeModal(true)}
                />

                {/* Change hold (#182) — the order is stopped and waiting on a
                    change, which is the one thing a supervisor must not miss. */}
                {status === 'CHANGE_HOLD' && (
                    <div className="mb-5 rounded-om-sm border border-om-line2 bg-om-downtime-bg p-4">
                        <p className="font-semibold text-om-downtime">{__('On change hold')}</p>
                        <p className="mt-1 text-[13px] text-om-downtime">
                            {resumeBlocked
                                ? __('Production cannot resume until an approved change request has been applied.')
                                : __('A change has been applied. Production can be resumed.')}
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1.55fr_1fr]">
                    {/* LEFT — what the order is, and what its batches are doing. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <Details workOrder={workOrder} isDuePast={isDuePast} isTerminal={isTerminal} />
                        <CustomFieldsDisplay definitions={customFields} values={workOrder.custom_fields ?? {}} />
                        <Batches workOrder={workOrder} />
                        <MaterialsReconciliation
                            workOrder={workOrder}
                            allocations={allocations}
                            canReclassify={canReclassify}
                            materials={materials}
                        />
                        <ChangeRequests items={changeRequests} />
                        <StopHistory stops={stops} />
                    </div>

                    {/* RIGHT — what it adds up to. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <Progress workOrder={workOrder} pct={pct} />
                        <Problems workOrder={workOrder} />
                        <Activity entries={workOrder.activity ?? []} />
                    </div>
                </div>
            </div>

            {showStopModal && (
                <StopProductionModal
                    workOrder={workOrder}
                    options={changeControl}
                    onClose={() => setShowStopModal(false)}
                />
            )}
            {showChangeModal && (
                <ChangeRequestModal
                    workOrder={workOrder}
                    options={changeControl}
                    onClose={() => setShowChangeModal(false)}
                />
            )}

            {dialog}
            {promptDialog}
        </>
    );
}

AdminWorkOrderShow.layout = (page) => <AppLayout>{page}</AppLayout>;

/**
 * Title, state, and the verbs this order can take right now.
 *
 * Only the transitions its status allows are offered: a button that exists to
 * explain it doesn't apply is a button in the way. The one the order is waiting
 * for is the filled one; everything else is an outline.
 */
function Header({
    workOrder, status, isTerminal, isDuePast, post, confirm, promptComplete,
    changeControl = {}, resume, resumeBlocked, onStopProduction, onRequestChange,
}) {
    return (
        <div className="mb-5 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="font-mono text-[28px] leading-none font-semibold tracking-[-0.02em] text-om-ink">
                        {workOrder.order_no}
                    </h1>
                    <StatusBadge {...woStatusBadge(status)} />
                    {isDuePast && (
                        <span className="rounded-[20px] bg-om-blocked-bg px-[9px] py-[3px] font-mono text-[10px] tracking-[0.04em] text-om-blocked uppercase">
                            <DueCountdown due={workOrder.due_date} />
                        </span>
                    )}
                </div>
                <p className="mt-1.5 text-[13.5px] text-om-muted">
                    {[
                        workOrder.product_type_name,
                        workOrder.line_name,
                        __('created :ago', { ago: timeAgo(workOrder.created_at) }),
                    ].filter(Boolean).join(' · ')}
                </p>
            </div>

            <div className="flex flex-wrap gap-2">
                <Link
                    href="/admin/work-orders"
                    className="inline-flex items-center gap-1.5 rounded-om-sm border border-om-line bg-om-card px-4 py-[9px] text-[13px] font-medium text-om-muted transition-colors hover:bg-om-chip"
                >
                    <Icon name="arrow-left" size={14} />
                    {__('Back')}
                </Link>
                <Link
                    href={`/admin/work-orders/${workOrder.id}/edit`}
                    className="inline-flex items-center rounded-om-sm border border-om-line bg-om-card px-4 py-[9px] text-[13px] font-medium text-om-ink transition-colors hover:bg-om-chip"
                >
                    {__('Edit')}
                </Link>

                {status === 'PENDING' && (
                    <Button variant="ghost" onClick={() => confirm({ title: __('Reject this work order?') }, () => post('reject'))}>
                        {__('Reject')}
                    </Button>
                )}
                {status === 'ACCEPTED' && (
                    <Button variant="ghost" onClick={() => confirm({ title: __('Reject this work order?') }, () => post('reject'))}>
                        {__('Reject')}
                    </Button>
                )}
                {!isTerminal && (
                    <Button
                        variant="ghost"
                        className="text-om-blocked!"
                        onClick={() => confirm(
                            {
                                title: __('Cancel this work order?'),
                                body: __('Cancelled orders stop production and can be reopened later.'),
                            },
                            () => post('cancel'),
                        )}
                    >
                        {__('Cancel')}
                    </Button>
                )}

                {/* The order's own next step, filled. */}
                {status === 'PENDING' && <Button variant="primary" onClick={() => post('accept')}>{__('Accept')}</Button>}
                {status === 'IN_PROGRESS' && (
                    <>
                        <Button variant="secondary" onClick={() => post('pause')}>{__('Pause')}</Button>
                        {/* Pause is the informal stop; this one records why, and
                            whether a configuration change is needed (#182). */}
                        <Button
                            variant="secondary"
                            onClick={onStopProduction}
                            title={__('Record why production stopped, and whether a configuration change is needed.')}
                        >
                            {__('Stop production')}
                        </Button>
                        {/* `Complete`, not `Done`: the verb, not the state the
                            status badge already shows. */}
                        <Button variant="primary" onClick={promptComplete}>{__('Complete')}</Button>
                    </>
                )}
                {!isTerminal && changeControl.can_raise_change && (
                    <Button variant="ghost" onClick={onRequestChange}>{__('Request change')}</Button>
                )}
                {/* HELD, not just PAUSED: a change hold resumes through the same
                    button, but only once an approved change has been applied. */}
                {HELD.includes(status) && !isTerminal && (
                    <Button
                        variant="primary"
                        onClick={resume}
                        disabled={resumeBlocked}
                        title={resumeBlocked
                            ? __('An approved change request must be applied before this order can resume.')
                            : undefined}
                    >
                        {__('Resume')}
                    </Button>
                )}
                {isTerminal && (
                    <Button variant="primary" onClick={() => confirm({ title: __('Reopen this work order?') }, () => post('reopen'))}>
                        {__('Reopen')}
                    </Button>
                )}
            </div>
        </div>
    );
}

/** A titled panel — the page is five of these. */
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
function Field({ label, mono = false, tone = 'text-om-ink', children, sub, className = '' }) {
    return (
        <div className={className}>
            <div className="mb-[5px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">{label}</div>
            <div className={`text-[14px] font-medium ${mono ? 'font-mono' : ''} ${tone}`}>{children}</div>
            {sub && <div className="mt-[3px] font-mono text-[10px] text-om-blocked">{sub}</div>}
        </div>
    );
}

function Details({ workOrder, isDuePast, isTerminal }) {
    return (
        <Card title={__('Details')}>
            <div className="grid grid-cols-2 gap-x-6 gap-y-[18px] md:grid-cols-3">
                <Field label={__('Order Number')} mono>{workOrder.order_no}</Field>
                <Field label={__('Customer')} tone={workOrder.customer_name ? 'text-om-ink' : 'text-om-faintest'}>
                    {workOrder.customer_name ? (
                        <span className="flex items-center gap-2">
                            {workOrder.customer_name}
                            {workOrder.customer_tier && (
                                <span className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${TIER_BADGE_STYLES[workOrder.customer_tier] ?? 'bg-om-chip text-om-muted'}`}>
                                    {tierLabel(workOrder.customer_tier)}
                                </span>
                            )}
                        </span>
                    ) : '—'}
                </Field>
                <Field label={__('Line')}>{workOrder.line_name ?? '—'}</Field>
                <Field label={__('Product Type')}>{workOrder.product_type_name ?? '—'}</Field>
                <Field label={__('Planned Qty')} mono>{fmtQty(workOrder.planned_qty)}</Field>
                <Field label={__('Produced Qty')} mono>{fmtQty(workOrder.produced_qty)}</Field>
                <Field label={__('Priority')} mono>
                    {workOrder.priority ?? '—'}
                    {workOrder.priority_score != null && (
                        <span className="text-om-faint"> · {__('score')} {workOrder.priority_score}</span>
                    )}
                </Field>
                {workOrder.due_date && (
                    <Field
                        label={__('Due Date')}
                        mono
                        tone={isDuePast ? 'text-om-blocked' : 'text-om-ink'}
                        sub={<DueCountdown due={workOrder.due_date} settled={isTerminal} />}
                    >
                        {fmtDate(workOrder.due_date)}
                    </Field>
                )}
                {workOrder.customer_order_no && (
                    <Field label={__('Customer Order No')} mono>{workOrder.customer_order_no}</Field>
                )}
                {workOrder.description && (
                    <Field label={__('Description')} tone="text-om-muted" className="col-span-2 md:col-span-3">
                        {workOrder.description}
                    </Field>
                )}
                {workOrder.extra_data && Object.keys(workOrder.extra_data).length > 0 && (
                    <div className="col-span-2 md:col-span-3">
                        <div className="mb-[5px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">
                            {__('Extra Data')}
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            {Object.entries(workOrder.extra_data).map(([k, v]) => (
                                <div key={k} className="rounded-om-sm bg-om-panel px-2 py-1">
                                    <span className="text-[11px] text-om-faint">{k}</span>
                                    <p className="text-[13px] font-medium text-om-muted">{String(v)}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </Card>
    );
}

function Batches({ workOrder }) {
    const batches = workOrder.batches ?? [];

    return (
        <section className="overflow-hidden rounded-om border border-om-line bg-om-card">
            <div className="flex items-center gap-2.5 px-[22px] pt-4 pb-3">
                <h2 className="text-[15px] font-semibold text-om-ink">{__('Batches')}</h2>
                <span className="font-mono text-[11px] text-om-faint">{batches.length}</span>
            </div>

            {batches.length === 0 ? (
                <p className="border-t border-om-line2 py-8 text-center text-[13px] text-om-faint">
                    {__('No batches yet.')}
                </p>
            ) : (
                batches.map((batch, i) => (
                    <BatchRow key={batch.id} batch={batch} defaultOpen={i === 0} orderStatus={workOrder.status} />
                ))
            )}
        </section>
    );
}

function BatchRow({ batch, defaultOpen, orderStatus }) {
    const [open, setOpen] = useState(defaultOpen);
    const [busy, setBusy] = useState(null);
    const toast = useToast();

    const steps = batch.steps ?? [];
    const doneCount = steps.filter((s) => s.status === 'DONE').length;
    const target = Number(batch.target_qty ?? 0);
    const pct = target > 0 ? Math.min((Number(batch.produced_qty ?? 0) / target) * 100, 100) : 0;
    const badge = BATCH_TONE[batch.status] ?? BATCH_TONE.PENDING;

    /**
     * Drive a step from here, through the same endpoint the operator station
     * uses — so the rules (order state, sequence, quality gates) are the
     * service's, not a second copy of them written for this page. A refused
     * transition comes back as the server's own message rather than a silent
     * no-op.
     */
    const run = async (step, verb) => {
        setBusy(step.id);
        try {
            const res = await apiCall(`/api/v1/batch-steps/${step.id}/${verb}`, 'POST');
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                toast({ severity: 'error', title: json.message ?? __('Request failed') });
                return;
            }
            toast({ severity: 'success', title: verb === 'start' ? __('Started · :step', { step: step.name }) : __('Completed · :step', { step: step.name }) });
            router.reload({ only: ['workOrder'] });
        } catch (e) {
            toast({ severity: 'error', title: e.message });
        } finally {
            setBusy(null);
        }
    };

    // Only the first step that isn't finished can be started: a routing is a
    // sequence, and the server refuses the rest anyway.
    const nextIndex = steps.findIndex((s) => s.status !== 'DONE');

    return (
        <div className="border-t border-om-line2">
            <div
                onClick={() => setOpen((o) => !o)}
                className="flex cursor-pointer items-center gap-3 px-[22px] py-3 transition-colors hover:bg-om-bg"
            >
                <span className="font-mono text-[13px] font-semibold text-om-ink">
                    {__('Batch #:number', { number: batch.batch_number })}
                </span>
                <StatusBadge size="sm" tone={badge.tone} icon={badge.icon} label={__(batch.status)} />
                <span className="font-mono text-[12px] text-om-muted">
                    {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                </span>
                <ProgressBar value={pct} className="ml-2 h-[5px] max-w-[200px] flex-1" />
                <span className="ml-auto font-mono text-[11px] text-om-faint">
                    {__(':done/:total steps', { done: doneCount, total: steps.length })}
                </span>
                <Icon name={open ? 'chevron-up' : 'chevron-down'} size={15} className="text-om-faintest" />
            </div>

            {open && (
                <div className="border-t border-om-line2 px-[22px] pt-4 pb-4">
                    <Stepper
                        size="sm"
                        steps={steps.map((step, i) => ({
                            key: step.id,
                            title: step.name,
                            label: step.step_number,
                            description: __(step.status),
                            status: STEP_STATUS[step.status] ?? 'pending',
                            meta: stepMeta(step),
                            action: stepAction({ step, i, nextIndex, orderStatus, busy, run }),
                        }))}
                    />
                    {batch.started_at && (
                        <p className="pt-2 font-mono text-[10.5px] text-om-faint">
                            {__('Started :date', { date: fmtDate(batch.started_at) })}
                            {batch.completed_at ? ` · ${__('completed :date', { date: fmtDate(batch.completed_at) })}` : ''}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

/** How long the step took, against its estimate. */
function stepMeta(step) {
    const estimated = step.estimated_duration_minutes ?? null;
    const actual = step.duration_minutes ?? null;
    if (actual != null) {
        const overTime = estimated != null && actual > estimated;
        return (
            <span className={`font-medium ${overTime ? 'text-om-blocked' : 'text-om-running'}`}>
                {__(':n min', { n: actual })}{estimated ? ` / ${__('est. :n min', { n: estimated })}` : ''}
            </span>
        );
    }
    if (step.started_at) {
        return <span className="text-om-accent">{elapsed(step.started_at)}</span>;
    }
    return estimated != null ? <span className="text-om-faint">{__('est. :n min', { n: estimated })}</span> : null;
}

/** The one thing this step is waiting for, if anything. */
function stepAction({ step, i, nextIndex, orderStatus, busy, run }) {
    const live = orderStatus === 'IN_PROGRESS' || orderStatus === 'ACCEPTED';
    if (!live || step.status === 'DONE') return null;

    if (step.status === 'IN_PROGRESS') {
        return (
            <Button variant="primary" size="sm" disabled={busy === step.id} onClick={() => run(step, 'complete')}>
                {__('Complete')}
            </Button>
        );
    }
    if (i === nextIndex) {
        return (
            <Button variant="ghost" size="sm" disabled={busy === step.id} onClick={() => run(step, 'start')}>
                {__('Start')}
            </Button>
        );
    }
    return null;
}

function Progress({ workOrder, pct }) {
    const complete = pct >= 100;

    return (
        <Card title={__('Progress')} action={
            <span className={`font-mono text-[20px] font-semibold ${complete ? 'text-om-running' : 'text-om-ink'}`}>
                {pct.toFixed(pct >= 10 ? 0 : 1)}%
            </span>
        }>
            <ProgressBar
                value={pct}
                className="mb-[18px] h-2"
                color={complete ? 'var(--color-om-running)' : undefined}
            />
            <dl className="flex flex-col">
                <Row label={__('Planned')} value={fmtQty(workOrder.planned_qty)} />
                <Row label={__('Produced')} value={fmtQty(workOrder.produced_qty)} />
                <Row label={__('Batches')} value={(workOrder.batches ?? []).length} />
                {/* Which frozen configuration the floor is building against (#182). */}
                <Row label={__('Configuration version')} value={`v${workOrder.snapshot_version ?? 1}`} last />
            </dl>
        </Card>
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

/**
 * Change requests raised against this order (#182), newest panel in the left
 * column. Each row links to the request itself — the impact analysis and the
 * approve/apply actions live there, not here.
 */
function ChangeRequests({ items = [] }) {
    if (items.length === 0) return null;

    return (
        <Card title={`${__('Change requests')} (${items.length})`}>
            <ul className="flex flex-col gap-2">
                {items.map((cr) => (
                    <li key={cr.id}>
                        <Link
                            href={`/admin/work-order-change-requests/${cr.id}`}
                            className="block rounded-om-sm bg-om-panel p-2.5 transition-colors hover:brightness-[0.98]"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="font-mono text-[12px] text-om-muted">{cr.code}</span>
                                    <span className="truncate text-[12.5px] font-medium text-om-ink">{cr.title}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    {cr.resulting_snapshot_version && (
                                        <span className="text-[11.5px] text-om-faint">
                                            {__('version :v', { v: cr.resulting_snapshot_version })}
                                        </span>
                                    )}
                                    <span className={`rounded px-2 py-0.5 text-[11.5px] font-medium ${CR_STATUS_STYLES[cr.status] ?? 'bg-om-chip text-om-muted'}`}>
                                        {cr.status_label}
                                    </span>
                                </div>
                            </div>
                            <p className="mt-1 text-[12px] text-om-faint">
                                {cr.effective_from_label}
                                {cr.requested_by ? ` · ${cr.requested_by}` : ''}
                            </p>
                        </Link>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

/**
 * Recorded production stops (#182) — a stop is a record with a reason and a
 * duration, not just a status flip, so the order carries its own history of
 * why it wasn't running. An open stop keeps the downtime background.
 */
function StopHistory({ stops = [] }) {
    if (stops.length === 0) return null;

    return (
        <Card title={`${__('Production stops')} (${stops.length})`}>
            <ul className="flex flex-col gap-2">
                {stops.map((stop) => (
                    <li
                        key={stop.id}
                        className={`rounded-om-sm p-2.5 ${stop.is_open ? 'bg-om-downtime-bg' : 'bg-om-panel'}`}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <span className="text-[12.5px] font-medium text-om-ink">{stop.type_label}</span>
                                {stop.requires_change && (
                                    <span className="rounded bg-om-chip px-1.5 py-0.5 text-[11.5px] text-om-accent">
                                        {__('change required')}
                                    </span>
                                )}
                                {stop.is_open && (
                                    <span className="rounded bg-om-downtime-bg px-1.5 py-0.5 text-[11.5px] font-medium text-om-downtime">
                                        {__('open')}
                                    </span>
                                )}
                            </div>
                            <span className="font-mono text-[12.5px] font-medium text-om-muted">
                                {fmtDuration(stop.duration_minutes)}
                            </span>
                        </div>
                        <p className="mt-1 text-[12.5px] text-om-muted">{stop.reason}</p>
                        <p className="mt-1 text-[12px] text-om-faint">
                            {fmtDate(stop.stopped_at)}
                            {stop.stopped_by ? ` · ${stop.stopped_by}` : ''}
                            {' · '}
                            {__('produced :qty at stop', { qty: fmtQty(stop.produced_qty_at_stop) })}
                            {stop.snapshot_version_at_stop
                                ? ` · ${__('version :v', { v: stop.snapshot_version_at_stop })}`
                                : ''}
                        </p>
                        {stop.resumed_at && (
                            <p className="mt-0.5 text-[12px] text-om-faint">
                                {__('Resumed')} {fmtDate(stop.resumed_at)}
                                {stop.resumed_by ? ` · ${stop.resumed_by}` : ''}
                                {stop.resume_notes ? ` — ${stop.resume_notes}` : ''}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function Problems({ workOrder }) {
    const issues = workOrder.issues ?? [];
    const manageHref = `/admin/issues?search=${encodeURIComponent(workOrder.order_no)}`;

    return (
        <Card
            title={__('Problems')}
            action={
                <Link href={manageHref} className="text-[12px] font-semibold text-om-accent hover:underline">
                    {__('Manage →')}
                </Link>
            }
        >
            {issues.length === 0 ? (
                // A dashed box, not an empty panel: "nothing has gone wrong" is
                // an answer, and it should look like one.
                <div className="flex items-center gap-2.5 rounded-om-sm border border-dashed border-om-line p-3.5">
                    <span className="size-2 shrink-0 rounded-full bg-om-running" />
                    <span className="text-[13px] text-om-muted">{__('No problems reported on this order.')}</span>
                </div>
            ) : (
                <ul className="flex flex-col gap-2">
                    {issues.map((issue) => {
                        const openIssue = ['OPEN', 'ACKNOWLEDGED'].includes(issue.status);
                        return (
                            <li key={issue.id}>
                                <Link
                                    href={manageHref}
                                    className={`block rounded-om-sm p-2.5 transition-colors hover:brightness-[0.98] ${
                                        openIssue && issue.is_blocking ? 'bg-om-blocked-bg' : 'bg-om-panel'
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="truncate text-[12.5px] font-medium text-om-ink">
                                            {issue.issue_type_name}
                                        </span>
                                        <StatusPill
                                            status={openIssue ? 'blocked' : 'running'}
                                            pulse={false}
                                            label={__(issue.status)}
                                        />
                                    </div>
                                    <p className="mt-1 truncate text-[12px] text-om-muted">{issue.title}</p>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}

function Activity({ entries }) {
    if (entries.length === 0) return null;

    return (
        <Card title={__('Activity')}>
            <ul className="flex flex-col">
                {entries.map((entry, i) => (
                    <li
                        key={`${entry.at}-${i}`}
                        className="flex items-start gap-3 border-b border-om-line2 py-2 last:border-b-0"
                    >
                        <span className={`mt-[5px] size-2 shrink-0 rounded-[2px] ${ACTIVITY_TONE[entry.tone] ?? ACTIVITY_TONE.muted}`} />
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-[13px] font-medium text-om-ink">{entry.title}</div>
                            {entry.meta && (
                                <div className="mt-px truncate font-mono text-[10px] text-om-faint">{entry.meta}</div>
                            )}
                        </div>
                        <span className="shrink-0 font-mono text-[10.5px] text-om-faint">{elapsed(entry.at)}</span>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

const ALLOC_STATUS_STYLES = {
    allocated: 'bg-om-chip text-om-accent',
    consumed: 'bg-om-running-bg text-om-running',
    returned: 'bg-om-chip text-om-faint',
};

// Materials reconciliation panel (#99): per-allocation record-consumption, return
// leftover and reclassify actions against the work order's pulled materials.
function MaterialsReconciliation({ workOrder, allocations, canReclassify, materials }) {
    const [modal, setModal] = useState(null); // { kind: 'consume'|'return'|'reclassify', alloc }

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
            <h2 className="text-lg font-bold text-om-ink mb-1">
                {__('Materials reconciliation')}{' '}
                <span className="text-sm font-normal text-om-faint">({allocations.length})</span>
            </h2>
            <p className="text-xs text-om-muted mb-4">
                {__('Record what was actually consumed, return leftovers to stock, or reclassify material.')}
            </p>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-om-muted border-b border-om-line2">
                            <th className="py-2 pr-3 font-medium">{__('Material')}</th>
                            <th className="py-2 px-3 font-medium text-right">{__('Allocated')}</th>
                            <th className="py-2 px-3 font-medium text-right">{__('Consumed')}</th>
                            <th className="py-2 px-3 font-medium text-right">{__('Returned')}</th>
                            <th className="py-2 px-3 font-medium text-right">{__('Scrap')}</th>
                            <th className="py-2 px-3 font-medium">{__('Status')}</th>
                            <th className="py-2 pl-3 font-medium text-right">{__('Actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {allocations.map((a) => {
                            const open = a.status === 'allocated';
                            return (
                                <tr key={a.id} className="border-b border-om-line2 last:border-0">
                                    <td className="py-2 pr-3">
                                        <span className="font-medium text-om-ink">{a.material_code}</span>
                                        <span className="text-om-faint"> · {a.material_name}</span>
                                    </td>
                                    <td className="py-2 px-3 text-right font-mono">{fmtQty(a.allocated_qty)}</td>
                                    <td className="py-2 px-3 text-right font-mono">{fmtQty(a.consumed_qty)}</td>
                                    <td className="py-2 px-3 text-right font-mono">{fmtQty(a.returned_qty)}</td>
                                    <td className="py-2 px-3 text-right font-mono">{fmtQty(a.scrap_qty)}</td>
                                    <td className="py-2 px-3">
                                        <span className={`inline-block px-2 py-0.5 rounded text-xs ${ALLOC_STATUS_STYLES[a.status] ?? 'bg-om-chip text-om-muted'}`}>
                                            {__(a.status)}
                                        </span>
                                    </td>
                                    <td className="py-2 pl-3 text-right whitespace-nowrap">
                                        {open && (
                                            <>
                                                <button type="button" onClick={() => setModal({ kind: 'consume', alloc: a })}
                                                    className="text-xs text-om-accent hover:underline">{__('Consume')}</button>
                                                <span className="text-om-faint mx-1.5">·</span>
                                                <button type="button" onClick={() => setModal({ kind: 'return', alloc: a })}
                                                    className="text-xs text-om-accent hover:underline">{__('Return')}</button>
                                                {canReclassify && (
                                                    <>
                                                        <span className="text-om-faint mx-1.5">·</span>
                                                        <button type="button" onClick={() => setModal({ kind: 'reclassify', alloc: a })}
                                                            className="text-xs text-om-accent hover:underline">{__('Reclassify')}</button>
                                                    </>
                                                )}
                                            </>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {modal?.kind === 'consume' && (
                <ConsumeModal workOrder={workOrder} alloc={modal.alloc} onClose={() => setModal(null)} />
            )}
            {modal?.kind === 'return' && (
                <ReturnModal workOrder={workOrder} alloc={modal.alloc} onClose={() => setModal(null)} />
            )}
            {modal?.kind === 'reclassify' && (
                <ReclassifyModal workOrder={workOrder} alloc={modal.alloc} materials={materials} onClose={() => setModal(null)} />
            )}
        </div>
    );
}

function ModalFrame({ title, children }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-lg font-bold text-om-ink mb-4">{title}</h3>
                {children}
            </div>
        </div>
    );
}

const fieldCls = 'w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent';
const labelCls = 'block text-sm font-medium text-om-muted mb-1';

function ModalActions({ onClose, submitLabel, disabled }) {
    return (
        <div className="flex justify-end gap-2 mt-4">
            <button type="button" onClick={onClose}
                className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg">
                {__('Cancel')}
            </button>
            <button type="submit" disabled={disabled}
                className="px-4 py-2 text-sm font-medium text-white bg-om-accent border border-transparent rounded-md hover:brightness-95 disabled:opacity-50">
                {submitLabel}
            </button>
        </div>
    );
}

function ConsumeModal({ workOrder, alloc, onClose }) {
    const [consumed, setConsumed] = useState(String(alloc.consumed_qty || ''));
    const [scrap, setScrap] = useState(String(alloc.scrap_qty || ''));
    const [processing, setProcessing] = useState(false);

    function submit(e) {
        e.preventDefault();
        if (processing) return;
        setProcessing(true);
        router.post(`/admin/work-orders/${workOrder.id}/allocations/${alloc.id}/consume`,
            { consumed_qty: consumed, scrap_qty: scrap || 0 },
            { preserveScroll: true, onSuccess: onClose, onFinish: () => setProcessing(false) });
    }

    return (
        <ModalFrame title={__('Record consumption')}>
            <form onSubmit={submit}>
                <p className="text-xs text-om-muted mb-3">
                    {__('Allocated: :qty', { qty: fmtQty(alloc.allocated_qty) })} {alloc.unit_of_measure}
                </p>
                <div className="mb-3">
                    <label className={labelCls}>{__('Consumed quantity')}</label>
                    <input type="number" step="0.0001" min="0" value={consumed}
                        onChange={(e) => setConsumed(e.target.value)} className={fieldCls} required />
                </div>
                <div className="mb-1">
                    <label className={labelCls}>{__('Scrap quantity')}</label>
                    <input type="number" step="0.0001" min="0" value={scrap}
                        onChange={(e) => setScrap(e.target.value)} className={fieldCls} />
                </div>
                <ModalActions onClose={onClose} submitLabel={__('Save')} disabled={processing} />
            </form>
        </ModalFrame>
    );
}

function ReturnModal({ workOrder, alloc, onClose }) {
    const returnable = Math.max(0, alloc.allocated_qty - alloc.consumed_qty - alloc.scrap_qty);
    const [qty, setQty] = useState('');
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    function submit(e) {
        e.preventDefault();
        if (processing) return;
        setProcessing(true);
        router.post(`/admin/work-orders/${workOrder.id}/allocations/${alloc.id}/return`,
            { qty, reason },
            { preserveScroll: true, onSuccess: onClose, onFinish: () => setProcessing(false) });
    }

    return (
        <ModalFrame title={__('Return to stock')}>
            <form onSubmit={submit}>
                <p className="text-xs text-om-muted mb-3">
                    {__('Returnable: :qty', { qty: fmtQty(returnable) })} {alloc.unit_of_measure}
                </p>
                <div className="mb-3">
                    <label className={labelCls}>{__('Quantity to return')}</label>
                    <input type="number" step="0.0001" min="0.0001" max={returnable} value={qty}
                        onChange={(e) => setQty(e.target.value)} className={fieldCls} required />
                </div>
                <div className="mb-1">
                    <label className={labelCls}>{__('Reason')}</label>
                    <input type="text" maxLength={255} value={reason}
                        onChange={(e) => setReason(e.target.value)} className={fieldCls} />
                </div>
                <ModalActions onClose={onClose} submitLabel={__('Return to stock')} disabled={processing} />
            </form>
        </ModalFrame>
    );
}

function ReclassifyModal({ workOrder, alloc, materials, onClose }) {
    const [target, setTarget] = useState('');
    const [qty, setQty] = useState('');
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    function submit(e) {
        e.preventDefault();
        if (processing) return;
        setProcessing(true);
        router.post(`/admin/work-orders/${workOrder.id}/reclassify`,
            { source_material_id: alloc.material_id, target_material_id: target, qty, reason },
            { preserveScroll: true, onSuccess: onClose, onFinish: () => setProcessing(false) });
    }

    const targets = materials.filter((m) => m.id !== alloc.material_id);

    return (
        <ModalFrame title={__('Reclassify material')}>
            <form onSubmit={submit}>
                <p className="text-xs text-om-muted mb-3">
                    {__('From')} <strong className="text-om-ink">{alloc.material_code}</strong>
                </p>
                <div className="mb-3">
                    <label className={labelCls}>{__('Target class (material)')}</label>
                    <select value={target} onChange={(e) => setTarget(e.target.value)} className={fieldCls} required>
                        <option value="">{__('Select a material')}</option>
                        {targets.map((m) => (
                            <option key={m.id} value={m.id}>{m.code} · {m.name}</option>
                        ))}
                    </select>
                </div>
                <div className="mb-3">
                    <label className={labelCls}>{__('Quantity')}</label>
                    <input type="number" step="0.0001" min="0.0001" value={qty}
                        onChange={(e) => setQty(e.target.value)} className={fieldCls} required />
                </div>
                <div className="mb-1">
                    <label className={labelCls}>{__('Reason')}</label>
                    <input type="text" maxLength={255} value={reason}
                        onChange={(e) => setReason(e.target.value)} className={fieldCls} />
                </div>
                <ModalActions onClose={onClose} submitLabel={__('Reclassify')} disabled={!target || processing} />
            </form>
        </ModalFrame>
    );
}
