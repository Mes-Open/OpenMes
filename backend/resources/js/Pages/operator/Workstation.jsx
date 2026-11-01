import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import { __ } from '../../lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Dropdown, Icon, IconButton, StatusPill } from '@openmes/ui';
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
import ExecutionPanel from '../../components/operator/ExecutionPanel';
import StationPicker from '../../components/operator/StationPicker';
import ReportIssueDialog from '../../components/operator/ReportIssueDialog';
import ListToolbar, { useOrderSelection, useStoredState } from '../../components/operator/ListToolbar';
import { applyFilter, dueInfo, groupOrders, orderSearchText, pillCls, progressOf, statusMeta } from '../../components/operator/operatorUi';

// Layout ref: OpenMES Operator.dc.html (workstation view + execution panel).
// The grouped table is the default view; the configurable table stays beside it.

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
        <Tooltip label={__("Correct quantity")}>
            <Link
                href={`/operator/shift-entry/${entry.id}/correct`}
                className="w-6 h-6 flex items-center justify-center rounded-[6px] text-om-faint hover:text-om-accent hover:bg-om-selected transition-colors"
                aria-label={__("Correct quantity")}
                onClick={(e) => e.stopPropagation()}
            >
                <Icon name="pencil" size={14} />
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
                aria-label={__('Close')}
                className="cursor-pointer text-om-faint hover:text-om-muted"
            >
                <Icon name="x" size={18} />
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
                <ModalHeader title={__('Add Produced Quantity')} subtitle={modal.orderNo} onClose={onClose} />
                <form onSubmit={handleSubmit}>
                    <div className="px-[18px] py-4">
                        <p className="text-om-ink mb-1 text-[17px] font-semibold tracking-[-0.01em]">
                            {modal.product}
                        </p>
                        <p className="text-sm text-om-muted mb-1">
                            {__('Order')}: <span className="font-mono text-om-ink">{modal.orderNo}</span>
                        </p>
                        <p className="text-sm text-om-muted mb-4">
                            {__('Planned')}: <strong className="font-mono text-om-ink">{fmt(modal.planned)}</strong> | {__('Already produced')}: <strong className="font-mono text-om-ink">{fmt(modal.produced)}</strong>
                        </p>
                        <div>
                            <div className={fieldLabelCls}>
                                {__('Quantity')} <span className="text-om-blocked">*</span>
                            </div>
                            <QuantityField
                                variant="big"
                                aria-label={__('Quantity')}
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

// `compact` (the grouped view's Log output): one row, and nothing at all when
// no step can take a piece — the Record output link beside it says where to go.
function QuickStepCount({ order, compact = false }) {
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
    if (compact) {
        if (targets.length === 0) return null;
        return <div className="flex items-center gap-1.5">
            {targets.length > 1 && <Dropdown className="w-28" aria-label={__('Assigned batch step')} value={selected} placeholder={__('Select step')} options={targets.map(t => ({ value: String(t.id), label: t.label }))} onChange={setSelected} />}
            <button type="button" disabled={!target || busy} onClick={add} aria-label={__('Add one good piece')} title={error || undefined}
                    className={`font-mono text-[12px] font-semibold rounded-[7px] px-3 py-2 ${target && !busy ? 'bg-om-accent text-white cursor-pointer' : 'bg-om-chip text-om-faint cursor-not-allowed'}`}>+1</button>
            {error && <span role="alert" className="max-w-40 truncate text-[11px] text-om-blocked" title={error}>{error}</span>}
        </div>;
    }
    return <div className="flex flex-col gap-1">
        {targets.length > 1 && <Dropdown aria-label={__('Assigned batch step')} value={selected} placeholder={__('Select step')} options={targets.map(t => ({ value: String(t.id), label: t.label }))} onChange={setSelected} />}
        <Button variant="accent" disabled={!target || busy} onClick={add} aria-label={__('Add one good piece')}>+1</Button>
        {targets.length === 0 && <Link className="max-w-44 text-xs text-om-muted underline" href={`/operator/work-order/${order.id}`}>{__('Check the step: it must be started and have incoming pieces.')}</Link>}
        {error && <p role="alert" className="text-sm text-om-blocked">{error}</p>}
    </div>;
}

// ─── table columns ───────────────────────────────────────────────────────────

/** Whole-row tint for the order's state (the design's done / running / blocked surfaces). */
// Mixed into the card colour rather than made transparent: the pinned actions
// column takes the row's background, and a see-through one let the scrolled
// columns show through it. `!` because the table's own stripe class would win.
function rowClassFor(wo) {
    if (wo.status === 'DONE') return 'bg-[color-mix(in_srgb,var(--om-done-bg)_60%,var(--om-card))]!';
    if (wo.status === 'IN_PROGRESS') return 'bg-[color-mix(in_srgb,var(--om-running-bg)_50%,var(--om-card))]!';
    if (wo.status === 'BLOCKED') return 'bg-[color-mix(in_srgb,var(--om-blocked-bg)_50%,var(--om-card))]!';
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
            {/* compact: one row, and no +1 at all when no step can take a piece — the
                link beside it opens the order, where the step says why. */}
            {wo.uses_step_ledger && <QuickStepCount order={wo} compact />}
            {wo.uses_step_ledger && (
                <Link href={`/operator/work-order/${wo.id}`} className="px-3 py-2 text-sm font-semibold text-om-accent whitespace-nowrap">
                    {__('Record output')}
                </Link>
            )}
            {!isDone && !wo.uses_step_ledger && (
                <Tooltip label={__("Add produced quantity")}>
                    <IconButton
                        variant="primary"
                        onClick={() => onComplete({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, planned, produced })}
                        className="bg-om-accent hover:bg-om-accent hover:brightness-95"
                        aria-label={__("Add produced quantity")}
                    >
                        +
                    </IconButton>
                </Tooltip>
            )}
            <Tooltip label={__("Report problem")}>
                <IconButton variant="danger" onClick={() => onReport({ woId: wo.id, woNo: wo.order_no })} aria-label={__("Report problem")}>
                    !
                </IconButton>
            </Tooltip>
            <Tooltip label={__("Details")}>
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
                    aria-label={__("Details")}
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
            // Pinned right so the actions stay on screen when the columns scroll.
            meta: { align: 'right', chrome: true, filter: false, sticky: 'right' },
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

// ─── grouped view (design: workstation table) ────────────────────────────────

/** What a shift cell shows in the grouped view: the shift's count, its correction link, or a module's cell. */
function ShiftValue({ wo, shift, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes }) {
    const { hooks } = usePage().props;
    const entry = (shiftEntries[`${wo.id}_${shift.id}`] ?? [])[0] ?? null;
    const qty = entry ? parseFloat(entry.quantity) : 0;
    const editable = wo.status !== 'DONE' && !wo.uses_step_ledger;
    const canCorrect = editable && entry && qty > 0 && qtyEditPolicy !== 'none'
        && (qtyEditPolicy === 'full' || (qtyEditPolicy === 'timed'
            && new Date(entry.updated_at).getTime() + qtyEditWindowMinutes * 60 * 1000 > Date.now()));

    // A module contributing to the shift cell replaces it here too.
    if (editable && hasRenderableHook(hooks, SHIFT_CELL_HOOK)) {
        return (
            <div className="text-center" onClick={(e) => e.stopPropagation()}>
                <Hook name={SHIFT_CELL_HOOK} hooks={hooks} entry={entry} workOrder={wo} shift={shift} canCorrect={Boolean(canCorrect)} />
            </div>
        );
    }
    return (
        <div className="flex items-center justify-center gap-0.5" onClick={(e) => e.stopPropagation()}>
            <span className={`font-mono text-[13px] ${qty > 0 ? 'text-om-ink' : 'text-om-faintest'}`}>{qty > 0 ? fmt(qty) : '—'}</span>
            {canCorrect && <TimedCorrectLink entry={entry} qtyEditPolicy={qtyEditPolicy} qtyEditWindowMinutes={qtyEditWindowMinutes} />}
        </div>
    );
}

/**
 * The row's "Log output": pick the shift, type the pieces, Add — the
 * shift-entry route adds them to that shift's count. Step-ledger orders log on
 * their steps instead (+1 / Record output), machine-counted ones not at all.
 */
function LogOutput({ wo, lineShifts, defaultShiftId, moduleCell, onAddQuantity }) {
    const [shiftId, setShiftId] = useState(defaultShiftId ?? lineShifts[0]?.id ?? null);
    const [qty, setQty] = useState('');
    const [busy, setBusy] = useState(false);
    useEffect(() => { if (defaultShiftId && !lineShifts.some((s) => s.id === shiftId)) setShiftId(defaultShiftId); }, [defaultShiftId]); // eslint-disable-line react-hooks/exhaustive-deps

    if (wo.status === 'DONE') return <span className="text-[12px] text-om-faint">{__('Completed')}</span>;
    if (wo.status === 'BLOCKED') return <span className="text-[12px] text-om-blocked">{__('Blocked — resolve issue')}</span>;
    if (wo.uses_step_ledger) {
        return (
            <div className="flex items-center gap-2">
                <QuickStepCount order={wo} compact />
                <Link href={`/operator/work-order/${wo.id}`} className="text-[12px] font-semibold text-om-accent whitespace-nowrap">{__('Record output')}</Link>
            </div>
        );
    }
    if (wo.counting_source === 'machine') return <span className="text-[12px] text-om-faint">{__('Machine-counted')}</span>;
    // A module owns the quantity cell on this install: its shift cells are the input.
    if (moduleCell) return null;
    if (!lineShifts.length) {
        return (
            <button type="button" onClick={onAddQuantity} className="text-[12px] font-semibold rounded-[7px] px-3 py-2 bg-om-ink text-om-on-ink cursor-pointer">
                {__('Add quantity')}
            </button>
        );
    }

    const n = parseInt(qty, 10);
    const add = () => {
        if (!n || n <= 0 || !shiftId || busy) return;
        setBusy(true);
        router.post(`/operator/workstation/${wo.id}/shift-entry`, { shift_id: shiftId, quantity: n }, {
            preserveScroll: true,
            onSuccess: () => setQty(''),
            onFinish: () => setBusy(false),
        });
    };
    const seg = (on) => `font-mono text-[10.5px] font-semibold px-[7px] py-[5px] rounded-[5px] cursor-pointer ${on ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'}`;
    return (
        <div className="flex items-center gap-1.5">
            {lineShifts.length > 1 && (
                <div className="flex gap-0.5 bg-om-bg border border-om-line2 rounded-[7px] p-0.5" role="group" aria-label={__('Shift')}>
                    {lineShifts.map((s) => (
                        <button key={s.id} type="button" aria-pressed={shiftId === s.id} onClick={() => setShiftId(s.id)} className={seg(shiftId === s.id)} title={s.name}>
                            {s.code}
                        </button>
                    ))}
                </div>
            )}
            <QuantityField
                aria-label={__('Quantity')}
                value={qty}
                onChange={(v) => setQty(String(v).replace(/[^0-9]/g, '').slice(0, 6))}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }}
                placeholder={__('qty')}
                min="1"
                step="1"
                inputMode="numeric"
                className="w-[54px] text-center border border-om-line rounded-[7px] px-1 py-[7px] bg-om-bg font-mono text-[13px] text-om-ink outline-none focus:border-om-accent"
            />
            <button type="button" disabled={!n || busy} onClick={add}
                    className={`text-[12px] font-semibold rounded-[7px] px-3 py-2 ${n && !busy ? 'bg-om-ink text-om-on-ink cursor-pointer' : 'bg-om-chip text-om-faint cursor-not-allowed'}`}>
                {__('Add')}
            </button>
        </div>
    );
}

function GroupedTable({ orders, selectedId, onSelect, lineShifts, defaultShiftId, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, detailColumns, onAddQuantity }) {
    const { hooks } = usePage().props;
    const moduleCell = hasRenderableHook(hooks, SHIFT_CELL_HOOK);
    const groups = groupOrders(orders);
    const cols = `112px minmax(150px,1fr) 100px 60px 150px ${lineShifts.map(() => '48px').join(' ')} ${moduleCell ? '' : '270px'}`;
    const th = 'font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint';

    return (
        <div className="bg-om-card border border-om-line rounded-xl overflow-x-auto" data-testid="workstation-grouped">
            <div className="min-w-max">
                <div className="grid gap-2.5 items-center px-4 py-2.5 bg-om-panel border-b border-om-line2" style={{ gridTemplateColumns: cols }}>
                    <span className={th}>{__('Order')}</span>
                    <span className={th}>{__('Product')}</span>
                    <span className={th}>{__('Status')}</span>
                    <span className={th}>{__('Due')}</span>
                    <span className={th}>{__('Progress')}</span>
                    {lineShifts.map((s) => (
                        <span key={s.id} className={`${th} text-center`} title={`${s.name} (${(s.start_time ?? '').substring(0, 5)}–${(s.end_time ?? '').substring(0, 5)})`}>{s.code}</span>
                    ))}
                    {!moduleCell && <span className={th}>{__('Log output')}</span>}
                </div>
                {groups.map((g) => (
                    <div key={g.key}>
                        <div className="flex items-center gap-2 px-4 py-[9px] bg-om-bg border-b border-om-line2">
                            <span className={`w-2 h-2 rounded-full ${g.dot}`} />
                            <span className="text-[12px] font-semibold text-om-ink">{g.label}</span>
                            <span className="font-mono text-[11px] text-om-faint">{g.rows.length}</span>
                        </div>
                        {g.rows.map((wo) => {
                            const st = statusMeta(wo.status);
                            const due = dueInfo(wo.due_date, wo.status);
                            const { planned, produced, remaining, pct } = progressOf(wo);
                            const details = detailColumns.map((c) => textValue(wo, c)).filter(Boolean).join(' · ');
                            return (
                                <div
                                    key={wo.id}
                                    role="button"
                                    tabIndex={0}
                                    aria-pressed={wo.id === selectedId}
                                    onClick={() => onSelect(wo.id)}
                                    onKeyDown={(e) => { if (e.target === e.currentTarget && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); onSelect(wo.id); } }}
                                    data-testid={`ws-row-${wo.id}`}
                                    className={`grid gap-2.5 items-center px-4 py-3 border-b border-om-line2 cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40 ${
                                        wo.id === selectedId ? 'bg-om-accent-bg' : 'hover:bg-om-panel'
                                    } ${wo.status === 'DONE' ? 'opacity-60' : ''}`}
                                    style={{ gridTemplateColumns: cols }}
                                >
                                    <Link href={`/operator/work-order/${wo.id}`} onClick={(e) => e.stopPropagation()}
                                          className="font-mono text-[12.5px] font-medium text-om-ink hover:text-om-accent truncate">
                                        {wo.order_no}
                                    </Link>
                                    <div className="min-w-0">
                                        <div className="text-[14px] font-semibold text-om-ink truncate">{wo.product_type?.name ?? '—'}</div>
                                        {details && <div className="text-[11.5px] text-om-faint mt-0.5 truncate">{details}</div>}
                                    </div>
                                    <span className={`flex items-center gap-[7px] text-[11.5px] font-semibold ${st.text}`}>
                                        <span className={`w-2 h-2 rounded-full shrink-0 ${st.dot}`} />{st.label}
                                    </span>
                                    <span className={`font-mono text-[12.5px] ${due.hot ? 'text-om-blocked font-semibold' : 'text-om-muted'}`}>
                                        {due.label}
                                    </span>
                                    <div>
                                        <div className="flex justify-between items-baseline mb-1.5 gap-1">
                                            <span className="font-mono text-[13px] text-om-ink whitespace-nowrap">{fmt(produced)}<span className="text-om-faint"> / {fmt(planned)}</span></span>
                                            <span className={`text-[11px] font-semibold whitespace-nowrap ${remaining <= 0 ? 'text-om-running' : 'text-om-accent'}`}>
                                                {remaining <= 0 ? __('complete') : __(':count left', { count: fmt(remaining) })}
                                            </span>
                                        </div>
                                        <div className="h-[5px] rounded-full bg-om-chip overflow-hidden"><div className="h-full bg-om-accent" style={{ width: `${pct}%` }} /></div>
                                    </div>
                                    {lineShifts.map((s) => (
                                        <ShiftValue key={s.id} wo={wo} shift={s} shiftEntries={shiftEntries} qtyEditPolicy={qtyEditPolicy} qtyEditWindowMinutes={qtyEditWindowMinutes} />
                                    ))}
                                    {!moduleCell && (
                                        <div onClick={(e) => e.stopPropagation()}>
                                            <LogOutput wo={wo} lineShifts={lineShifts} defaultShiftId={defaultShiftId} moduleCell={moduleCell} onAddQuantity={() => onAddQuantity(wo)} />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                ))}
                {groups.length === 0 && <div className="text-center p-[34px] text-om-faint text-[13px]">{__('No work orders match.')}</div>}
            </div>
        </div>
    );
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
        shiftSummary = null,
        stationPicker = [],
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

    // The design's grouped table by default; the configurable table beside it.
    const [view, setView] = useStoredState('operatorWorkstationView', 'grouped');
    const [filter, setFilter] = useStoredState('operatorWorkstationFilter', 'all');
    const [query, setQuery] = useState(searchProp ?? '');

    // Modals
    const [startModal, setStartModal] = useState({ open: false });
    const [completeModal, setCompleteModal] = useState({ open: false });
    const [infoModal, setInfoModal] = useState(null);   // null = closed, obj = open
    const [reportFor, setReportFor] = useState(null);   // null = closed, order = open
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

    const visible = useMemo(
        () => applyFilter(workOrders, filter, query, (wo) => `${orderSearchText(wo)} ${allColumns.map((c) => textValue(wo, c)).join(' ')}`),
        [workOrders, filter, query, allColumns],
    );
    const { selectedId, select, mobileOpen, closeMobile } = useOrderSelection(workOrders);
    const selected = workOrders.find((wo) => wo.id === selectedId) ?? null;

    const startFor = (wo) => setStartModal({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, qty: parseFloat(wo.planned_qty ?? 0) });
    const completeFor = (wo) => setCompleteModal({ open: true, id: wo.id, orderNo: wo.order_no, product: wo.product_type?.name ?? wo.order_no, planned: parseFloat(wo.planned_qty ?? 0), produced: parseFloat(wo.produced_qty ?? 0) });
    // Callers pass { woId, woNo } (the table's actions) or the order (the panel).
    const openReport = ({ woId, woNo, id, order_no, product }) => {
        const wo = workOrders.find((o) => o.id === (woId ?? id));
        setReportFor({ id: woId ?? id, order_no: woNo ?? order_no, product: product ?? wo?.product_type?.name });
    };

    // Table view: clicking a row — an order with a step ledger opens its page;
    // otherwise the start / add-quantity dialog, as before.
    const handleRowClick = (wo) => {
        select(wo.id);
        if (wo.uses_step_ledger) { router.visit(`/operator/work-order/${wo.id}`); return; }
        if (wo.status === 'DONE') return;
        if (wo.status !== 'IN_PROGRESS') startFor(wo); else completeFor(wo);
    };

    // The panel's footer for an order counted by quantity: start it, then add pieces.
    const quantityAction = (() => {
        if (!selected || selected.uses_step_ledger) return null;
        if (selected.status === 'IN_PROGRESS') return { label: __('Add produced quantity'), onClick: () => completeFor(selected) };
        if (['PENDING', 'ACCEPTED', 'PAUSED'].includes(selected.status)) return { label: __('Start Production'), onClick: () => startFor(selected) };
        return null;
    })();

    // Only show shift columns for shifts belonging to this line
    const lineShifts = shifts.filter((s) => s.line_id === line?.id || String(s.line_id) === String(line?.id));
    const hasShifts = lineShifts.length > 0;
    // The shift running now is the one a new count goes to by default.
    const defaultShiftId = lineShifts.find((s) => s.code && s.code === shiftSummary?.shift?.code)?.id ?? lineShifts[0]?.id ?? null;
    // Extra detail under the product in the grouped view: the line's own columns.
    const detailColumns = allColumns.filter((c) => c.source === 'extra_data' && c.default);

    const weekUrl = (wk) => {
        const params = new URLSearchParams();
        if (wk && wk !== 'all') params.set('week', wk);
        if (searchProp) params.set('search', searchProp);
        const qs = params.toString();
        return '/operator/workstation' + (qs ? '?' + qs : '');
    };

    // Changing the workstation keeps the week and search filters in place.
    const stationUrl = (id) => {
        const params = new URLSearchParams({ workstation: id ?? 'all' });
        if (weekFilter && weekFilter !== 'all') params.set('week', weekFilter);
        if (searchProp) params.set('search', searchProp);
        return `/operator/workstation?${params}`;
    };

    const columns = useMemo(() => buildColumns({
        allColumns, lineShifts: hasShifts ? lineShifts : [], shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, labelTemplates,
        openRouting, onToggleRouting: toggleRouting, onComplete: setCompleteModal, onInfo: setInfoModal, onReport: openReport,
    }), [allColumns, lineShifts, hasShifts, shiftEntries, qtyEditPolicy, qtyEditWindowMinutes, labelTemplates, openRouting, workOrders]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title={`Workstation — ${line?.name ?? ''}`} />

            <LineSync lineId={line?.id} reloadOnly={['workOrders', 'shiftEntries', 'shiftSummary']} />

            <div className="h-full flex">
            <div className="flex-1 min-w-0 overflow-auto px-4 md:px-[22px] pt-[18px] pb-10">
                {machineStates.length > 0 && (
                    <MachineStatePanel machines={machineStates} options={machineStateOptions} label={workOrders.some(order => ['machine', 'both'].includes(order.counting_source)) ? __('Machine state') : __('Workstation state')} />
                )}

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
                        <span className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint mr-1">{__("Select week:")}</span>
                        <div className="flex flex-wrap gap-[3px] bg-om-card border border-om-line rounded-[9px] p-[3px]">
                            <a href={weekUrl('all')} className={pillCls(!weekFilter || weekFilter === 'all')}>
                                {__("All weeks")}
                            </a>
                            {availableWeeks.map((wk) => (
                                <a key={wk} href={weekUrl(wk)} className={`${pillCls(String(weekFilter) === String(wk))} font-mono`}>
                                    {weekLabel(wk)}
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                <ListToolbar
                    leading={stationPicker.length > 0 && (
                        <StationPicker
                            stations={stationPicker}
                            selectedId={selectedWorkstation?.id ?? null}
                            onPick={(id) => router.visit(stationUrl(id))}
                        />
                    )}
                    query={query} onQuery={setQuery}
                    filter={filter} onFilter={setFilter}
                    count={visible.length}
                    views={[['grouped', __('Grouped')], ['table', __('Table')]]}
                    view={view} onView={setView}
                >
                    {view === 'table' && anyRouting && (
                        <Button variant={showRouting ? 'primary' : 'secondary'} onClick={toggleAllRouting} aria-pressed={showRouting} data-testid="routing-toggle-all">
                            {showRouting ? __('Hide routing') : __('Show routing')}
                        </Button>
                    )}
                </ListToolbar>

                {view === 'grouped' && (
                    <GroupedTable
                        orders={visible}
                        selectedId={selectedId}
                        onSelect={select}
                        lineShifts={hasShifts ? lineShifts : []}
                        defaultShiftId={defaultShiftId}
                        shiftEntries={shiftEntries}
                        qtyEditPolicy={qtyEditPolicy}
                        qtyEditWindowMinutes={qtyEditWindowMinutes}
                        detailColumns={detailColumns}
                        onAddQuantity={completeFor}
                    />
                )}

                {view === 'table' && (
                    <>
                        <p className="text-xs text-om-faint mb-2">
                            {__('Click a row to change production status. Use "Z1" or "Z2" columns to enter produced quantities per shift.')}
                        </p>
                        {visible.length === 0 ? (
                            <div className="bg-om-card border border-om-line rounded-xl text-center py-16">
                                <p className="text-om-faint text-lg">{__('No work orders found')}</p>
                            </div>
                        ) : (
                            <AppDataTable
                                data={visible}
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
                    </>
                )}
            </div>

            <ExecutionPanel
                workOrderId={selectedId}
                fallback={selected}
                labelTemplates={labelTemplates}
                onReportIssue={issueTypes.length > 0 ? openReport : null}
                quantityAction={quantityAction}
                mobileOpen={mobileOpen}
                onClose={closeMobile}
            />
            </div>

            {/* Modals */}
            <StartModal modal={startModal} onClose={() => setStartModal({ open: false })} />
            <CompleteModal modal={completeModal} onClose={() => setCompleteModal({ open: false })} />
            <InfoModal info={infoModal} onClose={() => setInfoModal(null)} />
            {issueTypes.length > 0 && (
                <ReportIssueDialog workOrder={reportFor} issueTypes={issueTypes} onClose={() => setReportFor(null)} />
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

    // A whole line's benches would push the list off the screen: past a few,
    // the panel folds to one line of per-state counts until it is opened.
    const [open, setOpen] = useState(machines.length <= 4);
    const counts = machines.reduce((acc, m) => ({ ...acc, [m.state ?? '—']: (acc[m.state ?? '—'] ?? 0) + 1 }), {});

    return (
        <div className="mb-4 bg-om-card border border-om-line rounded-xl px-3.5 py-2.5">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <p className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint m-0">{label}</p>
                {!open && Object.entries(counts).map(([state, n]) => (
                    <span key={state} className="inline-flex items-center gap-1.5 text-[12px] text-om-muted">
                        <span className={`w-2 h-2 rounded-full ${MACHINE_STATE_DOT[state] ?? 'bg-om-faintest'}`} />
                        {MACHINE_STATE_LABELS[state] ? __(MACHINE_STATE_LABELS[state]) : state} <span className="font-mono text-om-ink">{n}</span>
                    </span>
                ))}
                {machines.length > 4 && (
                    <button type="button" onClick={() => setOpen((v) => !v)} aria-expanded={open}
                            className="ml-auto text-[12px] font-medium text-om-accent hover:text-om-ink cursor-pointer">
                        {open ? __('Hide') : __('Set state')}
                    </button>
                )}
            </div>
            {open && <div className="flex flex-wrap gap-3 mt-2">
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
            </div>}
            {dialog}
        </div>
    );
}

Workstation.layout = (page) => <OperatorLayout flush>{page}</OperatorLayout>;
