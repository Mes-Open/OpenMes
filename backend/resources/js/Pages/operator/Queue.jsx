import { useState, useEffect } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';

// ─── helpers ────────────────────────────────────────────────────────────────

function fmtQty(v, decimals = 0) {
    const n = parseFloat(v);
    if (isNaN(n)) return '0';
    return n.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function fmtDate(dateStr, format = 'short') {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return '—';
    if (format === 'short') {
        return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
    }
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
        + ', ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function hexToRgba(hex, alpha) {
    if (!hex || hex.length !== 7) return null;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

// ─── WO status badge (mirrors wo-status-badge blade component) ───────────────

function WoStatusBadge({ status }) {
    const map = {
        PENDING:     'bg-gray-100 text-gray-700',
        IN_PROGRESS: 'bg-blue-100 text-blue-700',
        ON_HOLD:     'bg-yellow-100 text-yellow-700',
        DONE:        'bg-green-100 text-green-700',
        CANCELLED:   'bg-red-100 text-red-700',
    };
    const label = {
        PENDING:     'Pending',
        IN_PROGRESS: 'In Progress',
        ON_HOLD:     'On Hold',
        DONE:        'Done',
        CANCELLED:   'Cancelled',
    };
    const cls = map[status] ?? 'bg-gray-100 text-gray-700';
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${cls}`}>
            {label[status] ?? status}
        </span>
    );
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
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-white dark:bg-slate-800 w-full sm:max-w-lg sm:rounded-xl shadow-2xl"
                     onClick={(e) => e.stopPropagation()}>

                    {/* drag handle on mobile */}
                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-gray-300 rounded-full" />
                    </div>

                    <div className="px-5 pt-3 pb-5 sm:p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">Report Issue</h3>
                                <p className="text-sm text-gray-500 font-mono">{woNo}</p>
                            </div>
                            <button type="button" onClick={onClose}
                                    className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            {/* Issue type */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Type <span className="text-red-500">*</span>
                                </label>
                                <div className="grid grid-cols-2 gap-2 mb-2">
                                    {issueTypes.map((type) => {
                                        const selected = String(form.data.issue_type_id) === String(type.id);
                                        return (
                                            <label key={type.id}
                                                   className={`flex items-center gap-2 p-2.5 rounded-lg border-2 cursor-pointer transition-colors ${
                                                       selected ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                                   }`}>
                                                <input type="radio" name="issue_type_id"
                                                       value={type.id}
                                                       checked={selected}
                                                       onChange={() => handleTypeChange(type.id, type.name)}
                                                       className="sr-only"
                                                       required />
                                                <span className="flex-1 text-sm font-medium text-gray-700 dark:text-gray-200 leading-tight">
                                                    {type.name}
                                                    {type.is_blocking && (
                                                        <span className="block text-xs text-red-500 font-normal">⚠ blocking</span>
                                                    )}
                                                </span>
                                                {selected && (
                                                    <svg className="w-4 h-4 text-orange-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Title <span className="text-red-500">*</span>
                                </label>
                                <input type="text" name="title"
                                       value={form.data.title}
                                       onChange={(e) => form.setData('title', e.target.value)}
                                       className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-orange-400"
                                       placeholder="Brief summary…"
                                       required maxLength={255} />
                            </div>

                            {/* Description */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Details <span className="text-gray-400 font-normal text-xs">(optional)</span>
                                </label>
                                <textarea name="description"
                                          value={form.data.description}
                                          onChange={(e) => form.setData('description', e.target.value)}
                                          rows={3}
                                          className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-orange-400 resize-none"
                                          placeholder="Additional details, photos description, measurements…"
                                          maxLength={2000} />
                            </div>

                            <div className="flex gap-3 pt-1">
                                <button type="button" onClick={onClose}
                                        className="flex-1 py-3 text-base text-center rounded-lg bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-800 dark:text-gray-100 font-medium">
                                    Cancel
                                </button>
                                <button type="submit"
                                        disabled={form.processing || !form.data.issue_type_id || !form.data.title}
                                        className="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg py-3 disabled:opacity-40 inline-flex items-center justify-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                                    </svg>
                                    Submit Report
                                </button>
                            </div>
                        </form>
                    </div>
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
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-white dark:bg-slate-800 w-full sm:max-w-sm sm:rounded-xl shadow-2xl"
                     onClick={(e) => e.stopPropagation()}>

                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-gray-300 rounded-full" />
                    </div>

                    <div className="px-5 pt-3 pb-5 sm:p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">Complete Work Order</h3>
                                <p className="text-sm text-gray-500 font-mono">{woNo}</p>
                            </div>
                            <button type="button" onClick={onClose}
                                    className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Produced quantity <span className="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       value={qty}
                                       onChange={(e) => setQty(e.target.value)}
                                       className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 text-2xl font-bold text-center py-4 text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-green-400"
                                       placeholder="0" min="0" step="0.01" required autoFocus />
                                <p className="text-xs text-gray-500 mt-1">Enter the number of units actually produced.</p>
                            </div>

                            <div className="flex gap-3">
                                <button type="button" onClick={onClose}
                                        className="flex-1 py-3 text-base text-center rounded-lg bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-800 dark:text-gray-100 font-medium">
                                    Cancel
                                </button>
                                <button type="submit"
                                        disabled={processing || qty === '' || parseFloat(qty) < 0}
                                        className="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg py-3 disabled:opacity-40 inline-flex items-center justify-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Mark as Done
                                </button>
                            </div>
                        </form>
                    </div>
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
            <div className="fixed inset-0 bg-black/50" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <div className="relative bg-white dark:bg-slate-800 w-full sm:max-w-lg sm:rounded-xl shadow-2xl"
                     onClick={(e) => e.stopPropagation()}>

                    {/* drag handle on mobile */}
                    <div className="flex justify-center pt-3 pb-1 sm:hidden">
                        <div className="w-10 h-1 bg-gray-300 rounded-full" />
                    </div>

                    <div className="px-5 pt-3 pb-5 sm:p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">Report Downtime</h3>
                                <p className="text-sm text-gray-500">Record a production stoppage for this line</p>
                            </div>
                            <button type="button" onClick={onClose}
                                    className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            {/* Reason */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Reason <span className="text-red-500">*</span>
                                </label>
                                <select value={form.data.reason_id}
                                        onChange={(e) => form.setData('reason_id', e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-400"
                                        required>
                                    <option value="">— select reason —</option>
                                    {downtimeReasons.map((r) => (
                                        <option key={r.id} value={String(r.id)}>{r.name}</option>
                                    ))}
                                </select>
                                {form.errors.reason_id && (
                                    <p className="mt-1 text-xs text-red-500">{form.errors.reason_id}</p>
                                )}
                            </div>

                            {/* Notes */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Notes <span className="text-gray-400 font-normal text-xs">(optional)</span>
                                </label>
                                <textarea name="notes"
                                          value={form.data.notes}
                                          onChange={(e) => form.setData('notes', e.target.value)}
                                          rows={3}
                                          className="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 outline-none focus:ring-2 focus:ring-red-400 resize-none"
                                          placeholder="Additional context…"
                                          maxLength={2000} />
                            </div>

                            <div className="flex gap-3 pt-1">
                                <button type="button" onClick={onClose}
                                        className="flex-1 py-3 text-base text-center rounded-lg bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-800 dark:text-gray-100 font-medium">
                                    Cancel
                                </button>
                                <button type="submit"
                                        disabled={form.processing || !form.data.reason_id}
                                        className="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg py-3 disabled:opacity-40 inline-flex items-center justify-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Start Downtime
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── BOARD STATUS BADGE ──────────────────────────────────────────────────────

function BoardStatusBadge({ lineStatus }) {
    if (!lineStatus) return <span className="text-gray-400 text-sm">—</span>;
    return (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold text-white"
              style={{ backgroundColor: lineStatus.color }}>
            {lineStatus.name}
        </span>
    );
}

// ─── ACTIVE WORK ORDER TABLE ROW ─────────────────────────────────────────────

function ActiveWoTableRow({ wo, lineStatuses, workflowMode, doneStatusIds, onReport, onDoneQty }) {
    const ls = wo.line_status ?? null;
    const rowBg = ls && ls.color && ls.color.length === 7
        ? { backgroundColor: hexToRgba(ls.color, 0.12), borderLeft: `4px solid ${ls.color}` }
        : { borderLeft: '4px solid transparent' };

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
            <td className="px-4 py-3 font-mono font-semibold text-gray-800 dark:text-gray-100 whitespace-nowrap">
                <Link href={`/operator/work-order/${wo.id}`} className="hover:text-blue-600">
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
            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                <Link href={`/operator/work-order/${wo.id}`} className="hover:text-blue-600">
                    {wo.product_type?.name ?? '—'}
                </Link>
            </td>
            <td className="px-4 py-3 text-sm whitespace-nowrap">
                <Link href={`/operator/work-order/${wo.id}`}>
                    <span className="font-medium text-gray-800 dark:text-gray-100">
                        {fmtQty(producedQty)} / {fmtQty(plannedQty)}
                    </span>
                    {plannedQty > 0 && (
                        <>
                            <span className="text-xs text-gray-400 ml-1">({fmtQty(pct)}%)</span>
                            <div className="mt-1 w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div className="h-full bg-blue-500 rounded-full" style={{ width: `${pct}%` }} />
                            </div>
                        </>
                    )}
                </Link>
            </td>
            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-center">
                {wo.batches ? wo.batches.length : 0}
            </td>
            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-center">
                {wo.priority || '—'}
            </td>
            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                {fmtDate(wo.due_date, 'short')}
            </td>
            {/* Actions cell — does NOT navigate */}
            <td className="px-3 py-2 whitespace-nowrap">
                <button type="button"
                        onClick={(e) => { e.stopPropagation(); onReport({ woId: wo.id, woNo: wo.order_no }); }}
                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 active:bg-orange-200 transition-colors"
                        title="Report issue">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                    </svg>
                    Report
                </button>
            </td>
            {/* Detail arrow */}
            <td className="px-4 py-3 text-right" style={{ minWidth: 48, cursor: 'pointer' }}
                onClick={() => router.visit(`/operator/work-order/${wo.id}`)}>
                <svg className="w-6 h-6 text-blue-400 inline hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                </svg>
            </td>
        </tr>
    );
}

// ─── ACTIVE WORK ORDER CARD ───────────────────────────────────────────────────

function ActiveWoCard({ wo, lineStatuses, workflowMode, doneStatusIds, onReport, onDoneQty }) {
    const handleSelectChange = (e) => {
        const selectedId = e.target.value ? parseInt(e.target.value) : null;
        if (workflowMode === 'board_status' && selectedId !== null && doneStatusIds.map(Number).includes(selectedId)) {
            onDoneQty({ woId: wo.id, woNo: wo.order_no, statusId: selectedId });
            // revert — user will see the modal
            e.target.value = String(wo.line_status_id ?? '');
            return;
        }
        router.post(`/operator/work-order/${wo.id}/line-status`, { line_status_id: selectedId ?? '' });
    };

    const plannedQty = parseFloat(wo.planned_qty) || 0;
    const producedQty = parseFloat(wo.produced_qty) || 0;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6 hover:shadow-xl transition-all">
            <div className="flex items-center justify-between mb-3">
                <Link href={`/operator/work-order/${wo.id}`}
                      className="text-lg font-bold text-gray-800 dark:text-gray-100 hover:text-blue-700">
                    {wo.order_no}
                </Link>
                <WoStatusBadge status={wo.status} />
            </div>

            {lineStatuses.length > 0 && (
                <div className="mb-3" onClick={(e) => e.stopPropagation()}>
                    <p className="text-xs text-gray-500 mb-1">Board Status</p>
                    <select defaultValue={String(wo.line_status_id ?? '')}
                            onChange={handleSelectChange}
                            className="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 font-medium py-1.5 px-3 cursor-pointer">
                        <option value="">— none —</option>
                        {lineStatuses.map((ls) => (
                            <option key={ls.id} value={String(ls.id)}>{ls.name}</option>
                        ))}
                    </select>
                </div>
            )}

            <Link href={`/operator/work-order/${wo.id}`} className="block">
                <div className="mb-3">
                    <p className="text-sm text-gray-600 dark:text-gray-400">Product</p>
                    <p className="font-medium text-gray-800 dark:text-gray-100">{wo.product_type?.name ?? '—'}</p>
                </div>
                <div className="mb-3">
                    <p className="text-sm text-gray-600 dark:text-gray-400">Quantity</p>
                    <p className="font-medium text-gray-800 dark:text-gray-100">
                        {fmtQty(producedQty, 2)} / {fmtQty(plannedQty, 2)}
                        {plannedQty > 0 && (
                            <span className="text-sm text-gray-500 ml-1">
                                ({fmtQty((producedQty / plannedQty) * 100, 1)}%)
                            </span>
                        )}
                    </p>
                </div>
                <div className="mb-3">
                    <p className="text-sm text-gray-600 dark:text-gray-400">Batches</p>
                    <p className="font-medium text-gray-800 dark:text-gray-100">{wo.batches ? wo.batches.length : 0}</p>
                </div>
                <div className="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3 flex justify-between items-center text-sm">
                    <span className="text-gray-600 dark:text-gray-400">
                        Priority: <span className="font-medium">{wo.priority || '—'}</span>
                    </span>
                    {wo.due_date && (
                        <span className="text-gray-600 dark:text-gray-400">
                            Due: <span className="font-medium">{fmtDate(wo.due_date, 'short')}</span>
                        </span>
                    )}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <button type="button"
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); onReport({ woId: wo.id, woNo: wo.order_no }); }}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                        </svg>
                        Report
                    </button>
                    <span className="flex items-center gap-1 text-blue-600 text-sm font-medium">
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
        trackingMode === 'per_operation' ? 'bg-green-100 text-green-700' :
        trackingMode === 'hybrid'        ? 'bg-amber-100 text-amber-700' :
                                           'bg-gray-100 text-gray-600';

    const trackingLabel =
        trackingMode === 'per_operation' ? 'Per Operation' :
        trackingMode === 'hybrid'        ? 'Hybrid' : 'Cumulative';

    return (
        <>
            <Head title="Work Order Queue" />

            {/* Live sync */}
            <LineSync lineId={line.id} reloadOnly={['activeWorkOrders', 'completedWorkOrders', 'workstationQueue']} />

            <div className="max-w-7xl mx-auto">

                {/* ── Header ── */}
                <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">Work Order Queue</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-2">
                            Line: {line.name}
                            {selectedWorkstation && (
                                <span className="text-blue-600 font-medium ml-2">/ {selectedWorkstation.name}</span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {/* Mode toggle: Queue / Workstation */}
                        <div className="flex items-center bg-gray-100 dark:bg-slate-700 rounded-lg p-1 gap-1">
                            <span className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-white dark:bg-slate-600 shadow text-blue-600">
                                Queue
                            </span>
                            <Link href="/operator/workstation"
                                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white transition-all">
                                Workstation
                            </Link>
                        </div>

                        {/* View toggle: table / cards */}
                        <div className="flex items-center bg-gray-100 dark:bg-slate-700 rounded-lg p-1 gap-1">
                            <button type="button" onClick={() => setView('table')}
                                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${
                                        view === 'table' ? 'bg-white dark:bg-slate-600 shadow text-blue-600' : 'text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white'
                                    }`}>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                                </svg>
                                Table
                            </button>
                            <button type="button" onClick={() => setView('cards')}
                                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${
                                        view === 'cards' ? 'bg-white dark:bg-slate-600 shadow text-blue-600' : 'text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white'
                                    }`}>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                </svg>
                                Cards
                            </button>
                        </div>

                        <Link href="/operator/select-line"
                              className="px-4 py-2.5 rounded-lg text-sm font-medium bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors">
                            Change Line
                        </Link>
                    </div>
                </div>

                {/* ── Downtime bar ── */}
                {activeDowntime ? (
                    <div className="mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-xl border-2 border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3">
                        <div className="flex items-center gap-2 flex-1 min-w-0">
                            <span className="flex-shrink-0 w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse" />
                            <span className="text-sm font-semibold text-red-700 dark:text-red-300 truncate">
                                Downtime in progress &mdash; {activeDowntime.reason.name}
                            </span>
                            <span className="hidden sm:inline text-xs text-red-500 dark:text-red-400 whitespace-nowrap">
                                (since {fmtDate(activeDowntime.started_at, 'long')})
                            </span>
                        </div>
                        <span className="sm:hidden text-xs text-red-500 dark:text-red-400">
                            since {fmtDate(activeDowntime.started_at, 'long')}
                        </span>
                        {activeDowntime.notes && (
                            <span className="text-xs text-red-600 dark:text-red-400 italic truncate max-w-xs hidden lg:inline">
                                {activeDowntime.notes}
                            </span>
                        )}
                        <button type="button"
                                onClick={() => router.post('/operator/downtime/' + activeDowntime.id + '/stop', {}, { preserveScroll: true })}
                                className="flex-shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-sm font-semibold transition-colors">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10h6v4H9z"/>
                            </svg>
                            Stop Downtime
                        </button>
                    </div>
                ) : downtimeReasons.length > 0 && (
                    <div className="mb-4 flex justify-end">
                        <button type="button"
                                onClick={() => setDowntimeModalOpen(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:border-amber-700 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-sm font-semibold transition-colors">
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
                        <span className="text-xs font-semibold text-gray-500 uppercase">Workstation filter:</span>

                        <button type="button"
                                onClick={() => router.get('/operator/queue', {}, { preserveState: false })}
                                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                                    !selectedWorkstation ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600'
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
                                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                                            isSelected ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600'
                                        }`}>
                                    {ws.name}
                                    {isSelected && queueCount > 0 && (
                                        <span className="ml-1 inline-flex items-center justify-center w-5 h-5 bg-white text-blue-600 rounded-full text-[10px] font-bold">
                                            {queueCount}
                                        </span>
                                    )}
                                </button>
                            );
                        })}

                        <div className="ml-auto">
                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase ${trackingBadgeClass}`}>
                                {trackingLabel}
                            </span>
                        </div>
                    </div>
                )}

                {/* ── Workstation queue (filtered) ── */}
                {showWorkstationQueue && workstationQueue.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-3">
                            Ready at {selectedWorkstation.name}
                            <span className="text-sm font-normal text-gray-500 ml-2">({workstationQueue.length})</span>
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
                                          className="block p-4 rounded-xl border-2 border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 hover:border-blue-400 transition group">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-bold text-gray-800 dark:text-gray-100">{wo.order_no}</span>
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-semibold ${
                                                currentStep?.status === 'IN_PROGRESS'
                                                    ? 'bg-amber-200 text-amber-800'
                                                    : 'bg-green-200 text-green-800'
                                            }`}>
                                                {currentStep?.status === 'IN_PROGRESS' ? 'In Progress' : 'Ready'}
                                            </span>
                                        </div>
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            {wo.product_type?.name ?? '-'}
                                        </div>
                                        {currentStep && (
                                            <div className="mt-2 text-xs text-blue-700 dark:text-blue-400 font-medium">
                                                Step {currentStep.step_number}: {currentStep.name}
                                            </div>
                                        )}
                                        <div className="mt-1 text-[10px] text-gray-500">
                                            Qty: {wo.planned_qty} &middot; Batch #{currentBatch?.batch_number}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}

                {showWorkstationQueue && workstationQueue.length === 0 && (
                    <div className="mb-6 p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-800 text-center">
                        <p className="text-sm text-gray-500">
                            No work orders currently waiting at <strong>{selectedWorkstation.name}</strong>
                        </p>
                    </div>
                )}

                {/* ── Active Work Orders ── */}
                <div className="mb-8">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-3">
                        Active Work Orders
                        <span className="text-sm font-normal text-gray-500 ml-2">({activeWorkOrders.length})</span>
                    </h2>

                    {activeWorkOrders.length === 0 ? (
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm text-center py-12">
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No active work orders</h3>
                            <p className="mt-1 text-sm text-gray-500">There are no work orders currently in progress on this line.</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead className="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order No</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                    {lineStatuses.length > 0 && (
                                                        <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                            Board Status
                                                            <span className="ml-1 text-gray-400 font-normal normal-case text-xs" title="Tap badge to cycle">↻</span>
                                                        </th>
                                                    )}
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty (done / planned)</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Batches</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                                    <th className="px-4 py-3" />
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
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
                    <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-3">
                        Recently Completed
                        <span className="text-sm font-normal text-gray-500 ml-2">({completedWorkOrders.length})</span>
                    </h2>

                    {completedWorkOrders.length === 0 ? (
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm text-center py-8">
                            <p className="text-sm text-gray-500">No recently completed work orders</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead className="bg-gray-50 dark:bg-slate-700">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order No</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Produced</th>
                                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Completed at</th>
                                                    <th className="px-4 py-3" />
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-100 dark:divide-gray-700">
                                                {completedWorkOrders.map((wo) => (
                                                    <tr key={wo.id}
                                                        onClick={() => router.visit(`/operator/work-order/${wo.id}`)}
                                                        className="hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors cursor-pointer opacity-80">
                                                        <td className="px-4 py-3 font-mono font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">{wo.order_no}</td>
                                                        <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{wo.product_type?.name ?? '—'}</td>
                                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium">{fmtQty(wo.produced_qty)}</td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{fmtDate(wo.completed_at, 'long')}</td>
                                                        <td className="px-4 py-3 text-right">
                                                            <svg className="w-5 h-5 text-gray-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                                                            </svg>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Card view */}
                            {view === 'cards' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {completedWorkOrders.map((wo) => (
                                        <Link key={wo.id}
                                              href={`/operator/work-order/${wo.id}`}
                                              className="block bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6 hover:shadow-xl cursor-pointer transition-all opacity-75">
                                            <div className="flex items-center justify-between mb-4">
                                                <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">{wo.order_no}</h3>
                                                <span className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Completed</span>
                                            </div>
                                            <div className="mb-3">
                                                <p className="text-sm text-gray-600 dark:text-gray-400">Product</p>
                                                <p className="font-medium text-gray-800 dark:text-gray-100">{wo.product_type?.name ?? '—'}</p>
                                            </div>
                                            <div className="mb-3">
                                                <p className="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                                                <p className="font-medium text-gray-800 dark:text-gray-100">{fmtQty(wo.produced_qty, 2)}</p>
                                            </div>
                                            {wo.completed_at && (
                                                <div className="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3 text-sm text-gray-600 dark:text-gray-400">
                                                    Completed: {fmtDate(wo.completed_at, 'long')}
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
