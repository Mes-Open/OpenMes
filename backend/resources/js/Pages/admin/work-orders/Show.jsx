import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Breadcrumbs, Button, Icon, ProgressBar, StatusBadge, StatusPill, Stepper, useToast } from '@openmes/ui';

import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
import PageTitle from '../../../components/PageTitle';
import useConfirm from '../../../components/useConfirm';
import usePrompt from '../../../components/usePrompt';
import DueCountdown from '../../../components/DueCountdown';
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
    const { workOrder, customFields = [] } = usePage().props;
    const { confirm, dialog } = useConfirm();
    const { prompt, dialog: promptDialog } = usePrompt();

    const post = (verb, data = {}) =>
        router.post(`/admin/work-orders/${workOrder.id}/${verb}`, data, { preserveScroll: true });

    const status = workOrder.status;
    const isTerminal = TERMINAL.includes(status);
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
                />

                <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1.55fr_1fr]">
                    {/* LEFT — what the order is, and what its batches are doing. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <Details workOrder={workOrder} isDuePast={isDuePast} isTerminal={isTerminal} />
                        <CustomFieldsDisplay definitions={customFields} values={workOrder.custom_fields ?? {}} />
                        <Batches workOrder={workOrder} />
                    </div>

                    {/* RIGHT — what it adds up to. */}
                    <div className="flex min-w-0 flex-col gap-4">
                        <Progress workOrder={workOrder} pct={pct} />
                        <Problems workOrder={workOrder} />
                        <Activity entries={workOrder.activity ?? []} />
                    </div>
                </div>
            </div>

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
function Header({ workOrder, status, isTerminal, isDuePast, post, confirm, promptComplete }) {
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
                        {/* `Complete`, not `Done`: the verb, not the state the
                            status badge already shows. */}
                        <Button variant="primary" onClick={promptComplete}>{__('Complete')}</Button>
                    </>
                )}
                {status === 'PAUSED' && <Button variant="primary" onClick={() => post('resume')}>{__('Resume')}</Button>}
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
                <Row label={__('Batches')} value={(workOrder.batches ?? []).length} last />
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
