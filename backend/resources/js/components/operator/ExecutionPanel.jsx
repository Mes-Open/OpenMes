import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Icon } from '@openmes/ui';
import LabelPrintMenu from '../LabelPrintMenu';
import { ConfirmTimesModal, LotPickModal } from './StepModals';
import { apiGet } from '../../lib/http';
import { __, formatNumber } from '../../lib/i18n';
import { batchLabel, dueInfo, fmtQty, monoLabelCls, statusMeta } from './operatorUi';

/**
 * The execution panel beside the operator's list (design ref: OpenMES
 * Operator.dc.html, "execution panel"): the selected order's figures, its
 * process steps, and the step at hand — instructions, media, read-confirm,
 * checklist — with the one action that moves it on.
 *
 * Reads GET /operator/work-order/{id}/panel and re-reads after every Inertia
 * visit (a write here redirects back, a live-sync reload refreshes the list),
 * so it never shows a state the server no longer holds. The writes are the
 * order page's own routes; anything the panel has no form for (documents,
 * typed outputs, quantity logs, batches) links to the order page on that step.
 *
 * `quantityAction` is what the footer offers for an order counted by quantity
 * rather than by steps (the page decides: start it, add produced pieces…).
 */
export default function ExecutionPanel({ workOrderId, fallback = null, labelTemplates = [], onReportIssue, quantityAction = null, mobileOpen = false, onClose }) {
    const { selectedWorkstation } = usePage().props;
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [reloadKey, setReloadKey] = useState(0);
    const [viewStepId, setViewStepId] = useState(null);
    const [inflight, setInflight] = useState(null);
    const [actionError, setActionError] = useState(null);
    const [pickModal, setPickModal] = useState(null);
    const [timesModal, setTimesModal] = useState(null);
    const [zoom, setZoom] = useState(null);

    // Every finished visit may have changed the order: read it again.
    useEffect(() => router.on('success', () => setReloadKey((k) => k + 1)), []);

    useEffect(() => {
        setViewStepId(null);
        setActionError(null);
        if (!workOrderId) { setData(null); return undefined; }
        setData((prev) => (prev?.work_order?.id === workOrderId ? prev : null));
    }, [workOrderId]);

    useEffect(() => {
        if (!workOrderId) return undefined;
        let stale = false;
        apiGet(`/operator/work-order/${workOrderId}/panel`)
            .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
            .then((json) => { if (!stale) { setData(json); setError(null); } })
            .catch(() => { if (!stale) setError(__('Could not load this work order.')); });
        return () => { stale = true; };
    }, [workOrderId, reloadKey]);

    const steps = data?.steps ?? [];
    const wo = data?.work_order ?? null;
    const currentStep = useMemo(() => pickCurrentStep(steps, selectedWorkstation?.id), [steps, selectedWorkstation?.id]);
    const viewed = steps.find((s) => s.id === viewStepId) ?? currentStep;

    const post = useCallback((url, payload = {}, key = url, opts = {}) => {
        setInflight(key);
        setActionError(null);
        router.post(url, payload, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setActionError(Object.values(errors).join(' ')),
            onSuccess: (page) => { if (page.props.flash?.error) setActionError(page.props.flash.error); },
            onFinish: () => setInflight(null),
            ...opts,
        });
    }, []);

    // Starting asks the server which lots need picking first; with lots to
    // pick the operator confirms them, otherwise the step starts straight away.
    const startStep = async (step) => {
        setInflight(`start:${step.id}`);
        setActionError(null);
        try {
            const res = await apiGet(`/operator/batch-step/${step.id}/pick-preview`);
            if (res.ok) {
                const json = await res.json();
                if (json.materials?.length > 0) { setInflight(null); setPickModal({ step, materials: json.materials }); return; }
            }
        } catch { /* fall through: the server still picks lots and reports a real error */ }
        post(`/operator/batch-step/${step.id}/start`, {}, `start:${step.id}`);
    };

    const completeStep = (step) => {
        if (step.asks_actual_times) { setTimesModal({ step }); return; }
        post(`/operator/batch-step/${step.id}/complete`, {}, `complete:${step.id}`);
    };

    if (!workOrderId) {
        return (
            <PanelFrame mobileOpen={mobileOpen} onClose={onClose}>
                <div className="flex-1 flex items-center justify-center p-8 text-center text-[13px] text-om-faint">
                    {__('Select a work order to see its steps.')}
                </div>
            </PanelFrame>
        );
    }

    // Header figures: the fresh read when there is one, the list's row until then.
    const head = wo ?? (fallback ? {
        id: fallback.id, order_no: fallback.order_no, status: fallback.status, product: fallback.product_type?.name,
        planned_qty: fallback.planned_qty, produced_qty: fallback.produced_qty, due_date: fallback.due_date,
        uses_step_ledger: fallback.uses_step_ledger,
    } : null);
    const st = statusMeta(head?.status);
    const planned = parseFloat(head?.planned_qty) || 0;
    const produced = parseFloat(head?.produced_qty) || 0;
    const due = dueInfo(head?.due_date, head?.status);
    const orderUrl = (stepId) => `/operator/work-order/${workOrderId}${stepId ? `?step=${stepId}` : ''}`;

    // A finished step being looked at moves nothing on: act on the step at hand.
    const target = viewed && !['DONE', 'SKIPPED'].includes(viewed.status) ? viewed : currentStep;
    const action = footerAction({ wo: head, data, viewed: target, inflight, quantityAction, startStep, completeStep, orderUrl });

    return (
        <PanelFrame mobileOpen={mobileOpen} onClose={onClose}>
            {/* ── header ── */}
            <div className="pl-5 pr-12 xl:pr-5 pt-[18px] pb-3.5 border-b border-om-line2">
                <div className="flex items-center justify-between gap-2 mb-1.5">
                    <Link href={orderUrl()} className="min-w-0 inline-flex items-center gap-1 font-mono text-[11.5px] text-om-muted hover:text-om-accent" title={__('Open work order')}>
                        <span className="truncate">{head?.order_no ?? '…'}</span><Icon name="arrow-up-right" size={13} className="shrink-0" />
                    </Link>
                    <div className="flex items-center gap-2 shrink-0">
                        {labelTemplates.some((t) => t.type === 'work_order') && (
                            <LabelPrintMenu kind="work-order" id={workOrderId} templates={labelTemplates} label={__('Label')} />
                        )}
                        <span className={`font-mono text-[9px] tracking-[0.05em] uppercase rounded-full px-[9px] py-[3px] ${st.pill}`}>{st.label}</span>
                    </div>
                </div>
                <div className="text-[19px] font-semibold tracking-[-0.015em] text-om-ink">{head?.product ?? '—'}</div>
                <div className="font-mono text-[10px] text-om-faint mt-[3px]">
                    {data?.batch ? batchLabel(data.batch) : (head?.uses_step_ledger ? __('No batch yet') : __('Counted by quantity'))}
                    {wo?.batch_count > 1 ? ` · ${__(':count batches', { count: wo.batch_count })}` : ''}
                </div>
                <div className="flex border border-om-line2 rounded-[10px] overflow-hidden mt-3.5">
                    <Figure label={__('Made')}>
                        {fmtQty(produced)}<span className="text-[12px] text-om-faint"> / {fmtQty(planned)}</span>
                    </Figure>
                    <Figure label={__('Remain')} cls="text-om-accent">{fmtQty(Math.max(0, planned - produced))}</Figure>
                    <Figure label={__('Due')} cls={due.hot ? 'text-om-blocked' : 'text-om-ink'} last>{due.label}</Figure>
                </div>
            </div>

            {/* ── body ── */}
            <div className="flex-1 min-h-0 overflow-auto px-5 py-4">
                {error && <p role="alert" className="text-[12.5px] text-om-blocked mb-3">{error}</p>}
                {!data && !error && <p className="text-[12.5px] text-om-faint">{__('Loading…')}</p>}

                {data && steps.length === 0 && (
                    <div className="bg-om-bg border border-om-line2 rounded-xl px-[15px] py-3.5">
                        <div className="text-[14px] font-semibold text-om-ink mb-1">
                            {wo.uses_step_ledger ? __('No batch yet') : __('Counted by quantity')}
                        </div>
                        <p className="text-[12.5px] text-om-muted leading-[1.55] m-0">
                            {wo.uses_step_ledger
                                ? __('Create the first batch on the order page to start its process steps.')
                                : __('This order has no process steps to run here — log the pieces made in the list.')}
                        </p>
                        {wo.description && <p className="text-[12.5px] text-om-ink leading-[1.55] mt-3 mb-0 whitespace-pre-line">{wo.description}</p>}
                    </div>
                )}

                {steps.length > 0 && (
                    <>
                        <div className={`${monoLabelCls} mb-3`}>{__('Process steps')}</div>
                        <ol className="flex flex-col mb-4 list-none p-0 m-0">
                            {steps.map((s, i) => (
                                <StepRow
                                    key={s.id}
                                    step={s}
                                    last={i === steps.length - 1}
                                    current={currentStep?.id === s.id}
                                    viewing={viewed?.id === s.id}
                                    blocked={head?.status === 'BLOCKED'}
                                    onSelect={() => setViewStepId(s.id)}
                                />
                            ))}
                        </ol>
                        {viewed && (
                            <StepBox
                                step={viewed}
                                inflight={inflight}
                                transfer={data.flow_mode === 'transfer'}
                                orderUrl={orderUrl}
                                onZoom={setZoom}
                                onConfirmRead={() => post(`/operator/batch-step/${viewed.id}/confirm-instructions`, {}, `confirm:${viewed.id}`)}
                                onToggle={(item) => post(`/operator/batch-step/${viewed.id}/checklist/${item.id}/toggle`, {}, `check:${viewed.id}:${item.id}`)}
                            />
                        )}
                    </>
                )}
            </div>

            {/* ── footer ── */}
            <div className="shrink-0 border-t border-om-line2 px-5 py-3.5">
                {(actionError || action.hint) && (
                    <p role={actionError ? 'alert' : 'status'} className={`text-[12px] mb-2.5 mt-0 ${actionError ? 'text-om-blocked' : 'text-om-muted'}`}>
                        {actionError || action.hint}
                    </p>
                )}
                <div className="flex gap-[9px]">
                    {action.href ? (
                        <Link href={action.href} title={action.label} className={primaryCls(true)}>{action.label}</Link>
                    ) : (
                        <button type="button" title={action.label} disabled={!action.onClick} onClick={action.onClick} className={primaryCls(!!action.onClick)}>
                            {action.label}
                        </button>
                    )}
                    {onReportIssue && (
                        <button
                            type="button"
                            onClick={() => onReportIssue({ id: workOrderId, order_no: head?.order_no, product: head?.product })}
                            className="text-[13.5px] font-semibold text-om-blocked bg-om-blocked-bg rounded-[10px] px-4 py-[13px] cursor-pointer hover:brightness-95 whitespace-nowrap"
                        >
                            {__('Report issue')}
                        </button>
                    )}
                </div>
            </div>

            {pickModal && <LotPickModal step={pickModal.step} materials={pickModal.materials} onClose={() => setPickModal(null)} />}
            {timesModal && <ConfirmTimesModal step={timesModal.step} onClose={() => setTimesModal(null)} />}
            {zoom && (
                <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-6" onClick={() => setZoom(null)}>
                    <figure className="max-w-3xl max-h-full m-0" onClick={(e) => e.stopPropagation()}>
                        <img src={zoom.url} alt={zoom.caption || zoom.title || ''} className="max-w-full max-h-[80vh] rounded-om shadow-2xl" />
                        {(zoom.caption || zoom.title) && <figcaption className="text-white/90 text-sm mt-3 text-center">{zoom.caption || zoom.title}</figcaption>}
                    </figure>
                </div>
            )}
        </PanelFrame>
    );
}

/**
 * Docked beside the list from xl up; below that a drawer over the page,
 * opened when the operator picks an order.
 */
function PanelFrame({ mobileOpen, onClose, children }) {
    return (
        <>
            {mobileOpen && <div className="xl:hidden fixed inset-0 z-30 bg-[rgba(10,9,8,0.42)]" onClick={onClose} />}
            <aside
                aria-label={__('Execution panel')}
                data-testid="execution-panel"
                className={`${mobileOpen ? 'fixed inset-y-0 right-0 z-40 flex w-full max-w-[420px] shadow-2xl' : 'hidden'} xl:static xl:z-auto xl:shadow-none xl:flex xl:w-[420px] shrink-0 flex-col min-h-0 bg-om-card border-l border-om-line`}
            >
                {mobileOpen && onClose && (
                    <button type="button" onClick={onClose} aria-label={__('Close')} className="xl:hidden absolute top-2 right-2 z-10 w-9 h-9 rounded-full text-om-faint hover:text-om-ink hover:bg-om-chip flex items-center justify-center cursor-pointer">
                        <Icon name="x" size={20} />
                    </button>
                )}
                {children}
            </aside>
        </>
    );
}

function Figure({ label, cls = 'text-om-ink', last = false, children }) {
    return (
        <div className={`flex-1 px-3 py-2.5 ${last ? '' : 'border-r border-om-line2'}`}>
            <div className="font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint mb-1">{label}</div>
            <div className={`font-mono text-[18px] font-medium ${cls}`}>{children}</div>
        </div>
    );
}

const primaryCls = (on) => `flex-1 min-w-0 truncate text-center text-[13.5px] font-semibold rounded-[10px] py-[13px] px-3 transition-colors ${
    on ? 'bg-om-accent text-white cursor-pointer hover:brightness-95' : 'bg-om-chip text-om-faint cursor-not-allowed'
}`;

/** The step the operator is on: running here, else running, else next to start here, else next. */
export function pickCurrentStep(steps, workstationId) {
    const open = steps.filter((s) => !['DONE', 'SKIPPED'].includes(s.status));
    const here = (s) => !workstationId || s.workstation?.id === workstationId;
    return open.find((s) => s.status === 'IN_PROGRESS' && here(s))
        ?? open.find((s) => s.status === 'IN_PROGRESS')
        ?? open.find((s) => ['READY', 'PENDING'].includes(s.status) && s.prerequisites_met && here(s))
        ?? open.find((s) => s.prerequisites_met)
        ?? open[0]
        ?? null;
}

function stepMeta(step) {
    return [
        step.workstation?.name,
        step.estimated_duration_minutes ? __(':min min', { min: formatNumber(step.estimated_duration_minutes) }) : null,
    ].filter(Boolean).join(' · ');
}

function StepRow({ step, last, current, viewing, blocked, onSelect }) {
    const done = step.status === 'DONE';
    const skipped = step.status === 'SKIPPED';
    const running = step.status === 'IN_PROGRESS';
    // In the transfer flow several steps run at once: the ones after the
    // current step that already have pieces read as running, not as not-yet.
    const dotCls = done
        ? 'bg-om-running text-white'
        : current
            ? `${blocked ? 'bg-om-blocked shadow-[0_0_0_4px_var(--om-blocked-bg)]' : 'bg-om-accent shadow-[0_0_0_4px_var(--om-accent-bg)]'} text-white`
            : running
                ? 'bg-om-accent-bg border-[1.5px] border-om-accent text-om-accent'
                : 'bg-om-card border-[1.5px] border-om-faintest text-om-faint';
    const meta = [stepMeta(step), running ? __('In progress') : null, skipped ? __('Skipped') : null].filter(Boolean).join(' · ');
    return (
        <li>
            <button type="button" onClick={onSelect} aria-current={current ? 'step' : undefined} className={`w-full flex gap-3 text-left cursor-pointer rounded-lg ${viewing ? 'bg-om-bg' : 'hover:bg-om-bg/60'}`}>
                <div className="flex flex-col items-center w-6 shrink-0">
                    <span className={`w-6 h-6 rounded-full shrink-0 flex items-center justify-center font-mono text-[10.5px] font-bold ${dotCls}`}>
                        {done ? <Icon name="check" size={13} stroke={3} /> : step.step_number}
                    </span>
                    {!last && <span className={`flex-1 w-0.5 min-h-3 ${done ? 'bg-om-running' : 'bg-om-line'}`} />}
                </div>
                <div className="flex-1 min-w-0 pt-0.5 pb-3.5 pr-2">
                    <div className="flex items-center gap-[7px] flex-wrap">
                        <span className={`text-[13px] ${current ? 'font-semibold' : 'font-medium'} ${done || current || running ? 'text-om-ink' : 'text-om-faint'} ${skipped ? 'line-through' : ''}`}>{step.name}</span>
                        {step.requires_confirmation && (
                            <span className="font-mono text-[7.5px] text-om-blocked bg-om-blocked-bg rounded-full px-1.5 py-px">{__('READ-CONFIRM')}</span>
                        )}
                    </div>
                    {meta && <div className="font-mono text-[9.5px] text-om-faint mt-px">{meta}</div>}
                </div>
            </button>
        </li>
    );
}

function StepBox({ step, inflight, transfer, orderUrl, onZoom, onConfirmRead, onToggle }) {
    const open = !['DONE', 'SKIPPED'].includes(step.status);
    const items = step.checklist ?? [];
    const ticked = items.filter((c) => c.done).length;
    const tiles = [
        step.photo ? { ...step.photo, media_type: 'image', title: step.photo.caption } : null,
        ...(step.media ?? []),
    ].filter(Boolean);
    const blocker = step.status === 'IN_PROGRESS' ? (step.completion_blocker || (transfer ? step.production_blocker : null)) : step.production_blocker;
    const needsOrderPage = open && (step.blocking_documents > 0 || (step.pending_outputs ?? []).length > 0 || (transfer && step.status === 'IN_PROGRESS'));

    return (
        <div className="bg-om-bg border border-om-line2 rounded-xl px-[15px] py-3.5">
            <div className="flex items-center justify-between gap-2 mb-2">
                <span className="text-[14px] font-semibold text-om-ink">{step.step_number}. {step.name}</span>
                <span className="font-mono text-[9.5px] text-om-faint text-right">{stepMeta(step)}</span>
            </div>
            {step.instruction && (
                <div className="text-[12.5px] text-om-muted leading-[1.55] mb-3 whitespace-pre-line">{step.instruction}</div>
            )}

            {tiles.length > 0 && (
                <div className="grid grid-cols-2 gap-[7px] mb-3">
                    {tiles.map((m) => (m.media_type === 'image' ? (
                        <button key={`${m.media_type}:${m.id}:${m.url}`} type="button" onClick={() => onZoom(m)} className="relative h-[66px] rounded-lg overflow-hidden bg-om-chip cursor-zoom-in">
                            <img src={m.url} alt={m.title || ''} loading="lazy" className="absolute inset-0 w-full h-full object-cover" />
                            {m.title && <span className="absolute left-0 right-0 bottom-0 px-2 py-1 text-[9.5px] text-white bg-gradient-to-t from-black/60 to-transparent text-left truncate">{m.title}</span>}
                        </button>
                    ) : (
                        <a key={`${m.media_type}:${m.id}`} href={m.url} target="_blank" rel="noopener noreferrer" className="h-[66px] rounded-lg bg-om-line flex flex-col justify-end px-2 py-1.5 hover:bg-om-chip">
                            <span className="font-mono text-[8.5px] uppercase tracking-[0.08em] text-om-faint">{m.media_type}</span>
                            <span className="text-[10.5px] text-om-ink truncate">{m.title || m.original_name || __('Open')}</span>
                        </a>
                    )))}
                </div>
            )}

            {step.requires_confirmation && (
                <div className="flex items-center justify-between gap-3 bg-om-card border border-om-line2 rounded-lg px-[11px] py-[9px] mb-3">
                    <span className="text-[12.5px] text-om-ink">
                        {step.confirmed_at ? __('Instructions read and confirmed') : __('Confirm you have read the instructions')}
                    </span>
                    {step.confirmed_at ? (
                        <Icon name="check" size={14} stroke={3} className="text-om-running shrink-0" aria-label={__('Done')} />
                    ) : (
                        <button type="button" disabled={!open || inflight === `confirm:${step.id}`} onClick={onConfirmRead}
                                className="text-[12px] font-semibold rounded-[7px] px-3 py-1.5 bg-om-ink text-om-on-ink cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                            {__('I have read them')}
                        </button>
                    )}
                </div>
            )}

            {items.length > 0 && (
                <>
                    <div className={`${monoLabelCls} mb-2`}>{__('Checklist')} · {ticked} / {items.length}</div>
                    <div className="flex flex-col gap-1.5">
                        {items.map((c) => {
                            const busy = inflight === `check:${step.id}:${c.id}`;
                            return (
                                <button key={c.id} type="button" role="checkbox" aria-checked={c.done} disabled={!open || busy} onClick={() => onToggle(c)}
                                        className="flex items-center gap-2.5 bg-om-card border border-om-line2 rounded-lg px-[11px] py-[9px] text-left cursor-pointer disabled:cursor-not-allowed">
                                    <span className={`w-[18px] h-[18px] rounded-[5px] shrink-0 flex items-center justify-center text-white text-[11px] font-bold ${c.done ? 'bg-om-running' : 'border-2 border-om-faintest'} ${busy ? 'opacity-50' : ''}`}>
                                        {c.done && <Icon name="check" size={12} stroke={3} />}
                                    </span>
                                    <span className={`text-[12.5px] flex-1 ${c.done ? 'text-om-muted' : 'text-om-ink'}`}>{c.label}</span>
                                    {c.is_required && !c.done && <span className="font-mono text-[8.5px] uppercase tracking-[0.08em] text-om-blocked">{__('required')}</span>}
                                </button>
                            );
                        })}
                    </div>
                </>
            )}

            {blocker && <p className="text-[12px] text-om-muted mt-3 mb-0">{blocker}</p>}

            {needsOrderPage && (
                <div className="mt-3 text-[12px] text-om-muted">
                    {step.blocking_documents > 0 && <p className="m-0">{__(':count document(s) to validate before completing.', { count: step.blocking_documents })}</p>}
                    {(step.pending_outputs ?? []).length > 0 && <p className="m-0">{__('Record before completing: :outputs', { outputs: step.pending_outputs.join(', ') })}</p>}
                    <Link href={orderUrl(step.id)} className="inline-flex items-center gap-1 mt-1 font-semibold text-om-accent hover:text-om-ink">
                        {transfer && step.status === 'IN_PROGRESS' ? __('Log quantities on the order page') : __('Open this step on the order page')}
                        <Icon name="arrow-right" size={14} />
                    </Link>
                </div>
            )}
        </div>
    );
}

/** The footer's main button: what moves the order on from where it stands. */
function footerAction({ wo, data, viewed, inflight, quantityAction, startStep, completeStep, orderUrl }) {
    if (!wo) return { label: '…' };
    if (wo.status === 'BLOCKED') return { label: __('Blocked — resolve issue') };
    if (wo.status === 'DONE') return { label: __('Completed') };
    if (!data) return { label: '…' };

    if (!wo.uses_step_ledger) {
        return quantityAction ?? { label: __('Open work order'), href: orderUrl() };
    }
    if (!data.batch) return { label: __('Create batch'), href: orderUrl(), hint: __('The order page asks for the batch size and station.') };
    if (!viewed) return { label: __('All steps complete'), href: orderUrl() };

    const busy = inflight === `start:${viewed.id}` || inflight === `complete:${viewed.id}`;
    if (['READY', 'PENDING'].includes(viewed.status)) {
        if (!viewed.prerequisites_met) return { label: __('Waiting for the previous step') };
        if (viewed.production_blocker) return { label: __('Start :step', { step: viewed.name }), hint: viewed.production_blocker };
        return { label: busy ? '…' : __('Start :step', { step: viewed.name }), onClick: busy ? null : () => startStep(viewed) };
    }
    if (viewed.status === 'IN_PROGRESS') {
        const label = busy ? '…' : __('Complete :step', { step: viewed.name });
        const openRequired = (viewed.checklist ?? []).filter((c) => c.is_required && !c.done).length;
        const hint = viewed.completion_blocker
            || (viewed.requires_confirmation && !viewed.confirmed_at ? __('Confirm you have read the instructions first.') : null)
            || (openRequired ? __('Tick the :count required checklist item(s) first.', { count: openRequired }) : null)
            || (viewed.blocking_documents > 0 ? __('Validate the mandatory document(s) first.') : null)
            || ((viewed.pending_outputs ?? []).length ? __('Record the required outputs first.') : null);
        return { label, hint, onClick: hint || busy ? null : () => completeStep(viewed) };
    }
    return { label: __('All steps complete') };
}
