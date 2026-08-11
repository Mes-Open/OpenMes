import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import CustomFieldsDisplay from '../../../components/CustomFieldsDisplay';
import StopProductionModal from './StopProductionModal';
import ChangeRequestModal from './ChangeRequestModal';
import { WO_STATUS_STYLES } from './fields';
import { TIER_BADGE_STYLES, tierLabel } from '../customers/fields';
import { formatDate, formatNumber, timeAgo, __ } from '../../../lib/i18n';

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

const BATCH_STATUS_STYLES = {
    PENDING: 'bg-om-chip text-om-muted',
    IN_PROGRESS: 'bg-om-chip text-om-accent',
    DONE: 'bg-om-running-bg text-om-running',
};

const STEP_STATUS_STYLES = {
    DONE: 'bg-om-running-bg text-om-running',
    IN_PROGRESS: 'bg-om-chip text-om-accent',
};

const ISSUE_STATUS_STYLES = {
    OPEN: 'bg-om-blocked-bg text-om-blocked',
    ACKNOWLEDGED: 'bg-om-downtime-bg text-om-downtime',
    RESOLVED: 'bg-om-running-bg text-om-running',
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



function BatchRow({ batch }) {
    const [open, setOpen] = useState(batch.is_first ?? false);
    const batchStyle = BATCH_STATUS_STYLES[batch.status] ?? 'bg-om-chip text-om-faint';

    return (
        <div className="border border-om-line2 rounded-om-sm p-3">
            <div
                className="flex items-center justify-between cursor-pointer"
                onClick={() => setOpen((o) => !o)}
            >
                <div className="flex items-center gap-3">
                    <span className="font-semibold text-om-muted">Batch #{batch.batch_number}</span>
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${batchStyle}`}>
                        {__(batch.status)}
                    </span>
                    <span className="text-sm text-om-muted">
                        {fmtQty(batch.produced_qty)} / {fmtQty(batch.target_qty)}
                    </span>
                </div>
                <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                    <svg
                        className={`w-4 h-4 text-om-faint transition-transform ${open ? 'rotate-180' : ''}`}
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            {open && (
                <div className="mt-3 space-y-1">
                    {(batch.steps ?? []).map((step) => {
                        const stepStyle = STEP_STATUS_STYLES[step.status] ?? 'bg-om-chip text-om-muted';
                        const estimated = step.estimated_duration_minutes ?? null;
                        const overTime = estimated && step.duration_minutes != null && step.duration_minutes > estimated;
                        return (
                            <div key={step.id} className="flex items-center gap-3 py-1 px-2 rounded text-sm">
                                <span className={`w-5 h-5 rounded-full flex items-center justify-center text-xs flex-shrink-0 ${stepStyle}`}>
                                    {step.step_number}
                                </span>
                                <span className="flex-1 text-om-muted">{step.name}</span>
                                <span className="text-xs text-om-faint">{step.status.replace('_', ' ')}</span>
                                {step.duration_minutes != null ? (
                                    <span className={`text-xs font-medium ${overTime ? 'text-om-blocked' : 'text-om-running'}`}>
                                        {step.duration_minutes}min{estimated ? ` / est. ${estimated}min` : ''}
                                    </span>
                                ) : estimated ? (
                                    <span className="text-xs text-om-faint">est. {estimated}min</span>
                                ) : null}
                            </div>
                        );
                    })}
                    {batch.started_at && (
                        <p className="text-xs text-om-faint pt-1">
                            Started: {fmtDate(batch.started_at)}
                            {batch.completed_at ? ` · Completed: ${fmtDate(batch.completed_at)}` : ''}
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
        router.post(`/admin/work-orders/${workOrder.id}/complete`, { produced_qty: qty }, { preserveScroll: true });
        onClose();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-lg font-bold text-om-ink mb-4">{__('Complete Work Order')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    Enter the produced quantity for <strong>{workOrder.order_no}</strong>.
                </p>
                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Produced Quantity')}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            max={workOrder.planned_qty * 2}
                            value={qty}
                            onChange={(e) => setQty(e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            required
                        />
                        <p className="text-xs text-om-muted mt-1">Planned: {fmtQty(workOrder.planned_qty)}</p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-medium text-white bg-om-running border border-transparent rounded-md hover:brightness-95"
                        >
                            Mark as Done
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

    function submit(e) {
        e.preventDefault();
        router.post(`/admin/work-orders/${workOrder.id}/allocations/${alloc.id}/consume`,
            { consumed_qty: consumed, scrap_qty: scrap || 0 },
            { preserveScroll: true, onSuccess: onClose });
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
                <ModalActions onClose={onClose} submitLabel={__('Save')} />
            </form>
        </ModalFrame>
    );
}

function ReturnModal({ workOrder, alloc, onClose }) {
    const returnable = Math.max(0, alloc.allocated_qty - alloc.consumed_qty - alloc.scrap_qty);
    const [qty, setQty] = useState('');
    const [reason, setReason] = useState('');

    function submit(e) {
        e.preventDefault();
        router.post(`/admin/work-orders/${workOrder.id}/allocations/${alloc.id}/return`,
            { qty, reason },
            { preserveScroll: true, onSuccess: onClose });
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
                <ModalActions onClose={onClose} submitLabel={__('Return to stock')} />
            </form>
        </ModalFrame>
    );
}

function ReclassifyModal({ workOrder, alloc, materials, onClose }) {
    const [target, setTarget] = useState('');
    const [qty, setQty] = useState('');
    const [reason, setReason] = useState('');

    function submit(e) {
        e.preventDefault();
        router.post(`/admin/work-orders/${workOrder.id}/reclassify`,
            { source_material_id: alloc.material_id, target_material_id: target, qty, reason },
            { preserveScroll: true, onSuccess: onClose });
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
                <ModalActions onClose={onClose} submitLabel={__('Reclassify')} disabled={!target} />
            </form>
        </ModalFrame>
    );
}

export default function AdminWorkOrderShow() {
    const {
        workOrder, customFields = [],
        stops = [], changeRequests = [], changeControl = {},
        canReclassify = false, materials = [],
    } = usePage().props;
    const [showDoneModal, setShowDoneModal] = useState(false);
    const [showStopModal, setShowStopModal] = useState(false);
    const [showChangeModal, setShowChangeModal] = useState(false);

    const post = (verb) => router.post(`/admin/work-orders/${workOrder.id}/${verb}`, {}, { preserveScroll: true });

    const status = workOrder.status;
    const isTerminal = TERMINAL.includes(status);

    // An order held for a configuration change may only resume once an approved
    // change has actually been applied — resume then carries which one.
    const needsChange = !!changeControl.requires_change;
    const appliedChangeId = changeControl.applied_change_request_id ?? null;
    const resumeBlocked = needsChange && !appliedChangeId;

    function resume() {
        router.post(
            `/admin/work-orders/${workOrder.id}/resume`,
            appliedChangeId ? { change_request_id: appliedChangeId } : {},
            { preserveScroll: true },
        );
    }

    const pct = workOrder.planned_qty > 0
        ? Math.min((workOrder.produced_qty / workOrder.planned_qty) * 100, 100)
        : 0;

    const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < new Date() && status !== 'DONE';

    return (
        <>
            <Head title={__('Work Order :no', { no: workOrder.order_no })} />

            {/* Breadcrumbs */}
            <nav className="flex items-center gap-2 text-sm text-om-muted mb-4">
                <Link href="/admin/dashboard" className="hover:text-om-ink">{__('Dashboard')}</Link>
                <span>/</span>
                <Link href="/admin/work-orders" className="hover:text-om-ink">{__('Work Orders')}</Link>
                <span>/</span>
                <span className="text-om-muted font-medium">#{workOrder.order_no}</span>
            </nav>

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-3xl font-bold text-om-ink font-mono">{workOrder.order_no}</h1>
                            <span className={`px-2 py-0.5 rounded text-xs font-semibold ${WO_STATUS_STYLES[status] ?? 'bg-om-chip text-om-muted'}`}>
                                {status}
                            </span>
                        </div>
                        <p className="text-om-muted mt-1">
                            Created {timeAgo(workOrder.created_at)}
                            {workOrder.product_type_name ? ` · ${workOrder.product_type_name}` : ''}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {status === 'PENDING' && (
                            <>
                                <button
                                    onClick={() => post('accept')}
                                    className="px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
                                >
                                    Accept
                                </button>
                                <button
                                    onClick={() => { if (confirm('Reject this work order?')) post('reject'); }}
                                    className="px-4 py-2 text-sm font-medium text-om-blocked bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    Reject
                                </button>
                            </>
                        )}
                        {status === 'ACCEPTED' && (
                            <button
                                onClick={() => { if (confirm('Reject this work order?')) post('reject'); }}
                                className="px-4 py-2 text-sm font-medium text-om-blocked bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                            >
                                Reject
                            </button>
                        )}
                        {status === 'IN_PROGRESS' && (
                            <>
                                <button
                                    onClick={() => post('pause')}
                                    className="px-4 py-2 text-sm font-medium text-om-downtime bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    Pause
                                </button>
                                <button
                                    onClick={() => setShowStopModal(true)}
                                    className="px-4 py-2 text-sm font-medium text-om-downtime bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                    title={__('Record why production stopped, and whether a configuration change is needed.')}
                                >
                                    {__('Stop production')}
                                </button>
                                <button
                                    onClick={() => setShowDoneModal(true)}
                                    className="px-4 py-2 text-sm font-medium text-white bg-om-running rounded-md hover:brightness-95"
                                >
                                    Done
                                </button>
                            </>
                        )}
                        {HELD.includes(status) && !isTerminal && (
                            <button
                                onClick={resume}
                                disabled={resumeBlocked}
                                title={resumeBlocked
                                    ? __('An approved change request must be applied before this order can resume.')
                                    : undefined}
                                className="px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Resume
                            </button>
                        )}
                        {!isTerminal && changeControl.can_raise_change && (
                            <button
                                onClick={() => setShowChangeModal(true)}
                                className="px-4 py-2 text-sm font-medium text-om-accent bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                            >
                                {__('Request change')}
                            </button>
                        )}

                        {isTerminal ? (
                            <>
                                <button
                                    onClick={() => { if (confirm('Reopen this work order?')) post('reopen'); }}
                                    className="px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
                                >
                                    Reopen
                                </button>
                                <Link
                                    href={`/admin/work-orders/${workOrder.id}/edit`}
                                    className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    Edit
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href={`/admin/work-orders/${workOrder.id}/edit`}
                                    className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={() => { if (confirm('Cancel this work order?')) post('cancel'); }}
                                    className="px-4 py-2 text-sm font-medium text-om-accent bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    Cancel
                                </button>
                            </>
                        )}

                        <Link
                            href="/admin/work-orders"
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            ← Back
                        </Link>
                    </div>
                </div>

                {/* Change hold banner (#182) — the order is stopped and waiting on a
                    change, which is the one thing a supervisor must not miss. */}
                {status === 'CHANGE_HOLD' && (
                    <div className="mb-6 rounded-om-sm border border-om-line2 bg-om-downtime-bg p-4">
                        <p className="font-semibold text-om-downtime">{__('On change hold')}</p>
                        <p className="text-sm text-om-downtime mt-1">
                            {resumeBlocked
                                ? __('Production cannot resume until an approved change request has been applied.')
                                : __('A change has been applied. Production can be resumed.')}
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main */}
                    <div className="lg:col-span-2 space-y-6">

                        {/* Details */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h2 className="text-lg font-bold text-om-ink mb-4">{__('Details')}</h2>
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p className="text-om-muted">{__('Order Number')}</p>
                                    <p className="font-mono font-semibold text-om-ink">{workOrder.order_no}</p>
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Customer')}</p>
                                    {workOrder.customer_name ? (
                                        <p className="font-medium text-om-ink flex items-center gap-2">
                                            {workOrder.customer_name}
                                            {workOrder.customer_tier && (
                                                <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${TIER_BADGE_STYLES[workOrder.customer_tier] ?? 'bg-om-chip text-om-muted'}`}>
                                                    {tierLabel(workOrder.customer_tier)}
                                                </span>
                                            )}
                                        </p>
                                    ) : (
                                        <p className="font-medium text-om-ink">—</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Line')}</p>
                                    <p className="font-medium text-om-ink">{workOrder.line_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Product Type')}</p>
                                    <p className="font-medium text-om-ink">{workOrder.product_type_name ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Planned Qty')}</p>
                                    <p className="font-medium text-om-ink">{fmtQty(workOrder.planned_qty)}</p>
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Produced Qty')}</p>
                                    <p className="font-medium text-om-ink">{fmtQty(workOrder.produced_qty)}</p>
                                </div>
                                <div>
                                    <p className="text-om-muted">{__('Priority')}</p>
                                    <p className="font-medium text-om-ink">
                                        {workOrder.priority ?? '—'}
                                        {workOrder.priority_score != null && (
                                            <span className="text-om-faint font-normal"> · {__('score')} {workOrder.priority_score}</span>
                                        )}
                                    </p>
                                </div>
                                {workOrder.due_date && (
                                    <div>
                                        <p className="text-om-muted">{__('Due Date')}</p>
                                        <p className={`font-medium ${isDuePast ? 'text-om-blocked' : 'text-om-ink'}`}>
                                            {fmtDate(workOrder.due_date)}
                                        </p>
                                    </div>
                                )}
                                {workOrder.description && (
                                    <div className="col-span-2 md:col-span-3">
                                        <p className="text-om-muted">{__('Description')}</p>
                                        <p className="font-medium text-om-ink">{workOrder.description}</p>
                                    </div>
                                )}
                                {workOrder.extra_data && Object.keys(workOrder.extra_data).length > 0 && (
                                    <div className="col-span-2 md:col-span-3">
                                        <p className="text-om-muted mb-1">{__('Extra Data')}</p>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(workOrder.extra_data).map(([k, v]) => (
                                                <div key={k} className="bg-om-panel rounded px-2 py-1">
                                                    <span className="text-xs text-om-faint">{k}</span>
                                                    <p className="text-om-muted font-medium">{String(v)}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Custom fields */}
                        <CustomFieldsDisplay definitions={customFields} values={workOrder.custom_fields ?? {}} />

                        {/* Batches */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h2 className="text-lg font-bold text-om-ink mb-4">
                                Batches{' '}
                                <span className="text-sm font-normal text-om-faint">({workOrder.batches.length})</span>
                            </h2>
                            {workOrder.batches.length === 0 ? (
                                <p className="text-sm text-om-faint py-4 text-center">{__('No batches yet.')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {workOrder.batches.map((batch, i) => (
                                        <BatchRow key={batch.id} batch={{ ...batch, is_first: i === 0 }} />
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Materials reconciliation (#99) */}
                        {workOrder.allocations && workOrder.allocations.length > 0 && (
                            <MaterialsReconciliation
                                workOrder={workOrder}
                                allocations={workOrder.allocations}
                                canReclassify={canReclassify}
                                materials={materials}
                            />
                        )}

                        {/* Change requests (#182) */}
                        {changeRequests.length > 0 && (
                            <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                                <h2 className="text-lg font-bold text-om-ink mb-4">
                                    {__('Change requests')}{' '}
                                    <span className="text-sm font-normal text-om-faint">({changeRequests.length})</span>
                                </h2>
                                <div className="space-y-2">
                                    {changeRequests.map((cr) => (
                                        <Link
                                            key={cr.id}
                                            href={`/admin/work-order-change-requests/${cr.id}`}
                                            className="block p-3 rounded-om-sm bg-om-panel hover:ring-1 hover:ring-om-accent transition"
                                        >
                                            <div className="flex items-center justify-between gap-3 flex-wrap">
                                                <div className="flex items-center gap-3">
                                                    <span className="font-mono text-sm text-om-muted">{cr.code}</span>
                                                    <span className="font-medium text-om-ink">{cr.title}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {cr.resulting_snapshot_version && (
                                                        <span className="text-xs text-om-faint">
                                                            {__('version :v', { v: cr.resulting_snapshot_version })}
                                                        </span>
                                                    )}
                                                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${CR_STATUS_STYLES[cr.status] ?? 'bg-om-chip text-om-muted'}`}>
                                                        {cr.status_label}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="text-xs text-om-faint mt-1">
                                                {cr.effective_from_label}
                                                {cr.requested_by ? ` · ${cr.requested_by}` : ''}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Stop history (#182) */}
                        {stops.length > 0 && (
                            <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                                <h2 className="text-lg font-bold text-om-ink mb-4">
                                    {__('Production stops')}{' '}
                                    <span className="text-sm font-normal text-om-faint">({stops.length})</span>
                                </h2>
                                <div className="space-y-2">
                                    {stops.map((stop) => (
                                        <div
                                            key={stop.id}
                                            className={`p-3 rounded-om-sm ${stop.is_open ? 'bg-om-downtime-bg' : 'bg-om-panel'}`}
                                        >
                                            <div className="flex items-center justify-between gap-3 flex-wrap">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-om-ink">{stop.type_label}</span>
                                                    {stop.requires_change && (
                                                        <span className="px-1.5 py-0.5 rounded text-xs bg-om-chip text-om-accent">
                                                            {__('change required')}
                                                        </span>
                                                    )}
                                                    {stop.is_open && (
                                                        <span className="px-1.5 py-0.5 rounded text-xs bg-om-downtime-bg text-om-downtime font-medium">
                                                            {__('open')}
                                                        </span>
                                                    )}
                                                </div>
                                                <span className="text-sm font-medium text-om-muted">
                                                    {fmtDuration(stop.duration_minutes)}
                                                </span>
                                            </div>
                                            <p className="text-sm text-om-muted mt-1">{stop.reason}</p>
                                            <p className="text-xs text-om-faint mt-1">
                                                {fmtDate(stop.stopped_at)}
                                                {stop.stopped_by ? ` · ${stop.stopped_by}` : ''}
                                                {' · '}
                                                {__('produced :qty at stop', { qty: fmtQty(stop.produced_qty_at_stop) })}
                                                {stop.snapshot_version_at_stop
                                                    ? ` · ${__('version :v', { v: stop.snapshot_version_at_stop })}`
                                                    : ''}
                                            </p>
                                            {stop.resumed_at && (
                                                <p className="text-xs text-om-faint mt-0.5">
                                                    {__('Resumed')} {fmtDate(stop.resumed_at)}
                                                    {stop.resumed_by ? ` · ${stop.resumed_by}` : ''}
                                                    {stop.resume_notes ? ` — ${stop.resume_notes}` : ''}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">

                        {/* Progress */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h3 className="text-base font-bold text-om-ink mb-3">{__('Progress')}</h3>
                            <div className="mb-3">
                                <div className="flex justify-between text-sm text-om-muted mb-1">
                                    <span>{__('Completion')}</span>
                                    <span>{pct.toFixed(1)}%</span>
                                </div>
                                <div className="w-full bg-om-line2 rounded-full h-3">
                                    <div
                                        className={`h-3 rounded-full ${pct >= 100 ? 'bg-om-running' : 'bg-om-ink'}`}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Planned:')}</span>
                                    <span className="font-medium">{fmtQty(workOrder.planned_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Produced:')}</span>
                                    <span className="font-medium">{fmtQty(workOrder.produced_qty)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Batches:')}</span>
                                    <span className="font-medium">{workOrder.batches.length}</span>
                                </div>
                                {/* Which configuration the order is running (#182). */}
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Configuration:')}</span>
                                    <span className="font-medium">v{workOrder.snapshot_version ?? 1}</span>
                                </div>
                            </div>
                        </div>

                        {/* Issues */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <div className="flex justify-between items-center mb-3">
                                <h3 className="text-base font-bold text-om-ink">{__('Issues')}</h3>
                                <Link
                                    href={`/admin/issues?search=${encodeURIComponent(workOrder.order_no)}`}
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
                                        const issueStatusStyle = ISSUE_STATUS_STYLES[issue.status] ?? 'bg-om-chip text-om-muted';
                                        return (
                                            <Link
                                                key={issue.id}
                                                href={`/admin/issues?search=${encodeURIComponent(workOrder.order_no)}`}
                                                className={`block p-2 rounded-om-sm text-xs transition hover:ring-1 hover:ring-blue-300 ${isBlocking ? 'bg-om-blocked-bg' : 'bg-om-panel'}`}
                                            >
                                                <div className="flex justify-between">
                                                    <span className="font-medium text-om-ink">{issue.issue_type_name}</span>
                                                    <span className={`px-1.5 py-0.5 rounded text-xs ${issueStatusStyle}`}>
                                                        {__(issue.status)}
                                                    </span>
                                                </div>
                                                <p className="text-om-muted mt-1 truncate">{issue.title}</p>
                                            </Link>
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
        </>
    );
}

AdminWorkOrderShow.layout = (page) => <AppLayout>{page}</AppLayout>;
