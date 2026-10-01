import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { __ } from '../../lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Dropdown, IconButton, StatusPill } from '@openmes/ui';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import LabelPrintMenu from '../../components/LabelPrintMenu';
import Tooltip from '../../components/Tooltip';
import AppDataTable from '../../components/AppDataTable';
import usePrompt from '../../components/usePrompt';
import RoutingGraph from '../../components/flow/RoutingGraph';
import DueCountdown, { SETTLED_STATUSES } from '../../components/DueCountdown';
import { formatDate, formatNumber } from '../../lib/i18n';
import { Hook, hasRenderableHook } from '../../lib/hooks';
import QuantityField from '../../components/QuantityField';

// Geist White restyle: light-only v1 — former `dark:` variants removed.

// ─── helpers ────────────────────────────────────────────────────────────────

function fmt(n) {
    return formatNumber(Number(n ?? 0), { maximumFractionDigits: 0 });
}

function weekLabel(wk) {
    return 'W' + String(wk).padStart(2, '0');
}

function statusLabel(status) {
    const labels = { PENDING: 'Not Started', IN_PROGRESS: 'In Progress', DONE: 'Done', BLOCKED: 'Blocked', PAUSED: 'Paused', CANCELLED: 'Cancelled', REJECTED: 'Rejected', ACCEPTED: 'Accepted' };
    return __(labels[status] ?? status ?? 'Unknown');
}

// Imported extra_data can hold lists or nested objects — String() would print
// "[object Object]", so flatten them to readable text.
function formatExtraValue(val) {
    if (val === undefined || val === null || val === '') return '';
    if (Array.isArray(val)) return val.map(formatExtraValue).filter(Boolean).join(', ');
    if (typeof val === 'object') {
        return Object.entries(val)
            .map(([k, v]) => {
                const text = formatExtraValue(v);
                return text ? `${k.replace(/_/g, ' ')}: ${text}` : '';
            })
            .filter(Boolean)
            .join(' · ');
    }
    return String(val);
}

function getCellValue(wo, col) {
    if (col.source === 'extra_data') {
        return formatExtraValue(wo.extra_data?.[col.key]) || '—';
    }
    if (col.source === 'product_type') {
        return wo.product_type?.name ?? '—';
    }
    // field
    if (col.key === 'due_date') {
        if (!wo.due_date) return '—';
        // The date alone leaves the operator counting days off a calendar to see
        // which order is the urgent one; the countdown under it says so directly.
        return (
            <span className="inline-flex flex-col leading-tight">
                <span>{formatDate(new Date(wo.due_date), { day: '2-digit', month: 'short' })}</span>
                <DueCountdown
                    due={wo.due_date}
                    settled={SETTLED_STATUSES.includes(wo.status)}
                    className="text-[11px]"
                />
            </span>
        );
    }
    if (col.key === 'week_number') {
        return wo.week_number ? weekLabel(wo.week_number) : '—';
    }
    const v = wo[col.key];
    return v !== undefined && v !== null ? String(v) : '—';
}

// Shared Geist White idiom classes
const fieldLabelCls = 'block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';
const inputCls =
    'w-full text-[13px] text-om-ink placeholder:text-om-faint bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]';
const modalFooterCls = 'flex gap-3 border-t border-om-line2 bg-om-panel px-[18px] py-[14px]';

// ─── timed-correction link ───────────────────────────────────────────────────

function TimedCorrectLink({ entry, qtyEditPolicy, qtyEditWindowMinutes }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        if (qtyEditPolicy !== 'timed') return;
        const deadline = new Date(entry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1000;
        const tick = setInterval(() => {
            if (Date.now() > deadline) {
                setVisible(false);
                clearInterval(tick);
            }
        }, 5000);
        return () => clearInterval(tick);
    }, [entry.updated_at, qtyEditPolicy, qtyEditWindowMinutes]);

    if (!visible) return null;

    return (
        <Tooltip label="Correct quantity">
            <Link
                href={`/operator/shift-entry/${entry.id}/correct`}
                className="w-6 h-6 flex items-center justify-center rounded-[6px] text-om-faint hover:text-om-accent hover:bg-om-selected transition-colors"
                aria-label="Correct quantity"
                onClick={(e) => e.stopPropagation()}
            >
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
            </Link>
        </Tooltip>
    );
}

// ─── shift cell ─────────────────────────────────────────────────────────────

const SHIFT_CELL_HOOK = 'display.operator.workstation.shift_cell';

function ShiftCell({ wo, shift, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes }) {
    // Read page-wide rather than threaded through every row: the controller
    // resolved this page's hook points into the `hooks` prop.
    const { hooks } = usePage().props;
    const isDone = wo.status === 'DONE';
    const entryKey = `${wo.id}_${shift.id}`;
    const entriesForCell = shiftEntries[entryKey] ?? [];
    const firstEntry = entriesForCell[0] ?? null;
    const entryQty = firstEntry ? parseFloat(firstEntry.quantity) : 0;

    const defaultVal = entryQty > 0 ? String(Math.round(entryQty)) : '';
    const [inputVal, setInputVal] = useState(defaultVal);
    const prevEntryQty = useRef(entryQty);

    // Sync when server data changes (after reload)
    useEffect(() => {
        if (prevEntryQty.current !== entryQty) {
            prevEntryQty.current = entryQty;
            setInputVal(entryQty > 0 ? String(Math.round(entryQty)) : '');
        }
    }, [entryQty]);

    const submit = useCallback(() => {
        const qty = parseInt(inputVal, 10);
        if (!qty || qty <= 0) return;
        router.post(`/operator/workstation/${wo.id}/shift-entry`, { shift_id: shift.id, quantity: qty });
    }, [inputVal, wo.id, shift.id]);

    if (isDone || wo.uses_step_ledger) {
        return (
            <div className="text-center" onClick={(e) => e.stopPropagation()}>
                <span className="font-mono text-[13px] text-om-faint">{entryQty > 0 ? Math.round(entryQty) : 0}</span>
            </div>
        );
    }

    const canCorrect =
        firstEntry &&
        entryQty > 0 &&
        qtyEditPolicy !== 'none' &&
        (qtyEditPolicy === 'full' ||
            (qtyEditPolicy === 'timed' &&
                new Date(firstEntry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1000 > Date.now()));

    // A module that contributes to this point replaces the whole editable cell —
    // input and correction link — so core draws neither. Read-only cells above
    // (done / step ledger) stay core's, and so does this one when the module's
    // component is not in this build.
    if (hasRenderableHook(hooks, SHIFT_CELL_HOOK)) {
        return (
            <td className="px-2 py-1 text-center" onClick={(e) => e.stopPropagation()}>
                <Hook
                    name={SHIFT_CELL_HOOK}
                    hooks={hooks}
                    entry={firstEntry}
                    workOrder={wo}
                    shift={shift}
                    canCorrect={Boolean(canCorrect)}
                />
            </td>
        );
    }

    return (
        <div className="text-center" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-center gap-1">
                <input
                    type="number"
                    value={inputVal}
                    onChange={(e) => setInputVal(e.target.value)}
                    onFocus={(e) => e.target.select()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                    onBlur={() => {
                        const qty = parseInt(inputVal, 10);
                        if (qty > 0 && inputVal !== defaultVal) submit();
                    }}
                    className={`w-16 text-center font-mono text-[15px] font-medium border rounded-om-sm px-1 py-2 outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)] ${
                        entryQty > 0
                            ? 'text-om-accent bg-om-selected border-om-line'
                            : 'bg-om-bg text-om-ink border-om-line'
                    }`}
                    placeholder="—"
                    min="1"
                    step="1"
                    inputMode="numeric"
                />
                {canCorrect && (
                    <TimedCorrectLink
                        entry={firstEntry}
                        qtyEditPolicy={qtyEditPolicy}
                        qtyEditWindowMinutes={qtyEditWindowMinutes}
                    />
                )}
            </div>
        </div>
    );
}

// ─── modals — §09 shell idiom: header hairline + mono subtitle, panel footer ──

function ModalHeader({ title, subtitle, onClose }) {
    return (
        <div className="flex items-center justify-between border-b border-om-line2 px-[18px] py-4">
            <div>
                <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                {subtitle != null && (
                    <div className="mt-[3px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{subtitle}</div>
                )}
            </div>
            <button
                type="button"
                onClick={onClose}
                className="cursor-pointer text-[18px] leading-none text-om-faint hover:text-om-muted"
            >
                ×
            </button>
        </div>
    );
}

function StartModal({ modal, onClose }) {
    if (!modal.open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div
                className="relative w-full max-w-sm overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                onClick={(e) => e.stopPropagation()}
            >
                <ModalHeader title={__("Start Production")} subtitle={modal.orderNo} onClose={onClose} />
                <div className="px-[18px] py-4">
                    <p className="text-om-ink mb-2 text-[17px] font-semibold tracking-[-0.01em]">
                        {modal.product}
                    </p>
                    <p className="text-sm text-om-muted mb-1">
                        {__("Order No")}: <span className="font-mono text-om-ink">{modal.orderNo}</span>
                    </p>
                    <p className="text-sm text-om-muted">
                        {__("Planned")}: <strong className="font-mono text-[15px] text-om-ink">{fmt(modal.qty)}</strong> {__("units")}
                    </p>
                </div>
                <div className={modalFooterCls}>
                    <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px] font-semibold">
                        {__("Cancel")}
                    </Button>
                    <Button
                        variant="accent"
                        onClick={() => {
                            onClose();
                            router.post(`/operator/workstation/${modal.id}/start`);
                        }}
                        className="flex-1 px-6 py-4 text-[15px] font-semibold"
                    >
                        {__("Start")}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function CompleteModal({ modal, onClose }) {
    const [qty, setQty] = useState('');

    useEffect(() => {
        if (modal.open) setQty('');
    }, [modal.open, modal.id]);

    if (!modal.open) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        const n = parseInt(qty, 10);
        if (isNaN(n) || n < 0) return;
        onClose();
        router.post(`/operator/workstation/${modal.id}/complete`, { produced_qty: n });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div
                className="relative w-full max-w-sm overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                onClick={(e) => e.stopPropagation()}
            >
                <ModalHeader title="Add Produced Quantity" subtitle={modal.orderNo} onClose={onClose} />
                <form onSubmit={handleSubmit}>
                    <div className="px-[18px] py-4">
                        <p className="text-om-ink mb-1 text-[17px] font-semibold tracking-[-0.01em]">
                            {modal.product}
                        </p>
                        <p className="text-sm text-om-muted mb-1">
                            Order: <span className="font-mono text-om-ink">{modal.orderNo}</span>
                        </p>
                        <p className="text-sm text-om-muted mb-4">
                            Planned: <strong className="font-mono text-om-ink">{fmt(modal.planned)}</strong> | Already produced: <strong className="font-mono text-om-ink">{fmt(modal.produced)}</strong>
                        </p>
                        <div>
                            <div className={fieldLabelCls}>
                                Quantity <span className="text-om-blocked">*</span>
                            </div>
                            <QuantityField
                                variant="big"
                                aria-label="Quantity"
                                value={qty}
                                onChange={setQty}
                                className="w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-4 font-mono text-[30px] font-medium tracking-[-0.02em] text-center text-om-ink placeholder:text-om-faintest outline-none transition-colors focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]"
                                placeholder="0"
                                min="0"
                                step="1"
                                required
                                autoFocus
                                inputMode="numeric"
                            />
                        </div>
                    </div>
                    <div className={modalFooterCls}>
                        <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px] font-semibold">
                            {__("Cancel")}
                        </Button>
                        <Button
                            type="submit"
                            variant="accent"
                            disabled={qty === '' || parseInt(qty, 10) < 0}
                            className="flex-1 px-6 py-4 text-[15px] font-semibold"
                        >
                            {__("Confirm")}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function InfoModal({ info, onClose }) {
    if (!info) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div
                className="relative w-full max-w-md overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                onClick={(e) => e.stopPropagation()}
            >
                <ModalHeader title={__("Order Details")} subtitle={info.orderNo} onClose={onClose} />
                <div className="px-[18px] py-4 space-y-3">
                    <InfoRow label={__("Order #")}><span className="font-mono text-[13px] font-medium text-om-ink">{info.orderNo}</span></InfoRow>
                    <InfoRow label={__("Product")}><span className="text-sm font-medium text-om-ink">{info.product}</span></InfoRow>
                    <InfoRow label={__("Line")}><span className="text-sm font-medium text-om-ink">{info.line}</span></InfoRow>
                    <InfoRow label={__("Status")}><span className="text-sm font-semibold text-om-ink">{info.status}</span></InfoRow>
                    <div className="grid grid-cols-3 gap-3 py-2">
                        <div className="text-center">
                            <p className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1">{__("Planned")}</p>
                            <p className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-ink">{info.planned}</p>
                        </div>
                        <div className="text-center">
                            <p className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1">{__("Produced")}</p>
                            <p className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-running">{info.produced}</p>
                        </div>
                        <div className="text-center">
                            <p className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mb-1">{__("Remaining")}</p>
                            <p className="font-mono text-[22px] font-medium tracking-[-0.02em] text-om-accent">{info.remaining}</p>
                        </div>
                    </div>
                    <InfoRow label={__("Priority")}><span className="font-mono text-[13px] font-medium text-om-ink">{info.priority}</span></InfoRow>
                    <InfoRow label={__("Due Date")}>
                        <span className="font-mono text-[13px] font-medium text-om-ink">{info.dueDate}</span>
                        {info.dueRaw && <DueCountdown due={info.dueRaw} settled={info.settled} className="ml-2 font-mono text-[12px]" />}
                    </InfoRow>
                    {info.description && info.description !== '-' && (
                        <div>
                            <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">Description</p>
                            <p className="text-sm text-om-ink bg-om-panel border border-om-line2 rounded-om-sm p-2">{info.description}</p>
                        </div>
                    )}
                </div>
                <div className={modalFooterCls}>
                    <Button variant="primary" onClick={onClose} className="w-full px-6 py-4 text-[15px] font-semibold">
                        Close
                    </Button>
                </div>
            </div>
        </div>
    );
}

function InfoRow({ label, children }) {
    return (
        <div className="flex justify-between items-baseline border-b border-om-line2 pb-2">
            <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</span>
            {children}
        </div>
    );
}

function ReportModal({ report, issueTypes, onClose }) {
    const [typeId, setTypeId] = useState('');
    const [title, setTitle] = useState('');
    const [desc, setDesc] = useState('');

    useEffect(() => {
        if (report) {
            setTypeId('');
            setTitle('');
            setDesc('');
        }
    }, [report?.woId]);

    if (!report) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!typeId || !title) return;
        onClose();
        router.post('/operator/issue', {
            work_order_id: report.woId,
            issue_type_id: typeId,
            title,
            description: desc,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.4)]" onClick={onClose} />
            <div
                className="relative w-full max-w-lg overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]"
                onClick={(e) => e.stopPropagation()}
            >
                <ModalHeader title="Report Issue" subtitle={report.woNo} onClose={onClose} />

                <form onSubmit={handleSubmit}>
                    <div className="px-[18px] py-4 space-y-4">
                        <div>
                            <div className={fieldLabelCls}>
                                Type <span className="text-om-blocked">*</span>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {issueTypes.map((t) => (
                                    <label
                                        key={t.id}
                                        className={`flex items-center gap-2 p-3 rounded-om-sm border cursor-pointer transition-colors ${
                                            String(typeId) === String(t.id)
                                                ? 'border-om-accent bg-om-selected shadow-[0_0_0_3px_rgba(234,90,43,0.12)]'
                                                : 'border-om-line bg-om-bg hover:border-om-faintest'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="issue_type_id"
                                            value={t.id}
                                            checked={String(typeId) === String(t.id)}
                                            onChange={() => {
                                                setTypeId(String(t.id));
                                                if (!title) setTitle(t.name);
                                            }}
                                            className="sr-only"
                                            required
                                        />
                                        <span className="text-sm font-medium text-om-ink">{t.name}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <div className={fieldLabelCls}>
                                Title <span className="text-om-blocked">*</span>
                            </div>
                            <input
                                aria-label="Title"
                                type="text"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                className={inputCls}
                                required
                                maxLength={255}
                            />
                        </div>

                        <div>
                            <div className={fieldLabelCls}>
                                Details <span className="text-om-faint normal-case tracking-normal">(optional)</span>
                            </div>
                            <textarea
                                aria-label="Details"
                                value={desc}
                                onChange={(e) => setDesc(e.target.value)}
                                rows={3}
                                className={`${inputCls} resize-none`}
                                maxLength={2000}
                            />
                        </div>
                    </div>

                    <div className={modalFooterCls}>
                        <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px] font-semibold">
                            {__("Cancel")}
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            disabled={!typeId || !title}
                            className="flex-1 px-6 py-4 text-[15px] font-semibold"
                        >
                            {__("Submit Report")}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── status badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }) {
    if (status === 'DONE') {
        return <StatusPill status="done" label={__("Done")} />;
    }
    if (status === 'IN_PROGRESS') {
        return <StatusPill status="running" label={__("In Progress")} />;
    }
    if (status === 'BLOCKED') {
        return <StatusPill status="blocked" label={__("Blocked")} />;
    }
    return <StatusPill status="pending" label={statusLabel(status)} />;
}

// ─── quick step count ───────────────────────────────────────────────────────

function QuickStepCount({ order }) {
    const targets = order.quick_count_targets ?? [];
    const [selected, setSelected] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const target = targets.length === 1 ? targets[0] : targets.find(t => String(t.id) === selected);
    const add = () => {
        if (!target || busy) return;
        setBusy(true);
        setError('');
        router.post(`/operator/batch-step/${target.id}/quantity`, { good_qty: 1, scrap_qty: 0 }, {
            preserveScroll: true,
            onError: errors => setError(Object.values(errors).join(' ')),
            onFinish: () => setBusy(false),
        });
    };
    return <div className="flex flex-col gap-1">
        {targets.length > 1 && <Dropdown aria-label={__('Assigned batch step')} value={selected} placeholder={__('Select step')} options={targets.map(t => ({ value: String(t.id), label: t.label }))} onChange={setSelected} />}
        <Button variant="accent" disabled={!target || busy} onClick={add} aria-label={__('Add one good piece')}>+1</Button>
        {targets.length === 0 && <Link className="max-w-44 text-xs text-om-muted underline" href={`/operator/work-order/${order.id}`}>{__('Check the step: it must be started and have incoming pieces.')}</Link>}
        {error && <p role="alert" className="text-sm text-om-blocked">{error}</p>}
    </div>;
}

// ─── table columns ───────────────────────────────────────────────────────────

/** Whole-row tint for the order's state (the design's done / running / blocked surfaces). */
function rowClassFor(wo) {
    if (wo.status === 'DONE') return 'bg-om-done-bg/60';
    if (wo.status === 'IN_PROGRESS') return 'bg-om-running-bg/50';
    if (wo.status === 'BLOCKED') return 'bg-om-blocked-bg/50';
    return '';
}

/** The plain text behind a configurable column — what search, filters and sorting see. */
function textValue(wo, col) {
    if (col.source === 'extra_data') return formatExtraValue(wo.extra_data?.[col.key]) || '';
    if (col.source === 'product_type') return wo.product_type?.name ?? '';
    if (col.key === 'status') return statusLabel(wo.status);
    if (col.key === 'week_number') return wo.week_number ? weekLabel(wo.week_number) : '';
    const v = wo[col.key];
    return v == null ? '' : String(v);
}

function RowActions({ wo, labelTemplates, routingOpen, onToggleRouting, onComplete, onInfo, onReport }) {
    const isDone = wo.status === 'DONE';
    const planned = parseFloat(wo.planned_qty ?? 0);
    const produced = parseFloat(wo.produced_qty ?? 0);
    const remaining = Math.max(0, planned - produced);
    return (
        // The actions are not the row: a click here never starts or completes the order.
        <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
            {wo.uses_step_ledger && <QuickStepCount order={wo} />}
            {wo.uses_step_ledger && (
                <Link href={`/operator/work-order/${wo.id}`} className="px-3 py-2 text-sm font-semibold text-om-accent">
                    {__('Record output')}
                </Link>
            )}
            {!isDone && !wo.uses_step_ledger && (
                <Tooltip label="Add produced quantity">
                    <IconButton
                        variant="primary"
                        onClick={() => onComplete({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, planned, produced })}
                        className="bg-om-accent hover:bg-om-accent hover:brightness-95"
                        aria-label="Add produced quantity"
                    >
                        +
                    </IconButton>
                </Tooltip>
            )}
            <Tooltip label="Report problem">
                <IconButton variant="danger" onClick={() => onReport({ woId: wo.id, woNo: wo.order_no })} aria-label="Report problem">
                    !
                </IconButton>
            </Tooltip>
            <Tooltip label="Details">
                <IconButton
                    variant="default"
                    onClick={() =>
                        onInfo({
                            orderNo: wo.order_no,
                            product: wo.product_type?.name ?? '-',
                            line: wo.line?.name ?? '-',
                            status: statusLabel(wo.status),
                            planned: fmt(planned),
                            produced: fmt(produced),
                            remaining: fmt(remaining),
                            priority: wo.priority ?? '-',
                            dueDate: wo.due_date ? wo.due_date.substring(0, 10) : '-',
                            // Raw value too, so the details modal can put
                            // the countdown beside the date it prints.
                            dueRaw: wo.due_date ?? null,
                            settled: SETTLED_STATUSES.includes(wo.status),
                            description: wo.description ?? '-',
                        })
                    }
                    aria-label="Details"
                >
                    ?
                </IconButton>
            </Tooltip>
            {labelTemplates.some((t) => t.type === 'work_order') && (
                <LabelPrintMenu kind="work-order" id={wo.id} templates={labelTemplates} label={__("Label")} />
            )}
            {(wo.routing ?? []).length > 0 && (
                <Tooltip label={routingOpen ? __('Hide routing') : __('Show routing')}>
                    <IconButton
                        variant={routingOpen ? 'primary' : 'default'}
                        onClick={() => onToggleRouting(wo.id)}
                        aria-label={routingOpen ? __('Hide routing') : __('Show routing')}
                        aria-expanded={routingOpen}
                        data-testid={`routing-toggle-${wo.id}`}
                    >
                        ⇢
                    </IconButton>
                </Tooltip>
            )}
        </div>
    );
}

/**
 * The table's columns: the line's configurable ones (system fields and
 * extra_data keys, hideable) and then the fixed production block — to produce,
 * produced, remaining, one input column per shift, the actions.
 */
function buildColumns({ allColumns, lineShifts, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, labelTemplates, openRouting, onToggleRouting, onComplete, onInfo, onReport }) {
    const configurable = allColumns.map((col) => ({
        id: col.key,
        accessorFn: (wo) => textValue(wo, col),
        // A string header, not a render function: the column picker names the column by it.
        header: __(col.label),
        meta: col.key === 'due_date' ? { filter: 'date' } : col.key === 'status' ? { filter: 'select' } : {},
        cell: ({ row }) => (col.key === 'status' ? <StatusBadge status={row.original.status} /> : getCellValue(row.original, col)),
    }));
    const qty = (accessor, cls) => ({ cell: ({ getValue }) => <span className={`font-mono text-[15px] ${cls}`}>{fmt(getValue())}</span> });
    const fixed = [
        { id: 'to_produce', accessorFn: (wo) => parseFloat(wo.planned_qty ?? 0) || 0, header: __('To Produce'), enableHiding: false, meta: { align: 'center', filter: false }, ...qty('planned', 'font-medium text-om-ink') },
        { id: 'produced', accessorFn: (wo) => parseFloat(wo.produced_qty ?? 0) || 0, header: __('Produced'), enableHiding: false, meta: { align: 'center', filter: false }, ...qty('produced', 'text-om-muted') },
        {
            id: 'remaining',
            accessorFn: (wo) => Math.max(0, (parseFloat(wo.planned_qty ?? 0) || 0) - (parseFloat(wo.produced_qty ?? 0) || 0)),
            header: __('Remaining'),
            enableHiding: false,
            meta: { align: 'center', filter: false },
            cell: ({ getValue }) => {
                const r = getValue();
                return (
                    <span className={`inline-block min-w-12 rounded-om-sm px-2 py-1 font-mono text-[15px] font-semibold ${r <= 0 ? 'bg-om-running-bg text-om-running' : 'bg-om-accent text-white'}`}>
                        {fmt(r)}
                    </span>
                );
            },
        },
        ...lineShifts.map((shift) => ({
            id: `shift_${shift.id}`,
            accessorFn: (wo) => { const e = shiftEntries[`${wo.id}_${shift.id}`]?.[0]; return e ? parseFloat(e.quantity) || 0 : 0; },
            header: () => <span title={`${shift.name} (${(shift.start_time ?? '').substring(0, 5)}–${(shift.end_time ?? '').substring(0, 5)})`}>{shift.code}</span>,
            enableSorting: false,
            enableHiding: false,
            meta: { align: 'center', filter: false },
            cell: ({ row }) => <ShiftCell wo={row.original} shift={shift} shiftEntries={shiftEntries} qtyEditPolicy={qtyEditPolicy} qtyEditWindowMinutes={qtyEditWindowMinutes} />,
        })),
        {
            id: '_actions',
            header: __('Actions'),
            enableSorting: false,
            enableHiding: false,
            meta: { align: 'right', chrome: true, filter: false },
            cell: ({ row }) => (
                <RowActions
                    wo={row.original}
                    labelTemplates={labelTemplates}
                    routingOpen={openRouting.has(row.original.id)}
                    onToggleRouting={onToggleRouting}
                    onComplete={onComplete}
                    onInfo={onInfo}
                    onReport={onReport}
                />
            ),
        },
    ];
    return [...configurable, ...fixed];
}

// ─── main page ───────────────────────────────────────────────────────────────

export default function Workstation() {
    const {
        workOrders = [],
        line,
        availableWeeks = [],
        weekFilter,
        search: searchProp,
        issueTypes = [],
        allColumns = [],
        shifts = [],
        shiftEntries = {},
        qtyEditPolicy = 'none',
        qtyEditWindowMinutes = 60,
        labelTemplates = [],
        machineStates = [],
        machineStateOptions = [],
        selectedWorkstation = null,
        hooks = {},
        operatorCanSwitchLine = true,
    } = usePage().props;

    // Which configurable columns the reader keeps on, remembered per line in
    // the browser (as the list of visible keys; `default` decides the first time).
    const storageKey = `ws_cols_${line?.id ?? 0}`;
    const [savedVisibility] = useState(() => {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) ?? 'null');
            if (Array.isArray(saved)) return Object.fromEntries(allColumns.map((c) => [c.key, saved.includes(c.key)]));
        } catch { /* fall through to the defaults */ }
        return Object.fromEntries(allColumns.filter((c) => !c.default).map((c) => [c.key, false]));
    });
    const rememberColumns = (visibility) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(allColumns.filter((c) => visibility[c.key] !== false).map((c) => c.key)));
        } catch { /* private mode etc. — the choice just doesn't survive a reload */ }
    };

    // Modals
    const [startModal, setStartModal] = useState({ open: false });
    const [completeModal, setCompleteModal] = useState({ open: false });
    const [infoModal, setInfoModal] = useState(null);   // null = closed, obj = open
    const [reportModal, setReportModal] = useState(null); // null = closed, obj = open
    // Routing graphs folded out under orders: the header button opens every
    // ledger order's, the row button just that one.
    const [openRouting, setOpenRouting] = useState(() => new Set());
    const routingIds = workOrders.filter((wo) => (wo.routing ?? []).length > 0).map((wo) => wo.id);
    const anyRouting = routingIds.length > 0;
    const showRouting = anyRouting && routingIds.every((id) => openRouting.has(id));
    const toggleRouting = (id) => setOpenRouting((prev) => { const next = new Set(prev); if (next.has(id)) next.delete(id); else next.add(id); return next; });
    const toggleAllRouting = () => setOpenRouting(showRouting ? new Set() : new Set(routingIds));
    const openStep = (wo, stepId) => {
        const params = new URLSearchParams(window.location.search);
        const ctx = ['line', 'workstation'].filter((k) => params.has(k)).map((k) => `${k}=${encodeURIComponent(params.get(k))}`);
        router.visit(`/operator/work-order/${wo.id}?${[...ctx, `step=${stepId}`].join('&')}`);
    };

    // Clicking a row: an order with a step ledger opens its page; otherwise
    // the start / add-quantity dialog, as before.
    const handleRowClick = (wo) => {
        if (wo.uses_step_ledger) { router.visit(`/operator/work-order/${wo.id}`); return; }
        if (wo.status === 'DONE') return;
        const planned = parseFloat(wo.planned_qty ?? 0);
        const produced = parseFloat(wo.produced_qty ?? 0);
        if (wo.status !== 'IN_PROGRESS') {
            setStartModal({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, qty: planned });
        } else {
            setCompleteModal({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, planned, produced });
        }
    };

    // Only show shift columns for shifts belonging to this line
    const lineShifts = shifts.filter((s) => s.line_id === line?.id || String(s.line_id) === String(line?.id));
    const hasShifts = lineShifts.length > 0;

    const weekUrl = (wk) => {
        const params = new URLSearchParams();
        if (wk && wk !== 'all') params.set('week', wk);
        if (searchProp) params.set('search', searchProp);
        const qs = params.toString();
        return '/operator/workstation' + (qs ? '?' + qs : '');
    };

    // Clearing the workstation keeps the week and search filters in place.
    const allWorkstationsUrl = () => {
        const params = new URLSearchParams({ workstation: 'all' });
        if (weekFilter && weekFilter !== 'all') params.set('week', weekFilter);
        if (searchProp) params.set('search', searchProp);
        return `/operator/workstation?${params}`;
    };

    const columns = useMemo(() => buildColumns({
        allColumns, lineShifts: hasShifts ? lineShifts : [], shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, labelTemplates,
        openRouting, onToggleRouting: toggleRouting, onComplete: setCompleteModal, onInfo: setInfoModal, onReport: setReportModal,
    }), [allColumns, lineShifts, hasShifts, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, labelTemplates, openRouting]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title={`Workstation — ${line?.name ?? ''}`} />

            <LineSync lineId={line?.id} reloadOnly={['workOrders', 'shiftEntries']} />

            <div className="max-w-full mx-auto px-2 sm:px-4">
                {machineStates.length > 0 && (
                    <MachineStatePanel machines={machineStates} options={machineStateOptions} label={workOrders.some(order => ['machine', 'both'].includes(order.counting_source)) ? __('Machine state') : __('Workstation state')} />
                )}
                {/* Header */}
                <div className="mb-4">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3">
                        <div>
                            <h1 className="text-[24px] font-semibold tracking-[-0.02em] text-om-ink">
                                {line?.name}
                                {selectedWorkstation && (
                                    <span className="font-mono text-[14px] text-om-accent font-medium ml-2">/ {selectedWorkstation.name}</span>
                                )}
                            </h1>
                            {selectedWorkstation && (
                                <Link
                                    href={allWorkstationsUrl()}
                                    className="text-sm text-om-muted hover:text-om-ink underline underline-offset-2"
                                >
                                    {__("All workstations")}
                                </Link>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {/* Mode toggle */}
                            <div className="flex items-center bg-om-card border border-om-line rounded-om-sm p-1 gap-1">
                                <Link
                                    href="/operator/queue"
                                    className="px-3 py-1.5 rounded-[6px] text-sm font-medium text-om-muted hover:text-om-ink transition-colors"
                                >
                                    {__("Queue")}
                                </Link>
                                <span className="px-3 py-1.5 rounded-[6px] text-sm font-semibold bg-om-ink text-om-on-ink">
                                    {__("Workstation")}
                                </span>
                            </div>

                            {anyRouting && (
                                <Button variant={showRouting ? 'primary' : 'secondary'} onClick={toggleAllRouting} aria-pressed={showRouting} data-testid="routing-toggle-all">
                                    {showRouting ? __('Hide routing') : __('Show routing')}
                                </Button>
                            )}

                            {operatorCanSwitchLine && (
                                <Link
                                    href="/operator/select-line"
                                    className="px-4 py-2.5 rounded-om-sm text-sm font-medium text-om-ink border border-om-line bg-om-card hover:bg-om-chip transition-colors"
                                >
                                    {__("Change Line")}
                                </Link>
                            )}
                        </div>
                    </div>

                    {/* Anything an installed module contributes to this screen.
                        Renders nothing on a community install. */}
                    <Hook
                        name="display.operator.workstation.actor"
                        hooks={hooks}
                        line={line}
                        workstation={selectedWorkstation}
                    />

                    {/* Week filter */}
                    {availableWeeks.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                            <svg className="w-5 h-5 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">{__("Select week:")}</span>

                            <a
                                href={weekUrl('all')}
                                className={`px-4 py-2.5 rounded-om-sm font-mono text-[12px] border transition-colors ${
                                    !weekFilter || weekFilter === 'all'
                                        ? 'bg-om-chip border-om-line text-om-ink'
                                        : 'border-transparent text-om-muted hover:bg-om-chip'
                                }`}
                            >
                                {__("All weeks")}
                            </a>

                            {availableWeeks.map((wk) => (
                                <a
                                    key={wk}
                                    href={weekUrl(wk)}
                                    className={`px-4 py-2.5 rounded-om-sm font-mono text-[12px] border transition-colors ${
                                        String(weekFilter) === String(wk)
                                            ? 'bg-om-selected border-om-accent text-om-accent'
                                            : 'border-transparent text-om-muted hover:bg-om-chip'
                                    }`}
                                >
                                    {weekLabel(wk)}
                                </a>
                            ))}
                        </div>
                    )}

                    <p className="text-xs text-om-faint mb-2">
                        {__('Click a row to change production status. Use "Z1" or "Z2" columns to enter produced quantities per shift.')}
                    </p>
                </div>

                {/* Table */}
                {workOrders.length === 0 ? (
                    <div className="bg-om-card border border-om-line rounded-om text-center py-16">
                        <p className="text-om-faint text-lg">{__('No work orders found')}</p>
                    </div>
                ) : (
                    <AppDataTable
                        data={workOrders}
                        columns={columns}
                        getRowId={(wo) => String(wo.id)}
                        columnVisibility={savedVisibility}
                        onColumnVisibilityChange={rememberColumns}
                        paginated={false}
                        striped={false}
                        rowClassName={rowClassFor}
                        onRowClick={handleRowClick}
                        rowDetail={(wo) => (openRouting.has(wo.id) && (wo.routing ?? []).length > 0 ? (
                            <div data-testid={`routing-row-${wo.id}`}>
                                <RoutingGraph compact height={170} steps={wo.routing} onSelectStep={(id) => openStep(wo, id)} />
                                <p className="px-3 py-1.5 text-[11px] text-om-muted border-t border-om-line2 m-0">{__('Click a step to open the order on it.')}</p>
                            </div>
                        ) : null)}
                    />
                )}
            </div>

            {/* Modals */}
            <StartModal modal={startModal} onClose={() => setStartModal({ open: false })} />
            <CompleteModal modal={completeModal} onClose={() => setCompleteModal({ open: false })} />
            <InfoModal info={infoModal} onClose={() => setInfoModal(null)} />
            {issueTypes.length > 0 && (
                <ReportModal
                    report={reportModal}
                    issueTypes={issueTypes}
                    onClose={() => setReportModal(null)}
                />
            )}
        </>
    );
}

// Machine-state panel (#87): set a workstation's state (running/idle/setup/
// waiting/cleaning/maintenance/stopped/fault) manually from the operator panel.
const MACHINE_STATE_LABELS = {
    RUNNING: 'Running', IDLE: 'Idle', STOPPED: 'Stopped', FAULT: 'Fault', SETUP: 'Setup',
    WAITING: 'Waiting', CLEANING: 'Cleaning', MAINTENANCE: 'Maintenance',
};
const MACHINE_STATE_DOT = {
    RUNNING: 'bg-om-running', IDLE: 'bg-amber-400', SETUP: 'bg-om-accent',
    STOPPED: 'bg-om-faintest', FAULT: 'bg-om-blocked',
    WAITING: 'bg-yellow-400', CLEANING: 'bg-purple-400', MAINTENANCE: 'bg-orange-400',
};

// States that open a downtime — the ones worth a word of explanation.
const DOWNTIME_STATES = ['STOPPED', 'FAULT', 'WAITING', 'CLEANING', 'MAINTENANCE'];

function MachineStatePanel({ machines, options, label }) {
    const { prompt, dialog } = usePrompt();
    const post = (workstationId, state, note = '') => {
        router.post(`/operator/workstation/machine-state/${workstationId}`, note ? { state, note } : { state }, { preserveScroll: true });
    };
    // A stop gets an optional note (what happened), stored with the state and
    // shown on the shift monitor; running/idle/setup post straight away.
    const setState = (machine, state) => {
        if (!DOWNTIME_STATES.includes(state)) { post(machine.id, state); return; }
        prompt(
            { title: `${machine.name}: ${MACHINE_STATE_LABELS[state] ? __(MACHINE_STATE_LABELS[state]) : state}`, label: __('Note (optional)'), placeholder: __('What happened?'), required: false, maxLength: 255 },
            (note) => post(machine.id, state, note),
        );
    };

    return (
        <div className="mb-4 bg-om-card border border-om-line rounded-om-sm p-3">
            <p className="text-[10px] uppercase tracking-[0.08em] text-om-faint mb-2">{label}</p>
            <div className="flex flex-wrap gap-3">
                {machines.map((m) => (
                    <div key={m.id} className="flex items-center gap-2 border border-om-line2 rounded-om-sm px-2.5 py-1.5">
                        <span className={`w-2 h-2 rounded-full ${MACHINE_STATE_DOT[m.state] ?? 'bg-slate-300'}`} />
                        <span className="text-sm font-medium text-om-ink">{m.name}</span>
                        <Dropdown
                            className="min-w-[140px]"
                            value={m.state ?? undefined}
                            placeholder="—"
                            onChange={(state) => state !== m.state && setState(m, state)}
                            aria-label={`${label}: ${m.name}`}
                            options={[
                                // A state outside the settable list (e.g. from a machine feed) stays visible.
                                ...(m.state && !options.includes(m.state) ? [m.state] : []),
                                ...options,
                            ].map((s) => ({ value: s, label: MACHINE_STATE_LABELS[s] ? __(MACHINE_STATE_LABELS[s]) : s }))}
                        />
                    </div>
                ))}
            </div>
            {dialog}
        </div>
    );
}

Workstation.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
