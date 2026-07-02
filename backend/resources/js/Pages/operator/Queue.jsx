import { useState, useEffect } from 'react';
import { __ } from '../../lib/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, ProgressBar, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import { formatDate, formatNumber, formatTime } from '../../lib/i18n';

// Geist White restyle: light-only v1 — former `dark:` classes removed.

// ─── helpers ────────────────────────────────────────────────────────────────

function fmtQty(v, decimals = 0) {
    const n = parseFloat(v);
    if (isNaN(n)) return '0';
    return formatNumber(n, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function fmtDate(dateStr, format = 'short') {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return '—';
    if (format === 'short') {
        return formatDate(d, { day: '2-digit', month: 'short' });
    }
    return formatDate(d, { day: '2-digit', month: 'short', year: 'numeric' })
        + ', ' + formatTime(d, { hour: '2-digit', minute: '2-digit' });
}

function hexToRgba(hex, alpha) {
    if (!hex || hex.length !== 7) return null;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

// §04 input idiom — shared classes for dialog inputs/selects
const inputCls =
    'w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2.5 text-sm text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/20';
const monoLabelCls = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5';

// ─── WO status badge (mirrors wo-status-badge blade component) ───────────────

function WoStatusBadge({ status }) {
    // WO status → design-system pill state
    const map = {
        PENDING:     'pending',
        IN_PROGRESS: 'running',
        ON_HOLD:     'downtime',
        DONE:        'done',
        CANCELLED:   'blocked',
    };
    const label = {
        PENDING:     'Pending',
        IN_PROGRESS: 'In Progress',
        ON_HOLD:     'On Hold',
        DONE:        'Done',
        CANCELLED:   'Cancelled',
    };
    return <StatusPill status={map[status] ?? 'pending'} label={label[status] ?? status} />;
}

// ─── REPORT ISSUE MODAL ──────────────────────────────────────────────────────

function ReportIssueModal({ open, onClose, woId, woNo, issueTypes }) {
    const form = useForm({ work_order_id: '', issue_type_id: '', title: '', description: '' });
    const issueTypeNames = issueTypes.map((t) => t.name);

    useEffect(() => {
        if (open) {
            form.setData({ work_order_id: String(woId ?? ''), issue_type_id: '', title: '', description: '' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, woId]);

    const handleTypeChange = (typeId, typeName) => {
        form.setData((prev) => ({
            ...prev,
            issue_type_id: String(typeId),
            title: !prev.title || issueTypeNames.includes(prev.title) ? typeName : prev.title,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/issue', { onSuccess: () => onClose() });
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-om-card w-full sm:max-w-lg sm:rounded-om border border-om-line overflow-hidden shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                     onClick={(e) => e.stopPropagation()}>

                    {/* drag handle on mobile */}
                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-om-faintest rounded-full" />
                    </div>

                    {/* header — §09 hairline */}
                    <div className="flex items-center justify-between px-5 py-4 border-b border-om-line2">
                        <div>
                            <h3 className="text-[15px] font-semibold text-om-ink">{__("Report Issue")}</h3>
                            <p className="mt-[3px] font-mono text-[11px] text-om-faint">{woNo}</p>
                        </div>
                        <button type="button" onClick={onClose}
                                className="p-2 text-om-faint hover:text-om-ink rounded-om-sm hover:bg-om-chip transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="px-5 py-4 space-y-4">
                            {/* Issue type */}
                            <div>
                                <label className={monoLabelCls}>
                                    Type <span className="text-om-blocked">*</span>
                                </label>
                                <div className="grid grid-cols-2 gap-2 mb-2">
                                    {issueTypes.map((type) => {
                                        const selected = String(form.data.issue_type_id) === String(type.id);
                                        return (
                                            <label key={type.id}
                                                   className={`flex items-center gap-2 p-3 rounded-om-sm border cursor-pointer transition-colors ${
                                                       selected ? 'border-om-accent bg-om-selected' : 'border-om-line hover:border-om-faintest'
                                                   }`}>
                                                <input type="radio" name="issue_type_id"
                                                       value={type.id}
                                                       checked={selected}
                                                       onChange={() => handleTypeChange(type.id, type.name)}
                                                       className="sr-only"
                                                       required />
                                                <span className="flex-1 text-sm font-medium text-om-ink leading-tight">
                                                    {type.name}
                                                    {type.is_blocking && (
                                                        <span className="block font-mono text-[10px] text-om-blocked font-normal">⚠ blocking</span>
                                                    )}
                                                </span>
                                                {selected && (
                                                    <svg className="w-4 h-4 text-om-accent flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"/>
                                                    </svg>
                                                )}
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Title */}
                            <div>
                                <label className={monoLabelCls}>
                                    Title <span className="text-om-blocked">*</span>
                                </label>
                                <input type="text" name="title"
                                       value={form.data.title}
                                       onChange={(e) => form.setData('title', e.target.value)}
                                       className={inputCls}
                                       placeholder="Brief summary…"
                                       required maxLength={255} />
                            </div>

                            {/* Description */}
                            <div>
                                <label className={monoLabelCls}>
                                    Details <span className="text-om-faintest normal-case tracking-normal">(optional)</span>
                                </label>
                                <textarea name="description"
                                          value={form.data.description}
                                          onChange={(e) => form.setData('description', e.target.value)}
                                          rows={3}
                                          className={`${inputCls} resize-none`}
                                          placeholder="Additional details, photos description, measurements…"
                                          maxLength={2000} />
                            </div>
                        </div>

                        {/* footer — §09 panel */}
                        <div className="flex gap-3 px-5 py-3.5 bg-om-panel border-t border-om-line2">
                            <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px]">
                                Cancel
                            </Button>
                            <Button variant="danger" type="submit"
                                    disabled={form.processing || !form.data.issue_type_id || !form.data.title}
                                    className="flex-1 px-6 py-4 text-[15px]">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                                </svg>
                                Submit Report
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ─── DONE QTY MODAL (board_status mode) ──────────────────────────────────────

function DoneQtyModal({ open, onClose, woId, woNo, statusId }) {
    const [qty, setQty] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) setQty('');
    }, [open]);

    const submit = (e) => {
        e.preventDefault();
        if (qty === '' || parseFloat(qty) < 0) return;
        setProcessing(true);
        router.post(`/operator/work-order/${woId}/line-status`, {
            line_status_id: statusId,
            produced_qty: qty,
        }, {
            onFinish: () => { setProcessing(false); onClose(); },
        });
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-om-card w-full sm:max-w-sm sm:rounded-om border border-om-line overflow-hidden shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                     onClick={(e) => e.stopPropagation()}>

                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-om-faintest rounded-full" />
                    </div>

                    {/* header — §09 hairline */}
                    <div className="flex items-center justify-between px-5 py-4 border-b border-om-line2">
                        <div>
                            <h3 className="text-[15px] font-semibold text-om-ink">{__("Complete Work Order")}</h3>
                            <p className="mt-[3px] font-mono text-[11px] text-om-faint">{woNo}</p>
                        </div>
                        <button type="button" onClick={onClose}
                                className="p-2 text-om-faint hover:text-om-ink rounded-om-sm hover:bg-om-chip transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="px-5 py-4">
                            <label className={monoLabelCls}>
                                Produced quantity <span className="text-om-blocked">*</span>
                            </label>
                            <input type="number"
                                   value={qty}
                                   onChange={(e) => setQty(e.target.value)}
                                   className="w-full rounded-om-sm border border-om-line bg-om-bg font-mono text-3xl font-medium text-center py-4 text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                                   placeholder="0" min="0" step="0.01" required autoFocus />
                            <p className="text-xs text-om-faint mt-1.5">Enter the number of units actually produced.</p>
                        </div>

                        {/* footer — §09 panel */}
                        <div className="flex gap-3 px-5 py-3.5 bg-om-panel border-t border-om-line2">
                            <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px]">
                                Cancel
                            </Button>
                            <Button variant="accent" type="submit"
                                    disabled={processing || qty === '' || parseFloat(qty) < 0}
                                    className="flex-1 px-6 py-4 text-[15px]">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Mark as Done
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ─── REPORT DOWNTIME MODAL ───────────────────────────────────────────────────

function ReportDowntimeModal({ open, onClose, downtimeReasons }) {
    const form = useForm({ reason_id: '', notes: '' });

    useEffect(() => {
        if (open) form.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/downtime/start', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-om-card w-full sm:max-w-lg sm:rounded-om border border-om-line overflow-hidden shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                     onClick={(e) => e.stopPropagation()}>

                    {/* drag handle on mobile */}
                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-om-faintest rounded-full" />
                    </div>

                    {/* header — §09 hairline */}
                    <div className="flex items-center justify-between px-5 py-4 border-b border-om-line2">
                        <div>
                            <h3 className="text-[15px] font-semibold text-om-ink">{__("Report Downtime")}</h3>
                            <p className="mt-[3px] text-sm text-om-muted">Record a production stoppage for this line</p>
                        </div>
                        <button type="button" onClick={onClose}
                                className="p-2 text-om-faint hover:text-om-ink rounded-om-sm hover:bg-om-chip transition-colors">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="px-5 py-4 space-y-4">
                            {/* Reason */}
                            <div>
                                <label className={monoLabelCls}>
                                    Reason <span className="text-om-blocked">*</span>
                                </label>
                                <Dropdown
                                    options={downtimeReasons.map((r) => ({ value: String(r.id), label: r.name }))}
                                    value={form.data.reason_id == null ? '' : String(form.data.reason_id)}
                                    onChange={(v) => form.setData('reason_id', v)}
                                    placeholder="— select reason —"
                                    className="w-full"
                                />
                                {form.errors.reason_id && (
                                    <p className="mt-1 text-xs text-om-blocked">{form.errors.reason_id}</p>
                                )}
                            </div>

                            {/* Notes */}
                            <div>
                                <label className={monoLabelCls}>
                                    Notes <span className="text-om-faintest normal-case tracking-normal">(optional)</span>
                                </label>
                                <textarea name="notes"
                                          value={form.data.notes}
                                          onChange={(e) => form.setData('notes', e.target.value)}
                                          rows={3}
                                          className={`${inputCls} resize-none`}
                                          placeholder="Additional context…"
                                          maxLength={2000} />
                            </div>
                        </div>

                        {/* footer — §09 panel */}
                        <div className="flex gap-3 px-5 py-3.5 bg-om-panel border-t border-om-line2">
                            <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px]">
                                Cancel
                            </Button>
                            <Button variant="danger" type="submit"
                                    disabled={form.processing || !form.data.reason_id}
                                    className="flex-1 px-6 py-4 text-[15px]">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Start Downtime
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ─── BOARD STATUS BADGE ──────────────────────────────────────────────────────

function BoardStatusBadge({ lineStatus }) {
    if (!lineStatus) return <span className="text-om-faintest text-sm">—</span>;
    return (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-[20px] font-mono text-[10px] font-semibold tracking-[0.04em] text-white"
              style={{ backgroundColor: lineStatus.color }}>
            {lineStatus.name}
        </span>
    );
}

// ─── ACTIVE WORK ORDER TABLE ROW ─────────────────────────────────────────────

function ActiveWoTableRow({ wo, lineStatuses, workflowMode, doneStatusIds, onReport, onDoneQty }) {
    const ls = wo.line_status ?? null;
    const rowBg = ls && ls.color && ls.color.length === 7
        ? { backgroundColor: hexToRgba(ls.color, 0.12), borderLeft: `3px solid ${ls.color}` }
        : { borderLeft: '3px solid transparent' };

    const cycleStatus = () => {
        if (!lineStatuses.length) return;
        const currentId = wo.line_status_id ? parseInt(wo.line_status_id) : null;
        const ids = [null, ...lineStatuses.map((s) => parseInt(s.id))];
        const currentIdx = ids.indexOf(currentId);
        const nextId = ids[(currentIdx + 1) % ids.length];

        if (workflowMode === 'board_status' && nextId !== null && doneStatusIds.map(Number).includes(nextId)) {
            onDoneQty({ woId: wo.id, woNo: wo.order_no, statusId: nextId });
            return;
        }

        router.post(`/operator/work-order/${wo.id}/line-status`, { line_status_id: nextId ?? '' });
    };

    const plannedQty = parseFloat(wo.planned_qty) || 0;
    const producedQty = parseFloat(wo.produced_qty) || 0;
    const pct = plannedQty > 0 ? Math.min((producedQty / plannedQty) * 100, 100) : 0;

    return (
        <tr className="cursor-pointer transition-all hover:brightness-95 active:brightness-85"
            style={rowBg}>
            <td className="px-4 py-3 font-mono text-[13px] font-semibold text-om-ink whitespace-nowrap">
                <Link href={`/operator/work-order/${wo.id}`} className="hover:text-om-accent">
                    {wo.order_no}
                </Link>
            </td>
            <td className="px-4 py-3 whitespace-nowrap">
                <WoStatusBadge status={wo.status} />
            </td>
            {lineStatuses.length > 0 && (
                <td className="px-4 py-3 whitespace-nowrap cursor-pointer"
                    onClick={cycleStatus}
                    title="Tap to cycle status">
                    <BoardStatusBadge lineStatus={ls} />
                </td>
            )}
            <td className="px-4 py-3 text-[13.5px] font-medium text-om-ink">
                <Link href={`/operator/work-order/${wo.id}`} className="hover:text-om-accent">
                    {wo.product_type?.name ?? '—'}
                </Link>
            </td>
            <td className="px-4 py-3 text-sm whitespace-nowrap">
                <Link href={`/operator/work-order/${wo.id}`}>
                    <span className="font-mono text-[13px] font-medium text-om-ink">
                        {fmtQty(producedQty)} / {fmtQty(plannedQty)}
                    </span>
                    {plannedQty > 0 && (
                        <>
                            <span className="font-mono text-[10px] text-om-faint ml-1">({fmtQty(pct)}%)</span>
                            <ProgressBar value={pct} className="mt-1.5 w-24" />
                        </>
                    )}
                </Link>
            </td>
            <td className="px-4 py-3 font-mono text-[13px] text-om-muted text-center">
                {wo.batches ? wo.batches.length : 0}
            </td>
            <td className="px-4 py-3 font-mono text-[13px] text-om-muted text-center">
                {wo.priority || '—'}
            </td>
            <td className="px-4 py-3 font-mono text-[12px] text-om-muted whitespace-nowrap">
                {fmtDate(wo.due_date, 'short')}
            </td>
            {/* Actions cell — does NOT navigate */}
            <td className="px-3 py-2 whitespace-nowrap">
                <Button variant="danger"
                        onClick={(e) => { e.stopPropagation(); onReport({ woId: wo.id, woNo: wo.order_no }); }}
                        className="gap-1 text-xs"
                        title="Report issue">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                    </svg>
                    Report
                </Button>
            </td>
            {/* Detail arrow */}
            <td className="px-4 py-3 text-right" style={{ minWidth: 48, cursor: 'pointer' }}
                onClick={() => router.visit(`/operator/work-order/${wo.id}`)}>
                <svg className="w-6 h-6 text-om-faint inline hover:text-om-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                </svg>
            </td>
        </tr>
    );
}

// ─── ACTIVE WORK ORDER CARD ───────────────────────────────────────────────────

function ActiveWoCard({ wo, lineStatuses, workflowMode, doneStatusIds, onReport, onDoneQty }) {
    const handleSelectChange = (v) => {
        const selectedId = v ? parseInt(v) : null;
        if (workflowMode === 'board_status' && selectedId !== null && doneStatusIds.map(Number).includes(selectedId)) {
            onDoneQty({ woId: wo.id, woNo: wo.order_no, statusId: selectedId });
            // revert — user will see the modal (Dropdown reverts to current value below)
            return;
        }
        router.post(`/operator/work-order/${wo.id}/line-status`, { line_status_id: selectedId ?? '' });
    };

    const plannedQty = parseFloat(wo.planned_qty) || 0;
    const producedQty = parseFloat(wo.produced_qty) || 0;

    return (
        <div className="bg-om-card border border-om-line rounded-om p-6 transition-shadow hover:shadow-[0_16px_40px_-24px_rgba(26,25,23,0.4)]">
            <div className="flex items-center justify-between mb-3">
                <Link href={`/operator/work-order/${wo.id}`}
                      className="font-mono text-[15px] font-semibold text-om-ink hover:text-om-accent">
                    {wo.order_no}
                </Link>
                <WoStatusBadge status={wo.status} />
            </div>

            {lineStatuses.length > 0 && (
                <div className="mb-3" onClick={(e) => e.stopPropagation()}>
                    <p className={monoLabelCls}>Board Status</p>
                    <Dropdown
                        options={[{ value: '', label: '— none —' }, ...lineStatuses.map((ls) => ({ value: String(ls.id), label: ls.name }))]}
                        value={wo.line_status_id == null ? '' : String(wo.line_status_id)}
                        onChange={handleSelectChange}
                        className="w-full"
                    />
                </div>
            )}

            <Link href={`/operator/work-order/${wo.id}`} className="block">
                <div className="mb-3">
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Product</p>
                    <p className="text-[15px] font-medium text-om-ink">{wo.product_type?.name ?? '—'}</p>
                </div>
                <div className="mb-3">
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Quantity</p>
                    <p className="font-mono text-[15px] font-medium text-om-ink">
                        {fmtQty(producedQty, 2)} / {fmtQty(plannedQty, 2)}
                        {plannedQty > 0 && (
                            <span className="text-[12px] text-om-faint ml-1">
                                ({fmtQty((producedQty / plannedQty) * 100, 1)}%)
                            </span>
                        )}
                    </p>
                    {plannedQty > 0 && (
                        <ProgressBar value={Math.min((producedQty / plannedQty) * 100, 100)} className="mt-2" />
                    )}
                </div>
                <div className="mb-3">
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Batches</p>
                    <p className="font-mono text-[15px] font-medium text-om-ink">{wo.batches ? wo.batches.length : 0}</p>
                </div>
                <div className="border-t border-om-line2 pt-3 mt-3 flex justify-between items-center text-sm">
                    <span className="text-om-muted">
                        Priority: <span className="font-mono font-medium text-om-ink">{wo.priority || '—'}</span>
                    </span>
                    {wo.due_date && (
                        <span className="text-om-muted">
                            Due: <span className="font-mono font-medium text-om-ink">{fmtDate(wo.due_date, 'short')}</span>
                        </span>
                    )}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <Button variant="danger"
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); onReport({ woId: wo.id, woNo: wo.order_no }); }}
                            className="gap-1 text-xs">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                        </svg>
                        Report
                    </Button>
                    <span className="flex items-center gap-1 text-om-accent text-sm font-medium">
                        View Details
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </Link>
        </div>
    );
}

// ─── COMPLETED WORK ORDER COLUMNS ────────────────────────────────────────────

const completedColumns = [
    {
        id: 'order_no',
        accessorKey: 'order_no',
        header: 'Order No',
        cell: ({ row }) => (
            <span className="font-mono text-[13px] font-semibold text-om-muted whitespace-nowrap">{row.original.order_no}</span>
        ),
    },
    {
        id: 'product',
        accessorFn: (r) => r.product_type?.name ?? '—',
        header: 'Product',
        cell: ({ row }) => (
            <span className="text-[13.5px] text-om-muted">{row.original.product_type?.name ?? '—'}</span>
        ),
    },
    {
        id: 'produced',
        accessorKey: 'produced_qty',
        header: 'Produced',
        cell: ({ row }) => (
            <span className="font-mono text-[13px] font-medium text-om-ink">{fmtQty(row.original.produced_qty)}</span>
        ),
    },
    {
        id: 'completed_at',
        accessorKey: 'completed_at',
        header: 'Completed at',
        cell: ({ row }) => (
            <span className="font-mono text-[12px] text-om-faint whitespace-nowrap">{fmtDate(row.original.completed_at, 'long')}</span>
        ),
    },
    {
        id: 'arrow',
        header: '',
        enableSorting: false,
        meta: { align: 'right' },
        cell: () => (
            <svg className="w-5 h-5 text-om-faintest inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
            </svg>
        ),
    },
];

// ─── PAGE ────────────────────────────────────────────────────────────────────

export default function Queue() {
    const {
        activeWorkOrders = [],
        completedWorkOrders = [],
        line,
        selectedWorkstation = null,
        lineStatuses = [],
        issueTypes = [],
        workflowMode = 'status',
        doneStatusIds = [],
        trackingMode = 'per_operation',
        workstationQueue = [],
        lineWorkstations = [],
        downtimeReasons = [],
        activeDowntime = null,
    } = usePage().props;

    // Persist view preference in localStorage
    const [view, setView] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('queueView') || 'table';
        }
        return 'table';
    });
    useEffect(() => {
        if (typeof window !== 'undefined') localStorage.setItem('queueView', view);
    }, [view]);

    // Report issue modal state
    const [reportModal, setReportModal] = useState({ open: false, woId: null, woNo: '' });

    // Done qty modal state (board_status mode)
    const [doneQtyModal, setDoneQtyModal] = useState({ open: false, woId: null, woNo: '', statusId: null });

    // Downtime modal state
    const [downtimeModalOpen, setDowntimeModalOpen] = useState(false);

    const openReport = ({ woId, woNo }) => setReportModal({ open: true, woId, woNo });
    const closeReport = () => setReportModal((s) => ({ ...s, open: false }));

    const openDoneQty = ({ woId, woNo, statusId }) => setDoneQtyModal({ open: true, woId, woNo, statusId });
    const closeDoneQty = () => setDoneQtyModal((s) => ({ ...s, open: false }));

    const showWorkstationFilter =
        trackingMode !== 'cumulative' && lineWorkstations.length > 0;

    const showWorkstationQueue =
        selectedWorkstation &&
        ['per_operation', 'hybrid'].includes(trackingMode);

    const trackingBadgeClass =
        trackingMode === 'per_operation' ? 'text-om-running bg-om-running-bg' :
        trackingMode === 'hybrid'        ? 'text-om-downtime bg-om-downtime-bg' :
                                           'text-om-pending bg-om-pending-bg';

    const trackingLabel =
        trackingMode === 'per_operation' ? 'Per Operation' :
        trackingMode === 'hybrid'        ? 'Hybrid' : 'Cumulative';

    return (
        <>
            <Head title={__("Work Order Queue")} />

            {/* Live sync */}
            <LineSync lineId={line.id} reloadOnly={['activeWorkOrders', 'completedWorkOrders', 'workstationQueue']} />

            <div className="max-w-7xl mx-auto">

                {/* ── Header ── */}
                <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-[28px] font-semibold tracking-[-0.02em] text-om-ink">{__("Work Order Queue")}</h1>
                        <p className="text-sm text-om-muted mt-2">
                            Line: {line.name}
                            {selectedWorkstation && (
                                <span className="font-mono text-[12px] text-om-accent font-medium ml-2">/ {selectedWorkstation.name}</span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Mode toggle: Queue / Workstation */}
                        <div className="flex items-center gap-[3px] rounded-om-sm border border-om-line bg-om-bg p-[3px]">
                            <span className="flex items-center gap-1.5 px-4 py-2 rounded-[6px] text-sm font-medium bg-om-ink text-om-on-ink">
                                Queue
                            </span>
                            <Link href="/operator/workstation"
                                  className="flex items-center gap-1.5 px-4 py-2 rounded-[6px] text-sm font-medium text-om-muted hover:text-om-ink transition-colors">
                                Workstation
                            </Link>
                        </div>

                        {/* View toggle: table / cards */}
                        <div className="flex items-center gap-[3px] rounded-om-sm border border-om-line bg-om-bg p-[3px]">
                            <button type="button" onClick={() => setView('table')}
                                    className={`flex items-center gap-1.5 px-4 py-2 rounded-[6px] text-sm font-medium transition-colors cursor-pointer ${
                                        view === 'table' ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
                                    }`}>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                                </svg>
                                Table
                            </button>
                            <button type="button" onClick={() => setView('cards')}
                                    className={`flex items-center gap-1.5 px-4 py-2 rounded-[6px] text-sm font-medium transition-colors cursor-pointer ${
                                        view === 'cards' ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
                                    }`}>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                Cards
                            </button>
                        </div>

                        <Link href="/operator/select-line"
                              className="px-4 py-2.5 rounded-om-sm text-sm font-medium text-om-ink bg-om-card border border-om-line hover:bg-om-chip transition-colors">
                            Change Line
                        </Link>
                    </div>
                </div>

                {/* ── Downtime bar ── */}
                {activeDowntime ? (
                    <div className="mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-om border border-om-blocked/30 bg-om-blocked-bg px-4 py-3">
                        <div className="flex items-center gap-2 flex-1 min-w-0">
                            <span className="flex-shrink-0 w-2.5 h-2.5 rounded-full bg-om-blocked animate-om-pulse" />
                            <span className="text-sm font-semibold text-om-blocked truncate">
                                Downtime in progress &mdash; {activeDowntime.reason.name}
                            </span>
                            <span className="hidden sm:inline font-mono text-[11px] text-om-blocked/70 whitespace-nowrap">
                                (since {fmtDate(activeDowntime.started_at, 'long')})
                            </span>
                        </div>
                        <span className="sm:hidden font-mono text-[11px] text-om-blocked/70">
                            since {fmtDate(activeDowntime.started_at, 'long')}
                        </span>
                        {activeDowntime.notes && (
                            <span className="text-xs text-om-blocked/80 italic truncate max-w-xs hidden lg:inline">
                                {activeDowntime.notes}
                            </span>
                        )}
                        <Button variant="primary"
                                onClick={() => router.post('/operator/downtime/' + activeDowntime.id + '/stop', {}, { preserveScroll: true })}
                                className="flex-shrink-0 px-5 py-3 text-sm">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10h6v4H9z"/>
                            </svg>
                            Stop Downtime
                        </Button>
                    </div>
                ) : downtimeReasons.length > 0 && (
                    <div className="mb-4 flex justify-end">
                        <button type="button"
                                onClick={() => setDowntimeModalOpen(true)}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg hover:brightness-95 text-om-downtime text-sm font-semibold transition-all cursor-pointer">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Report Downtime
                        </button>
                    </div>
                )}

                {/* ── Workstation filter + tracking mode badge ── */}
                {showWorkstationFilter && (
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Workstation filter:</span>

                        <button type="button"
                                onClick={() => router.get('/operator/queue', {}, { preserveState: false })}
                                className={`px-3.5 py-2 rounded-om-sm text-xs font-medium transition-colors cursor-pointer ${
                                    !selectedWorkstation ? 'bg-om-ink text-om-on-ink' : 'bg-om-chip text-om-muted hover:bg-om-line2'
                                }`}>
                            All
                        </button>

                        {lineWorkstations.map((ws) => {
                            const isSelected = selectedWorkstation && String(selectedWorkstation.id) === String(ws.id);
                            const queueCount = isSelected ? workstationQueue.length : 0;
                            return (
                                <button key={ws.id}
                                        type="button"
                                        onClick={() => router.get('/operator/queue', { workstation: ws.id }, { preserveState: false })}
                                        className={`px-3.5 py-2 rounded-om-sm text-xs font-medium transition-colors cursor-pointer ${
                                            isSelected ? 'bg-om-ink text-om-on-ink' : 'bg-om-chip text-om-muted hover:bg-om-line2'
                                        }`}>
                                    {ws.name}
                                    {isSelected && queueCount > 0 && (
                                        <span className="ml-1.5 inline-flex items-center justify-center w-5 h-5 bg-om-card text-om-ink rounded-full font-mono text-[10px] font-semibold">
                                            {queueCount}
                                        </span>
                                    )}
                                </button>
                            );
                        })}

                        <div className="ml-auto">
                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[20px] font-mono text-[9.5px] uppercase tracking-[0.06em] ${trackingBadgeClass}`}>
                                {trackingLabel}
                            </span>
                        </div>
                    </div>
                )}

                {/* ── Workstation queue (filtered) ── */}
                {showWorkstationQueue && workstationQueue.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold tracking-[-0.01em] text-om-ink mb-3">
                            Ready at {selectedWorkstation.name}
                            <span className="font-mono text-[12px] font-normal text-om-faint ml-2">({workstationQueue.length})</span>
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {workstationQueue.map((wo) => {
                                // Find the batch whose current step is at this workstation
                                const currentBatch = (wo.batches ?? []).find((b) =>
                                    b.steps && b.steps.some((s) => s.workstation_id != null &&
                                        String(s.workstation_id) === String(selectedWorkstation.id) &&
                                        (s.status === 'PENDING' || s.status === 'IN_PROGRESS'))
                                ) ?? null;
                                const currentStep = currentBatch
                                    ? (currentBatch.steps ?? []).find((s) =>
                                        String(s.workstation_id) === String(selectedWorkstation.id) &&
                                        (s.status === 'PENDING' || s.status === 'IN_PROGRESS'))
                                    : null;

                                return (
                                    <Link key={wo.id}
                                          href={`/operator/work-order/${wo.id}`}
                                          className={`block p-4 rounded-om border border-om-line bg-om-card border-l-[3px] hover:bg-om-panel transition-colors group ${
                                              currentStep?.status === 'IN_PROGRESS' ? 'border-l-om-running' : 'border-l-om-accent'
                                          }`}>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-mono text-[13px] font-semibold text-om-ink">{wo.order_no}</span>
                                            <StatusPill
                                                status={currentStep?.status === 'IN_PROGRESS' ? 'running' : 'pending'}
                                                label={currentStep?.status === 'IN_PROGRESS' ? 'In Progress' : 'Ready'}
                                            />
                                        </div>
                                        <div className="text-sm font-medium text-om-ink">
                                            {wo.product_type?.name ?? '-'}
                                        </div>
                                        {currentStep && (
                                            <div className="mt-2 text-xs text-om-accent font-medium">
                                                Step {currentStep.step_number}: {currentStep.name}
                                            </div>
                                        )}
                                        <div className="mt-1 font-mono text-[10px] uppercase tracking-[0.06em] text-om-faint">
                                            Qty: {wo.planned_qty} &middot; Batch #{currentBatch?.batch_number}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}

                {showWorkstationQueue && workstationQueue.length === 0 && (
                    <div className="mb-6 p-6 rounded-om border border-om-line bg-om-card text-center">
                        <p className="text-sm text-om-muted">
                            No work orders currently waiting at <strong className="text-om-ink">{selectedWorkstation.name}</strong>
                        </p>
                    </div>
                )}

                {/* ── Active Work Orders ── */}
                <div className="mb-8">
                    <h2 className="text-lg font-semibold tracking-[-0.01em] text-om-ink mb-3">{__("Active Work Orders")}<span className="font-mono text-[12px] font-normal text-om-faint ml-2">({activeWorkOrders.length})</span>
                    </h2>

                    {activeWorkOrders.length === 0 ? (
                        <div className="bg-om-card border border-om-line rounded-om text-center py-12">
                            <svg className="mx-auto h-12 w-12 text-om-faintest" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 className="mt-2 text-sm font-medium text-om-ink">{__("No active work orders")}</h3>
                            <p className="mt-1 text-sm text-om-muted">There are no work orders currently in progress on this line.</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-om-line2">
                                            <thead className="bg-om-panel">
                                                <tr>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Order No</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Status</th>
                                                    {lineStatuses.length > 0 && (
                                                        <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">
                                                            Board Status
                                                            <span className="ml-1 text-om-faintest font-normal normal-case tracking-normal text-xs" title="Tap badge to cycle">↻</span>
                                                        </th>
                                                    )}
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Product</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Qty (done / planned)</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Batches</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Priority</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Due</th>
                                                    <th className="px-4 py-3 text-left font-mono text-[9.5px] font-medium uppercase tracking-[0.1em] text-om-faint">Actions</th>
                                                    <th className="px-4 py-3" />
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-om-line2">
                                                {activeWorkOrders.map((wo) => (
                                                    <ActiveWoTableRow key={wo.id} wo={wo}
                                                                      lineStatuses={lineStatuses}
                                                                      workflowMode={workflowMode}
                                                                      doneStatusIds={doneStatusIds}
                                                                      onReport={openReport}
                                                                      onDoneQty={openDoneQty} />
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Card view */}
                            {view === 'cards' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {activeWorkOrders.map((wo) => (
                                        <ActiveWoCard key={wo.id} wo={wo}
                                                      lineStatuses={lineStatuses}
                                                      workflowMode={workflowMode}
                                                      doneStatusIds={doneStatusIds}
                                                      onReport={openReport}
                                                      onDoneQty={openDoneQty} />
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* ── Recently Completed ── */}
                <div>
                    <h2 className="text-lg font-semibold tracking-[-0.01em] text-om-ink mb-3">
                        Recently Completed
                        <span className="font-mono text-[12px] font-normal text-om-faint ml-2">({completedWorkOrders.length})</span>
                    </h2>

                    {completedWorkOrders.length === 0 ? (
                        <div className="bg-om-card border border-om-line rounded-om text-center py-8">
                            <p className="text-sm text-om-muted">No recently completed work orders</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <DataTable
                                    data={completedWorkOrders}
                                    columns={completedColumns}
                                    searchable={false}
                                    columnToggle={false}
                                    paginated={false}
                                    onRowClick={(wo) => router.visit(`/operator/work-order/${wo.id}`)}
                                />
                            )}

                            {/* Card view */}
                            {view === 'cards' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {completedWorkOrders.map((wo) => (
                                        <Link key={wo.id}
                                              href={`/operator/work-order/${wo.id}`}
                                              className="block bg-om-card border border-om-line rounded-om p-6 cursor-pointer transition-opacity opacity-70 hover:opacity-100">
                                            <div className="flex items-center justify-between mb-4">
                                                <h3 className="font-mono text-[15px] font-semibold text-om-ink">{wo.order_no}</h3>
                                                <StatusPill status="done" label={__("Completed")} />
                                            </div>
                                            <div className="mb-3">
                                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Product</p>
                                                <p className="text-[15px] font-medium text-om-ink">{wo.product_type?.name ?? '—'}</p>
                                            </div>
                                            <div className="mb-3">
                                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Completed</p>
                                                <p className="font-mono text-[15px] font-medium text-om-ink">{fmtQty(wo.produced_qty, 2)}</p>
                                            </div>
                                            {wo.completed_at && (
                                                <div className="border-t border-om-line2 pt-3 mt-3 text-sm text-om-muted">
                                                    Completed: <span className="font-mono text-[12px]">{fmtDate(wo.completed_at, 'long')}</span>
                                                </div>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* ── Modals ── */}
            {issueTypes.length > 0 && (
                <ReportIssueModal
                    open={reportModal.open}
                    onClose={closeReport}
                    woId={reportModal.woId}
                    woNo={reportModal.woNo}
                    issueTypes={issueTypes}
                />
            )}

            {downtimeReasons.length > 0 && (
                <ReportDowntimeModal
                    open={downtimeModalOpen}
                    onClose={() => setDowntimeModalOpen(false)}
                    downtimeReasons={downtimeReasons}
                />
            )}

            {workflowMode === 'board_status' && (
                <DoneQtyModal
                    open={doneQtyModal.open}
                    onClose={closeDoneQty}
                    woId={doneQtyModal.woId}
                    woNo={doneQtyModal.woNo}
                    statusId={doneQtyModal.statusId}
                />
            )}
        </>
    );
}

Queue.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
