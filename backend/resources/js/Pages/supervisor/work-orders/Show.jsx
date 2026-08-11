// Geist White restyle: light-only v1 — om-* tokens + @openmes/ui (status transitions, modal post and batch logic untouched).
import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import { apiCall } from '../../../lib/http';
import { __, formatDate, formatNumber } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

// App status → Geist White pill state (labels stay the raw app statuses).
const WO_PILL_STATUS = {
    PENDING: 'pending',
    ACCEPTED: 'pending',
    IN_PROGRESS: 'running',
    PAUSED: 'downtime',
    CHANGE_HOLD: 'downtime',
    BLOCKED: 'blocked',
    DONE: 'done',
    REJECTED: 'blocked',
    CANCELLED: 'done',
};

const BATCH_PILL_STATUS = {
    PENDING: 'pending',
    IN_PROGRESS: 'running',
    DONE: 'done',
};

const STEP_STATUS_STYLES = {
    DONE: 'bg-om-running-bg text-om-running',
    IN_PROGRESS: 'bg-om-selected text-om-accent',
};

const ISSUE_PILL_STATUS = {
    OPEN: 'blocked',
    ACKNOWLEDGED: 'downtime',
    RESOLVED: 'running',
};

// Ghost-button classes for Inertia <Link>s (mirrors @openmes/ui Button ghost).
const LINK_GHOST =
    'inline-flex items-center justify-center gap-2 text-[13px] font-semibold rounded-om-sm border border-om-line px-4 py-[9px] text-om-ink hover:bg-om-chip transition-colors';

const MACHINE_PILL_STATUS = {
    RUNNING: 'running',
    IDLE: 'pending',
    STOPPED: 'downtime',
    FAULT: 'blocked',
    SETUP: 'pending',
    WAITING: 'downtime',
    CLEANING: 'pending',
    MAINTENANCE: 'downtime',
    UNKNOWN: 'pending',
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

function fmtDateTime(d) {
    if (!d) return null;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return formatDate(dt, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}



function BatchRow({ batch, processSnapshot, workstationTypeNames = {}, onAssign }) {
    const [open, setOpen] = useState(batch.is_first ?? false);

    // Build step-number → estimated_duration_minutes map from process_snapshot
    const snapshotSteps = {};
    if (processSnapshot && Array.isArray(processSnapshot.steps)) {
        processSnapshot.steps.forEach((s) => {
            snapshotSteps[s.step_number] = s.estimated_duration_minutes ?? null;
        });
    }

    return (
        <div className="border border-om-line rounded-om p-3">
            <div
                className="flex items-center justify-between cursor-pointer"
                onClick={() => setOpen((o) => !o)}
            >
                <div className="flex items-center gap-3">
                    <span className="font-semibold text-om-ink">Batch #{batch.batch_number}</span>
                    <StatusPill
                        status={BATCH_PILL_STATUS[batch.status] ?? 'pending'}
                        label={batch.status.replace('_', ' ')}
                    />
                    <span className="font-mono text-[12px] text-om-muted">
                        {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                    </span>
                </div>
                <svg
                    className={`w-4 h-4 text-om-faint transition-transform ${open ? 'rotate-180' : ''}`}
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            {open && (
                <div className="mt-3 space-y-1">
                    {(batch.steps ?? []).map((step) => {
                        const stepStyle = STEP_STATUS_STYLES[step.status] ?? 'bg-om-chip text-om-faint';
                        const estimated = snapshotSteps[step.step_number] ?? null;
                        const overTime = estimated && step.duration_minutes != null && step.duration_minutes > estimated;
                        return (
                            <div key={step.id} className="flex items-center gap-3 py-1.5 px-2 rounded-om-sm text-sm hover:bg-om-bg">
                                <span className={`w-5 h-5 rounded-full flex items-center justify-center font-mono text-xs flex-shrink-0 ${stepStyle}`}>
                                    {step.step_number}
                                </span>
                                <span className="flex-1 text-om-ink">{step.name}</span>
                                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{step.status.replace('_', ' ')}</span>
                                {step.duration_minutes != null ? (
                                    <span className={`font-mono text-xs font-medium ${overTime ? 'text-om-blocked' : 'text-om-running'}`}>
                                        {step.duration_minutes}min{estimated ? ` / est. ${estimated}min` : ''}
                                    </span>
                                ) : estimated ? (
                                    <span className="font-mono text-xs text-om-faint">est. {estimated}min</span>
                                ) : null}
                                {['PENDING', 'READY'].includes(step.status) && step.workstation_type_id && !step.workstation_id && (
                                    <button
                                        type="button"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onAssign?.(step);
                                        }}
                                        className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-accent hover:underline flex-shrink-0"
                                        title={workstationTypeNames[step.workstation_type_id] ?? ''}
                                    >
                                        {__('Assign')}
                                    </button>
                                )}
                            </div>
                        );
                    })}
                    {batch.started_at && (
                        <p className="text-xs text-om-faint pt-1">
                            Started: {fmtDateTime(batch.started_at)}
                            {batch.completed_at ? ` · Completed: ${fmtDateTime(batch.completed_at)}` : ''}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

function DoneModal({ workOrder, onClose }) {
    const [qty, setQty] = useState(String(workOrder.planned_qty ?? ''));

    function handleSubmit(e) {
        e.preventDefault();
        router.post(`/supervisor/work-orders/${workOrder.id}/complete`, { produced_qty: qty }, { preserveScroll: true });
        onClose();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-om-card border border-om-line rounded-om shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-[16px] font-semibold text-om-ink mb-4">{__('Complete Work Order')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    {__('Enter the produced quantity for')} <strong className="font-mono text-om-ink">{workOrder.order_no}</strong>.
                </p>
                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5">{__('Produced Quantity')}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            max={workOrder.planned_qty * 2}
                            value={qty}
                            onChange={(e) => setQty(e.target.value)}
                            className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 font-mono text-sm text-om-ink focus:outline-none focus:border-om-accent"
                            required
                        />
                        <p className="text-xs text-om-faint mt-1.5">{__('Planned:')} <span className="font-mono">{fmtQty(workOrder.planned_qty)}</span></p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="accent">
                            Mark as Done
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function OrderMachinesPanel({ machines }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <div className="flex justify-between items-center mb-3">
                <h3 className="text-[14px] font-semibold text-om-ink">{__('Machines')}</h3>
                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Live')}</span>
            </div>
            {machines.length === 0 ? (
                <p className="text-sm text-om-faint text-center py-3">{__('No assigned machines.')}</p>
            ) : (
                <div className="space-y-3">
                    {machines.map((machine) => {
                        const state = machine.current_state ?? 'UNKNOWN';
                        const pillStatus = MACHINE_PILL_STATUS[state] ?? MACHINE_PILL_STATUS.UNKNOWN;

                        return (
                            <div key={machine.id} className="rounded-om-sm bg-om-panel p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-medium text-om-ink truncate">{machine.name}</p>
                                        <p className="font-mono text-xs text-om-faint truncate">{machine.code}</p>
                                    </div>
                                    <StatusPill status={pillStatus} label={state} />
                                </div>
                                <div className="mt-2 flex flex-wrap gap-2 text-xs text-om-muted">
                                    <span>{__('Steps:')} {machine.steps_count}</span>
                                    {machine.active_steps_count > 0 && (
                                        <span className="text-om-accent">{__('Active:')} {machine.active_steps_count}</span>
                                    )}
                                    {machine.line_name && <span>{machine.line_name}</span>}
                                </div>
                                {machine.state_started_at && (
                                    <p className="mt-1 text-xs text-om-faint">
                                        {__('Since')} {fmtDateTime(machine.state_started_at)}
                                        {machine.state_source ? ` · ${__(machine.state_source)}` : ''}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function SupervisorWorkOrderShow() {
    const { workOrder, workstations = [], workstationTypeNames = {} } = usePage().props;
    const [assignStep, setAssignStep] = useState(null); // batch step being assigned a workstation (#52)
    const [showDoneModal, setShowDoneModal] = useState(false);

    const post = (verb) => router.post(`/supervisor/work-orders/${workOrder.id}/${verb}`, {}, { preserveScroll: true });

    useEffect(() => {
        const timer = setInterval(() => {
            router.reload({ only: ['workOrder'], preserveScroll: true, preserveState: true });
        }, 5000);

        return () => clearInterval(timer);
    }, [workOrder.id]);

    const status = workOrder.status;
    const isTerminal = TERMINAL.includes(status);

    const pct = workOrder.planned_qty > 0
        ? Math.min((workOrder.produced_qty / workOrder.planned_qty) * 100, 100)
        : 0;

    const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < new Date() && status !== 'DONE';

    return (
        <>
            <Head title={__('Work Order :no', { no: workOrder.order_no })} />

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-mono text-[26px] font-medium tracking-[-0.02em] text-om-ink">{workOrder.order_no}</h1>
                            <StatusPill status={WO_PILL_STATUS[status] ?? 'pending'} label={status} />
                        </div>
                        <p className="text-om-muted mt-1">
                            {__('Created')} {timeAgo(workOrder.created_at)}
                            {workOrder.product_type_name ? ` · ${workOrder.product_type_name}` : ''}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {status === 'PENDING' && (
                            <>
                                <Button variant="primary" onClick={() => post('accept')}>
                                    Accept
                                </Button>
                                <Button
                                    variant="danger"
                                    onClick={() => { if (confirm(__('Reject this work order?'))) post('reject'); }}
                                >
                                    Reject
                                </Button>
                            </>
                        )}
                        {status === 'ACCEPTED' && (
                            <Button
                                variant="danger"
                                onClick={() => { if (confirm(__('Reject this work order?'))) post('reject'); }}
                            >
                                Reject
                            </Button>
                        )}
                        {status === 'IN_PROGRESS' && (
                            <>
                                <Button variant="secondary" onClick={() => post('pause')}>
                                    Pause
                                </Button>
                                <Button variant="accent" onClick={() => setShowDoneModal(true)}>
                                    Done
                                </Button>
                            </>
                        )}
                        {/* CHANGE_HOLD resumes here too; the backend refuses it until an
                            approved change has been applied and answers with the reason (#182). */}
                        {['PAUSED', 'CHANGE_HOLD'].includes(status) && (
                            <Button variant="primary" onClick={() => post('resume')}>
                                Resume
                            </Button>
                        )}

                        {isTerminal ? (
                            <Button
                                variant="primary"
                                onClick={() => { if (confirm(__('Reopen this work order?'))) post('reopen'); }}
                            >
                                Reopen
                            </Button>
                        ) : (
                            <>
                                <Link
                                    href={`/supervisor/work-orders/${workOrder.id}/edit`}
                                    className={LINK_GHOST}
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={() => { if (confirm(__('Cancel this work order?'))) post('cancel'); }}
                                    className="inline-flex items-center justify-center gap-2 text-[13px] font-semibold rounded-om-sm border border-om-line px-4 py-[9px] text-om-accent hover:bg-om-chip transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                            </>
                        )}

                        <Link
                            href="/supervisor/work-orders"
                            className={LINK_GHOST}
                        >
                            ← Back
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main */}
                    <div className="lg:col-span-2 space-y-6">

                        {/* Details */}
                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <h2 className="text-[14px] font-semibold text-om-ink mb-4">{__('Details')}</h2>
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Order Number')}</p>
                                    <p className="font-mono font-medium text-om-ink">{workOrder.order_no}</p>
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Line')}</p>
                                    <p className="font-medium text-om-ink">{workOrder.line_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Product Type')}</p>
                                    <p className="font-medium text-om-ink">{workOrder.product_type_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Planned Qty')}</p>
                                    <p className="font-mono font-medium text-om-ink">{fmtQty(workOrder.planned_qty)}</p>
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Produced Qty')}</p>
                                    <p className="font-mono font-medium text-om-ink">{fmtQty(workOrder.produced_qty)}</p>
                                </div>
                                <div>
                                    <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Priority')}</p>
                                    <p className="font-medium text-om-ink">{workOrder.priority ?? '—'}</p>
                                </div>
                                {workOrder.due_date && (
                                    <div>
                                        <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Due Date')}</p>
                                        <p className={`font-medium ${isDuePast ? 'text-om-blocked' : 'text-om-ink'}`}>
                                            {fmtDate(workOrder.due_date)}
                                        </p>
                                    </div>
                                )}
                                {workOrder.description && (
                                    <div className="col-span-2 md:col-span-3">
                                        <p className="mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Description')}</p>
                                        <p className="font-medium text-om-ink">{workOrder.description}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Batches */}
                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <h2 className="text-[14px] font-semibold text-om-ink mb-4">
                                {__('Batches')}{' '}
                                <span className="font-mono text-[12px] font-normal text-om-faint">({workOrder.batches.length})</span>
                            </h2>
                            {workOrder.batches.length === 0 ? (
                                <p className="text-sm text-om-faint py-4 text-center">{__('No batches yet.')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {workOrder.batches.map((batch, i) => (
                                        <BatchRow
                                            key={batch.id}
                                            batch={{ ...batch, is_first: i === 0 }}
                                            processSnapshot={workOrder.process_snapshot}
                                            workstationTypeNames={workstationTypeNames}
                                            onAssign={setAssignStep}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">

                        {/* Progress */}
                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <h3 className="text-[14px] font-semibold text-om-ink mb-3">{__('Progress')}</h3>
                            <div className="mb-3">
                                <div className="flex justify-between items-baseline text-sm mb-1.5">
                                    <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Completion')}</span>
                                    <span className="font-mono text-[12px] text-om-ink">{pct.toFixed(1)}%</span>
                                </div>
                                <div className="w-full bg-om-chip rounded-[20px] h-[7px] overflow-hidden">
                                    <div
                                        className={`h-[7px] rounded-[20px] ${pct >= 100 ? 'bg-om-running' : 'bg-om-accent'}`}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Planned:')}</span>
                                    <span className="font-mono font-medium text-om-ink">{fmtQty(workOrder.planned_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Produced:')}</span>
                                    <span className="font-mono font-medium text-om-ink">{fmtQty(workOrder.produced_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Batches:')}</span>
                                    <span className="font-mono font-medium text-om-ink">{workOrder.batches.length}</span>
                                </div>
                            </div>
                        </div>

                        <OrderMachinesPanel machines={workOrder.machines ?? []} />

                        {/* Issues */}
                        <div className="bg-om-card border border-om-line rounded-om p-5">
                            <div className="flex justify-between items-center mb-3">
                                <h3 className="text-[14px] font-semibold text-om-ink">{__('Issues')}</h3>
                                <Link
                                    href="/supervisor/issues"
                                    className="text-xs text-om-accent hover:underline"
                                >
                                    {__('Manage →')}
                                </Link>
                            </div>
                            {workOrder.issues.length === 0 ? (
                                <p className="text-sm text-om-faint text-center py-3">{__('No issues.')}</p>
                            ) : (
                                <div className="space-y-2">
                                    {workOrder.issues.map((issue) => {
                                        const isBlocking = ['OPEN', 'ACKNOWLEDGED'].includes(issue.status) && issue.is_blocking;
                                        return (
                                            <div
                                                key={issue.id}
                                                className={`p-2.5 rounded-om-sm text-xs ${isBlocking ? 'bg-om-blocked-bg' : 'bg-om-panel'}`}
                                            >
                                                <div className="flex justify-between items-center gap-2">
                                                    <span className="font-medium text-om-ink">{issue.issue_type_name}</span>
                                                    <StatusPill
                                                        status={ISSUE_PILL_STATUS[issue.status] ?? 'pending'}
                                                        label={issue.status}
                                                    />
                                                </div>
                                                <p className="text-om-muted mt-1 truncate">{issue.title}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {showDoneModal && (
                <DoneModal workOrder={workOrder} onClose={() => setShowDoneModal(false)} />
            )}

            {assignStep && (
                <AssignWorkstationModal
                    step={assignStep}
                    workstations={workstations}
                    typeNames={workstationTypeNames}
                    onClose={() => setAssignStep(null)}
                />
            )}
        </>
    );
}

/**
 * Pool dispatch (#52): assign a specific workstation to a pending step that
 * carries only an Equipment Class. Lists active workstations of the required
 * type; posts the assign endpoint and reloads. Mirrors the DoneModal overlay.
 */
function AssignWorkstationModal({ step, workstations, typeNames, onClose }) {
    const options = (workstations ?? []).filter(
        (w) => String(w.workstation_type_id) === String(step.workstation_type_id),
    );
    const [workstationId, setWorkstationId] = useState(options[0] ? String(options[0].id) : '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    async function submit() {
        if (!workstationId) return;
        setSaving(true);
        setError(null);
        try {
            const res = await apiCall(`/api/v1/batch-steps/${step.id}/assign`, 'POST', {
                workstation_id: Number(workstationId),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                setError(body.message ?? __('The workstation could not be assigned.'));
                return;
            }
            onClose();
            router.reload({ only: ['workOrder'] });
        } catch {
            setError(__('The workstation could not be assigned.'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative w-full max-w-md rounded-om border border-om-line bg-om-card p-6" onClick={(e) => e.stopPropagation()}>
                    <h3 className="text-[15px] font-semibold text-om-ink mb-1">{__('Assign workstation')}</h3>
                    <p className="text-xs text-om-muted mb-4">
                        {step.name} · {typeNames?.[step.workstation_type_id] ?? __('required type')}
                    </p>
                    {options.length === 0 ? (
                        <p className="text-sm text-om-blocked mb-4">{__('No active workstation of the required type is available.')}</p>
                    ) : (
                        <select
                            value={workstationId}
                            onChange={(e) => setWorkstationId(e.target.value)}
                            className="w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-sm mb-4"
                        >
                            {options.map((w) => <option key={w.id} value={String(w.id)}>{w.name}</option>)}
                        </select>
                    )}
                    {error && <p className="text-om-blocked text-xs mb-3">{error}</p>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={onClose}>{__('Cancel')}</Button>
                        <Button variant="primary" disabled={!workstationId || saving} onClick={submit}>
                            {saving ? '…' : __('Assign')}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

SupervisorWorkOrderShow.layout = (page) => <AppLayout>{page}</AppLayout>;
