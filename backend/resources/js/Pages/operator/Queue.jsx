import { useState, useEffect, useMemo } from 'react';
import { __ } from '../../lib/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, Icon, ProgressBar, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import OperatorLayout from '../../layouts/OperatorLayout';
import LineSync from '../../components/LineSync';
import AppDataTable from '../../components/AppDataTable';
import Tooltip from '../../components/Tooltip';
import QuantityField from '../../components/QuantityField';
import { formatDate, formatNumber, formatTime } from '../../lib/i18n';
import ExecutionPanel from '../../components/operator/ExecutionPanel';
import StationPicker from '../../components/operator/StationPicker';
import ReportIssueDialog from '../../components/operator/ReportIssueDialog';
import ListToolbar, { useOrderSelection, useStoredState } from '../../components/operator/ListToolbar';
import {
    applyFilter, batchLabel, currentBatch, dueInfo, groupOrders, orderSearchText, pillCls, priorityTag, progressOf,
} from '../../components/operator/operatorUi';

// Layout ref: OpenMES Operator.dc.html (queue view + execution panel). The
// grouped list is the default view; the table and cards stay beside it.

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
    return <StatusPill status={map[status] ?? 'pending'} label={woStatusLabel(status)} />;
}

/** The translated status word — the badge's text and the table's filter/search value. */
function woStatusLabel(status) {
    const label = {
        PENDING:     __('Pending'),
        IN_PROGRESS: __('In Progress'),
        ON_HOLD:     __('On Hold'),
        DONE:        __('Done'),
        CANCELLED:   __('Cancelled'),
    };
    return label[status] ?? status;
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
                            <Icon name="x" size={20} />
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="px-5 py-4">
                            <div className={monoLabelCls}>
                                {__("Produced quantity")} <span className="text-om-blocked">*</span>
                            </div>
                            <QuantityField variant="big" aria-label={__("Produced quantity")}
                                   value={qty}
                                   onChange={setQty}
                                   className="w-full rounded-om-sm border border-om-line bg-om-bg font-mono text-3xl font-medium text-center py-4 text-om-ink outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/20"
                                   placeholder="0" min="0" step="0.01" required autoFocus />
                            <p className="text-xs text-om-faint mt-1.5">{__("Enter the number of units actually produced.")}</p>
                        </div>

                        {/* footer — §09 panel */}
                        <div className="flex gap-3 px-5 py-3.5 bg-om-panel border-t border-om-line2">
                            <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px]">
                                {__("Cancel")}
                            </Button>
                            <Button variant="accent" type="submit"
                                    disabled={processing || qty === '' || parseFloat(qty) < 0}
                                    className="flex-1 px-6 py-4 text-[15px]">
                                <Icon name="check" size={16} />
                                {__("Mark as Done")}
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
                            <p className="mt-[3px] text-sm text-om-muted">{__("Record a production stoppage for this line")}</p>
                        </div>
                        <button type="button" onClick={onClose}
                                className="p-2 text-om-faint hover:text-om-ink rounded-om-sm hover:bg-om-chip transition-colors">
                            <Icon name="x" size={20} />
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="px-5 py-4 space-y-4">
                            {/* Reason */}
                            <div>
                                <div className={monoLabelCls}>
                                    {__("Reason")} <span className="text-om-blocked">*</span>
                                </div>
                                <Dropdown
                                    aria-label={__("Reason")}
                                    options={downtimeReasons.map((r) => ({ value: String(r.id), label: r.name }))}
                                    value={form.data.reason_id == null ? '' : String(form.data.reason_id)}
                                    onChange={(v) => form.setData('reason_id', v)}
                                    placeholder={__("— Select reason —")}
                                    className="w-full"
                                />
                                {form.errors.reason_id && (
                                    <p className="mt-1 text-xs text-om-blocked">{form.errors.reason_id}</p>
                                )}
                            </div>

                            {/* Notes */}
                            <div>
                                <div className={monoLabelCls}>
                                    {__("Notes")} <span className="text-om-faintest normal-case tracking-normal">({__("optional")})</span>
                                </div>
                                <textarea aria-label={__("Notes")} name="notes"
                                          value={form.data.notes}
                                          onChange={(e) => form.setData('notes', e.target.value)}
                                          rows={3}
                                          className={`${inputCls} resize-none`}
                                          placeholder={__("Additional context…")}
                                          maxLength={2000} />
                            </div>
                        </div>

                        {/* footer — §09 panel */}
                        <div className="flex gap-3 px-5 py-3.5 bg-om-panel border-t border-om-line2">
                            <Button variant="secondary" onClick={onClose} className="flex-1 px-6 py-4 text-[15px]">
                                {__("Cancel")}
                            </Button>
                            <Button variant="danger" type="submit"
                                    disabled={form.processing || !form.data.reason_id}
                                    className="flex-1 px-6 py-4 text-[15px]">
                                <Icon name="circle-pause" size={16} />
                                {__("Start Downtime")}
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

// ─── ACTIVE WORK ORDER CARD ───────────────────────────────────────────────────

function ActiveWoCard({ wo, lineStatuses, workflowMode, doneStatusIds, onReport, onDoneQty }) {
    const handleSelectChange = (v) => {
        const selectedId = v ? parseInt(v) : null;
        if (workflowMode === 'board_status' && selectedId !== null && doneStatusIds.map(Number).includes(selectedId)) {
            if (wo.uses_step_ledger) { router.visit(`/operator/work-order/${wo.id}`); return; }
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
                <div className="flex items-center gap-2">
                    {(wo.counting_source === 'machine' || wo.counting_source === 'both') && (
                        <span title={__('Produced quantity is recorded automatically from the machine.')}
                              className="inline-flex items-center gap-1 rounded-full bg-om-chip px-2 py-0.5 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-muted">
                            <Icon name="settings" size={12} />
                            {__('Machine')}
                        </span>
                    )}
                    <WoStatusBadge status={wo.status} />
                </div>
            </div>

            {lineStatuses.length > 0 && (
                <div className="mb-3" onClick={(e) => e.stopPropagation()}>
                    <p className={monoLabelCls}>{__("Board Status")}</p>
                    <Dropdown
                        options={[{ value: '', label: __("— none —") }, ...lineStatuses.map((ls) => ({ value: String(ls.id), label: ls.name }))]}
                        value={wo.line_status_id == null ? '' : String(wo.line_status_id)}
                        onChange={handleSelectChange}
                        className="w-full"
                    />
                </div>
            )}

            <Link href={`/operator/work-order/${wo.id}`} className="block">
                <div className="mb-3">
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__("Product")}</p>
                    <p className="text-[15px] font-medium text-om-ink">{wo.product_type?.name ?? '—'}</p>
                </div>
                <div className="mb-3">
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__("Quantity")}</p>
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
                    <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__("Batches")}</p>
                    <p className="font-mono text-[15px] font-medium text-om-ink">{wo.batches ? wo.batches.length : 0}</p>
                </div>
                <div className="border-t border-om-line2 pt-3 mt-3 flex justify-between items-center text-sm">
                    <span className="text-om-muted">
                        {__("Priority")}: <span className="font-mono font-medium text-om-ink">{wo.priority || '—'}</span>
                    </span>
                    {wo.due_date && (
                        <span className="text-om-muted">
                            {__("Due")}: <span className="font-mono font-medium text-om-ink">{fmtDate(wo.due_date, 'short')}</span>
                        </span>
                    )}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <Button variant="danger"
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); onReport({ woId: wo.id, woNo: wo.order_no }); }}
                            className="gap-1 text-xs"
                            title={__("Report issue")}>
                        <Icon name="triangle-alert" size={16} />
                        {__("Report")}
                    </Button>
                    <span className="flex items-center gap-1 text-om-accent text-sm font-medium">
                        {__("View Details")}
                        <Icon name="chevron-right" size={20} />
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
        header: () => __("Order No"),
        cell: ({ row }) => (
            <span className="font-mono text-[13px] font-semibold text-om-muted whitespace-nowrap">{row.original.order_no}</span>
        ),
    },
    {
        id: 'product',
        accessorFn: (r) => r.product_type?.name ?? '—',
        header: () => __("Product"),
        cell: ({ row }) => (
            <span className="text-[13.5px] text-om-muted">{row.original.product_type?.name ?? '—'}</span>
        ),
    },
    {
        id: 'produced',
        accessorKey: 'produced_qty',
        header: () => __("Produced"),
        cell: ({ row }) => (
            <span className="font-mono text-[13px] font-medium text-om-ink">{fmtQty(row.original.produced_qty)}</span>
        ),
    },
    {
        id: 'completed_at',
        accessorKey: 'completed_at',
        header: () => __("Completed at"),
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
            <Icon name="chevron-right" size={20} className="text-om-faintest inline" />
        ),
    },
];

// The active orders table: the app's DataTable (search, per-column filters,
// column picker) instead of a hand-rolled <table>. Columns depend on the line's
// board statuses and the modal openers, so they are built inside the page.
function activeColumns({ lineStatuses, cycleStatus, onReport }) {
    // String headers (built per render, so the catalogue is loaded): the column
    // picker names a column by its header only when that is a string.
    const link = (wo, children, cls = '') => <Link href={`/operator/work-order/${wo.id}`} className={`hover:text-om-accent ${cls}`}>{children}</Link>;
    return [
        {
            id: 'order_no',
            accessorKey: 'order_no',
            header: __("Order No"),
            cell: ({ row }) => link(row.original, row.original.order_no, 'font-mono text-[13px] font-semibold text-om-ink whitespace-nowrap'),
        },
        {
            id: 'status',
            accessorFn: (r) => woStatusLabel(r.status),
            header: __("Status"),
            cell: ({ row }) => <WoStatusBadge status={row.original.status} />,
        },
        ...(lineStatuses.length > 0 ? [{
            id: 'board_status',
            accessorFn: (r) => r.line_status?.name ?? '—',
            header: __("Board Status"),
            cell: ({ row }) => (
                <button type="button" className="cursor-pointer" onClick={() => cycleStatus(row.original)} title={__("Tap to cycle status")}>
                    <BoardStatusBadge lineStatus={row.original.line_status ?? null} />
                </button>
            ),
        }] : []),
        {
            id: 'product',
            accessorFn: (r) => r.product_type?.name ?? '—',
            header: __("Product"),
            cell: ({ row }) => link(row.original, row.original.product_type?.name ?? '—', 'text-[13.5px] font-medium text-om-ink'),
        },
        {
            id: 'qty',
            accessorFn: (r) => parseFloat(r.produced_qty) || 0,
            header: __("Qty (done / planned)"),
            meta: { filter: false },
            cell: ({ row }) => {
                const wo = row.original;
                const planned = parseFloat(wo.planned_qty) || 0;
                const produced = parseFloat(wo.produced_qty) || 0;
                const pct = planned > 0 ? Math.min((produced / planned) * 100, 100) : 0;
                return link(wo, (
                    <>
                        <span className="font-mono text-[13px] font-medium text-om-ink whitespace-nowrap">{fmtQty(produced)} / {fmtQty(planned)}</span>
                        {planned > 0 && (
                            <>
                                <span className="font-mono text-[10px] text-om-faint ml-1">({fmtQty(pct)}%)</span>
                                <ProgressBar value={pct} className="mt-1.5 w-24" />
                            </>
                        )}
                    </>
                ), 'block');
            },
        },
        {
            id: 'batches',
            accessorFn: (r) => (r.batches ? r.batches.length : 0),
            header: __("Batches"),
            meta: { align: 'center', filter: false },
            cell: ({ row }) => <span className="font-mono text-[13px] text-om-muted">{row.original.batches ? row.original.batches.length : 0}</span>,
        },
        {
            id: 'priority',
            accessorFn: (r) => r.priority ?? 0,
            header: __("Priority"),
            meta: { align: 'center', filter: false },
            cell: ({ row }) => <span className="font-mono text-[13px] text-om-muted">{row.original.priority || '—'}</span>,
        },
        {
            id: 'due_date',
            accessorKey: 'due_date',
            header: __("Due"),
            meta: { filter: 'date' },
            cell: ({ row }) => <span className="font-mono text-[12px] text-om-muted whitespace-nowrap">{fmtDate(row.original.due_date, 'short')}</span>,
        },
        {
            id: '_actions',
            header: __("Actions"),
            enableSorting: false,
            enableHiding: false,
            meta: { align: 'right', chrome: true },
            cell: ({ row }) => {
                const wo = row.original;
                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button variant="danger" size="sm" onClick={() => onReport({ woId: wo.id, woNo: wo.order_no })} title={__("Report issue")}>
                            {__("Report")}
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => router.visit(`/operator/work-order/${wo.id}`)} aria-label={__("Open work order")} title={__("Open work order")}>
                            <Icon name="chevron-right" size={20} />
                        </Button>
                    </div>
                );
            },
        },
    ];
}


// ─── LIST VIEW (design: grouped queue) ───────────────────────────────────────

/** The step a batch is on: running, else ready, else the first not finished. */
function batchCurrentStep(batch) {
    const steps = [...(batch?.steps ?? [])].sort((a, b) => a.step_number - b.step_number);
    return steps.find((s) => s.status === 'IN_PROGRESS') ?? steps.find((s) => s.status === 'READY')
        ?? steps.find((s) => !['DONE', 'SKIPPED'].includes(s.status)) ?? null;
}

/** The row's own button names the next move; it opens the order in the panel. */
function primaryOf(status) {
    if (status === 'DONE') return { label: __('Completed'), cls: 'bg-om-chip text-om-faint' };
    if (status === 'BLOCKED') return { label: __('Blocked'), cls: 'bg-om-chip text-om-blocked' };
    if (status === 'IN_PROGRESS') return { label: __('Open'), cls: 'bg-om-ink text-om-on-ink' };
    if (['PAUSED', 'CHANGE_HOLD', 'ON_HOLD'].includes(status)) return { label: __('Resume'), cls: 'bg-om-accent text-white' };
    return { label: __('Start'), cls: 'bg-om-accent text-white' };
}

function QueueRow({ wo, pos, selected, onSelect, lineStatuses, cycleStatus }) {
    const prio = priorityTag(wo.priority);
    const { planned, produced, remaining, pct } = progressOf(wo);
    const due = dueInfo(wo.due_date ?? wo.completed_at, wo.status);
    const batch = currentBatch(wo);
    const step = wo.uses_step_ledger ? batchCurrentStep(batch) : null;
    const primary = primaryOf(wo.status);
    const done = wo.status === 'DONE';
    return (
        <div
            role="button"
            tabIndex={0}
            aria-pressed={selected}
            onClick={() => onSelect(wo.id)}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSelect(wo.id); } }}
            onDoubleClick={() => router.visit(`/operator/work-order/${wo.id}`)}
            data-testid={`queue-row-${wo.id}`}
            className={`grid grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)_96px] md:grid-cols-[32px_minmax(0,1.5fr)_minmax(140px,1.2fr)_64px_96px] lg:grid-cols-[32px_minmax(180px,1.5fr)_minmax(120px,1fr)_minmax(160px,1.2fr)_72px_100px] gap-3 lg:gap-4 items-center px-[18px] py-3.5 border-t border-om-line2 first:border-t-0 cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-om-accent/40 ${
                selected ? 'bg-om-accent-bg' : 'hover:bg-om-panel'
            } ${done ? 'opacity-60' : ''}`}
        >
            <span className="hidden md:block font-mono text-[11.5px] text-om-faint">{String(pos).padStart(2, '0')}</span>
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <span className="text-[14.5px] font-semibold text-om-ink truncate">{wo.product_type?.name ?? '—'}</span>
                    {prio && <span className={`shrink-0 font-mono text-[8.5px] font-semibold border rounded px-[5px] py-px ${prio.cls}`}>{prio.label}</span>}
                    {(wo.counting_source === 'machine' || wo.counting_source === 'both') && (
                        <span title={__('Produced quantity is recorded automatically from the machine.')}
                              className="shrink-0 rounded-full bg-om-chip px-1.5 py-px font-mono text-[8.5px] uppercase tracking-[0.08em] text-om-muted">
                            {__('Machine')}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-1.5 mt-[3px] min-w-0">
                    <Link href={`/operator/work-order/${wo.id}`} onClick={(e) => e.stopPropagation()}
                          className="font-mono text-[10.5px] text-om-faint hover:text-om-accent truncate">
                        {wo.order_no}
                    </Link>
                    {lineStatuses.length > 0 && !done && (
                        <button type="button" className="shrink-0 cursor-pointer" title={__('Tap to cycle status')}
                                onClick={(e) => { e.stopPropagation(); cycleStatus(wo); }}>
                            <BoardStatusBadge lineStatus={wo.line_status ?? null} />
                        </button>
                    )}
                </div>
                {step && <div className="lg:hidden text-[11px] text-om-accent truncate mt-0.5">{__('Step')} {step.step_number}: {step.name}</div>}
            </div>
            {/* Batch and step get their own column from 1024 px; on a tablet the step sits under the order number */}
            <div className="hidden lg:block min-w-0">
                <div className="font-mono text-[11px] text-om-muted truncate">
                    {batchLabel(batch)}{(wo.batches?.length ?? 0) > 1 ? ` · ${wo.batches.length}` : ''}
                </div>
                {step && <div className="text-[11px] text-om-accent truncate mt-0.5">{__('Step')} {step.step_number}: {step.name}</div>}
            </div>
            <div className="min-w-0">
                <div className="flex justify-between items-baseline mb-1.5 gap-2">
                    <span className="font-mono text-[13px] text-om-ink whitespace-nowrap">{fmtQty(produced)}<span className="text-om-faint"> / {fmtQty(planned)}</span></span>
                    <span className={`text-[11px] font-semibold whitespace-nowrap ${remaining === 0 ? 'text-om-running' : 'text-om-accent'}`}>
                        {remaining === 0 ? __('complete') : __(':count left', { count: fmtQty(remaining) })}
                    </span>
                </div>
                <ProgressBar value={pct} />
            </div>
            <span className={`hidden md:block font-mono text-[12.5px] text-right ${due.hot ? 'text-om-blocked font-semibold' : 'text-om-muted'}`}>{due.label}</span>
            <button type="button" onClick={(e) => { e.stopPropagation(); onSelect(wo.id); }}
                    className={`text-center text-[13px] font-semibold rounded-[9px] py-2.5 cursor-pointer ${primary.cls}`}>
                {primary.label}
            </button>
        </div>
    );
}

function QueueList({ orders, selectedId, onSelect, lineStatuses, cycleStatus }) {
    const groups = groupOrders(orders);
    if (!groups.length) {
        return <div className="bg-om-card border border-om-line rounded-xl text-center py-9 text-[13px] text-om-faint">{__('No work orders match.')}</div>;
    }
    return groups.map((g) => (
        <section key={g.key} className="mb-5" aria-label={g.label}>
            <div className="flex items-center gap-2 mx-0.5 mb-[9px]">
                <span className={`w-2 h-2 rounded-full ${g.dot}`} />
                <span className="text-[13px] font-semibold text-om-ink">{g.label}</span>
                <span className="font-mono text-[11px] text-om-faint">{g.rows.length}</span>
            </div>
            <div className="bg-om-card border border-om-line rounded-xl overflow-hidden">
                {g.rows.map((wo, i) => (
                    <QueueRow key={wo.id} wo={wo} pos={i + 1} selected={wo.id === selectedId} onSelect={onSelect}
                              lineStatuses={lineStatuses} cycleStatus={cycleStatus} />
                ))}
            </div>
        </section>
    ));
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
        workstationNotStarted = [],
        lineWorkstations = [],
        downtimeReasons = [],
        activeDowntime = null,
        stationPicker = [],
    } = usePage().props;

    // View, filter: remembered in the browser (the list is the design's view;
    // table and cards are the earlier ones, kept beside it).
    const [view, setView] = useStoredState('operatorQueueView', 'list');
    const [filter, setFilter] = useStoredState('operatorQueueFilter', 'all');
    const [query, setQuery] = useState('');

    // Report issue modal state
    const [reportFor, setReportFor] = useState(null);

    // Done qty modal state (board_status mode)
    const [doneQtyModal, setDoneQtyModal] = useState({ open: false, woId: null, woNo: '', statusId: null });

    // Downtime modal state
    const [downtimeModalOpen, setDowntimeModalOpen] = useState(false);

    const allOrders = useMemo(() => {
        const seen = new Set();
        return [...activeWorkOrders, ...completedWorkOrders].filter((wo) => (seen.has(wo.id) ? false : seen.add(wo.id)));
    }, [activeWorkOrders, completedWorkOrders]);
    // With a station picked, the list shows that station's orders only — ready or
    // running there, or about to start there (routing may bring them from another
    // line); the table and cards keep the whole line beside the station cards.
    const showWorkstationQueue =
        selectedWorkstation &&
        ['per_operation', 'hybrid'].includes(trackingMode);
    const stationOrders = useMemo(() => {
        if (!showWorkstationQueue) return null;
        const seen = new Set();
        return [...workstationQueue, ...workstationNotStarted].filter((wo) => (seen.has(wo.id) ? false : seen.add(wo.id)));
    }, [showWorkstationQueue, workstationQueue, workstationNotStarted]);
    const listSource = view === 'list' && stationOrders ? stationOrders : allOrders;
    const visible = useMemo(() => applyFilter(listSource, filter, query, orderSearchText), [listSource, filter, query]);
    const visibleIds = useMemo(() => new Set(visible.map((wo) => wo.id)), [visible]);
    const visibleActive = activeWorkOrders.filter((wo) => visibleIds.has(wo.id));
    const visibleCompleted = completedWorkOrders.filter((wo) => visibleIds.has(wo.id));

    const { selectedId, select, mobileOpen, closeMobile } = useOrderSelection(listSource);
    const selected = listSource.find((wo) => wo.id === selectedId) ?? allOrders.find((wo) => wo.id === selectedId) ?? null;

    // Callers pass { woId, woNo } (cards, table) — the dialog wants the order.
    const openReport = ({ woId, woNo, id, order_no, product }) => {
        const wo = allOrders.find((o) => o.id === (woId ?? id));
        setReportFor({ id: woId ?? id, order_no: woNo ?? order_no, product: product ?? wo?.product_type?.name });
    };

    const openDoneQty = ({ woId, woNo, statusId }) => setDoneQtyModal({ open: true, woId, woNo, statusId });
    const closeDoneQty = () => setDoneQtyModal((s) => ({ ...s, open: false }));

    // Tapping the board-status badge walks the line's statuses in order (none → first → … → none);
    // a "done" status asks for the quantity first, or opens the order when it keeps a step ledger.
    const cycleStatus = (wo) => {
        if (!lineStatuses.length) return;
        const currentId = wo.line_status_id ? parseInt(wo.line_status_id) : null;
        const ids = [null, ...lineStatuses.map((s) => parseInt(s.id))];
        const nextId = ids[(ids.indexOf(currentId) + 1) % ids.length];
        if (workflowMode === 'board_status' && nextId !== null && doneStatusIds.map(Number).includes(nextId)) {
            if (wo.uses_step_ledger) { router.visit(`/operator/work-order/${wo.id}`); return; }
            openDoneQty({ woId: wo.id, woNo: wo.order_no, statusId: nextId });
            return;
        }
        router.post(`/operator/work-order/${wo.id}/line-status`, { line_status_id: nextId ?? '' });
    };
    const activeCols = useMemo(() => activeColumns({ lineStatuses, cycleStatus, onReport: openReport }), [lineStatuses, workflowMode, doneStatusIds, allOrders]); // eslint-disable-line react-hooks/exhaustive-deps

    const showWorkstationFilter =
        trackingMode !== 'cumulative' && lineWorkstations.length > 0 && stationPicker.length > 0;

    const trackingBadgeClass =
        trackingMode === 'per_operation' ? 'text-om-running bg-om-running-bg' :
        trackingMode === 'hybrid'        ? 'text-om-downtime bg-om-downtime-bg' :
                                           'text-om-pending bg-om-pending-bg';

    const trackingLabel =
        trackingMode === 'per_operation' ? __("Per Operation") :
        trackingMode === 'hybrid'        ? __("Hybrid") : __("Cumulative");

    return (
        <>
            <Head title={__("Work Order Queue")} />

            {/* Live sync */}
            <LineSync lineId={line.id} reloadOnly={['activeWorkOrders', 'completedWorkOrders', 'workstationQueue', 'workstationNotStarted', 'shiftSummary']} />

            <div className="h-full flex">
            <div className="flex-1 min-w-0 overflow-auto px-4 md:px-[22px] pt-[18px] pb-10">

                {/* ── Downtime bar ── */}
                {activeDowntime && (
                    <div className="mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-om border border-om-blocked/30 bg-om-blocked-bg px-4 py-3">
                        <div className="flex items-center gap-2 flex-1 min-w-0">
                            <span className="flex-shrink-0 w-2.5 h-2.5 rounded-full bg-om-blocked animate-om-pulse" />
                            <span className="text-sm font-semibold text-om-blocked truncate">
                                {__("Downtime in progress")} &mdash; {activeDowntime.reason.name}
                            </span>
                            <span className="hidden sm:inline font-mono text-[11px] text-om-blocked/70 whitespace-nowrap">
                                ({__("since")} {fmtDate(activeDowntime.started_at, 'long')})
                            </span>
                        </div>
                        <span className="sm:hidden font-mono text-[11px] text-om-blocked/70">
                            {__("since")} {fmtDate(activeDowntime.started_at, 'long')}
                        </span>
                        {activeDowntime.notes && (
                            <span className="text-xs text-om-blocked/80 italic truncate max-w-xs hidden lg:inline">
                                {activeDowntime.notes}
                            </span>
                        )}
                        <Button variant="primary"
                                onClick={() => router.post('/operator/downtime/' + activeDowntime.id + '/stop', {}, { preserveScroll: true })}
                                className="flex-shrink-0 px-5 py-3 text-sm">
                            <Icon name="circle-stop" size={16} />
                            {__("Stop Downtime")}
                        </Button>
                    </div>
                )}

                <ListToolbar
                    leading={showWorkstationFilter && (
                        <StationPicker
                            stations={stationPicker}
                            selectedId={selectedWorkstation?.id ?? null}
                            onPick={(id) => router.get('/operator/queue', { workstation: id ?? 'all' }, { preserveState: false })}
                            footer={(
                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[20px] font-mono text-[9.5px] uppercase tracking-[0.06em] ${trackingBadgeClass}`}>
                                    {trackingLabel}
                                </span>
                            )}
                        />
                    )}
                    query={query} onQuery={setQuery}
                    filter={filter} onFilter={setFilter}
                    count={visible.length}
                    views={[['list', __('List')], ['table', __('Table')], ['cards', __('Cards')]]}
                    view={view} onView={setView}
                >
                    {!activeDowntime && downtimeReasons.length > 0 && (
                        <button type="button"
                                onClick={() => setDowntimeModalOpen(true)}
                                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-[9px] border border-om-downtime/30 bg-om-downtime-bg hover:brightness-95 text-om-downtime text-[12.5px] font-semibold transition-all cursor-pointer">
                            <Icon name="circle-pause" size={16} />
                            {__("Report Downtime")}
                        </button>
                    )}
                </ListToolbar>

                {/* ── Workstation queue (filtered) ── */}
                {view !== 'list' && showWorkstationQueue && workstationQueue.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-[13px] font-semibold text-om-ink mb-[9px]">
                            {__("Ready at :station", { station: selectedWorkstation.name })}
                            <span className="font-mono text-[11px] font-normal text-om-faint ml-2">{workstationQueue.length}</span>
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            {workstationQueue.map((wo) => {
                                // Find the batch whose current step is at this workstation
                                const currentBatchHere = (wo.batches ?? []).find((b) =>
                                    b.steps && b.steps.some((s) => s.workstation_id != null &&
                                        String(s.workstation_id) === String(selectedWorkstation.id) &&
                                        (s.status === 'READY' || s.status === 'IN_PROGRESS'))
                                ) ?? null;
                                const currentStep = currentBatchHere
                                    ? (currentBatchHere.steps ?? []).find((s) =>
                                        String(s.workstation_id) === String(selectedWorkstation.id) &&
                                        (s.status === 'READY' || s.status === 'IN_PROGRESS'))
                                    : null;

                                return (
                                    <button key={wo.id}
                                          type="button"
                                          onClick={() => select(wo.id)}
                                          onDoubleClick={() => router.visit(`/operator/work-order/${wo.id}`)}
                                          className={`block text-left p-4 rounded-xl border bg-om-card hover:bg-om-panel transition-colors cursor-pointer ${wo.id === selectedId ? 'border-om-accent' : 'border-om-line'}`}>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-mono text-[13px] font-semibold text-om-ink">{wo.order_no}</span>
                                            <StatusPill
                                                status={currentStep?.status === 'IN_PROGRESS' ? 'running' : 'pending'}
                                                label={currentStep?.status === 'IN_PROGRESS' ? __('In Progress') : __('Ready')}
                                            />
                                        </div>
                                        <div className="text-sm font-medium text-om-ink">
                                            {wo.product_type?.name ?? '-'}
                                        </div>
                                        {currentStep && (
                                            <div className="mt-2 text-xs text-om-accent font-medium">
                                                {__("Step")} {currentStep.step_number}: {currentStep.name}
                                            </div>
                                        )}
                                        <div className="mt-1 font-mono text-[10px] uppercase tracking-[0.06em] text-om-faint">
                                            {__("Qty")}: {wo.planned_qty} &middot; {__("Batch")} #{currentBatchHere?.batch_number}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* ── Not yet started, routing begins at this workstation ── */}
                {view !== 'list' && showWorkstationQueue && workstationNotStarted.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-[13px] font-semibold text-om-ink mb-[9px]">
                            {__("To start at :station", { station: selectedWorkstation.name })}
                            <span className="font-mono text-[11px] font-normal text-om-faint ml-2">{workstationNotStarted.length}</span>
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            {workstationNotStarted.map((wo) => {
                                // When the first step has alternatives (a variant group), show the
                                // one at this workstation — that's why the order is listed here.
                                const steps = [...(wo.process_snapshot?.steps ?? [])]
                                    .sort((a, b) => a.step_number - b.step_number);
                                const firstCandidate = steps[0] ?? null;
                                const firstStep = firstCandidate?.variant_group == null
                                    ? firstCandidate
                                    : steps.find((step) =>
                                        step.variant_group === firstCandidate.variant_group &&
                                        String(step.workstation_id) === String(selectedWorkstation.id),
                                    ) ?? firstCandidate;

                                return (
                                    <button key={wo.id}
                                          type="button"
                                          onClick={() => select(wo.id)}
                                          onDoubleClick={() => router.visit(`/operator/work-order/${wo.id}`)}
                                          className={`block text-left p-4 rounded-xl border bg-om-card hover:bg-om-panel transition-colors cursor-pointer ${wo.id === selectedId ? 'border-om-accent' : 'border-om-line'}`}>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-mono text-[13px] font-semibold text-om-ink">{wo.order_no}</span>
                                            <StatusPill status="pending" label={__('Not Started')} />
                                        </div>
                                        <div className="text-sm font-medium text-om-ink">
                                            {wo.product_type?.name ?? '-'}
                                        </div>
                                        {firstStep && (
                                            <div className="mt-2 text-xs text-om-muted font-medium">
                                                {__("Step")} {firstStep.step_number}: {firstStep.name}
                                            </div>
                                        )}
                                        <div className="mt-1 font-mono text-[10px] uppercase tracking-[0.06em] text-om-faint">
                                            {__("Qty")}: {wo.planned_qty}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {view !== 'list' && showWorkstationQueue && workstationQueue.length === 0 && workstationNotStarted.length === 0 && (
                    <div className="mb-6 p-6 rounded-xl border border-om-line bg-om-card text-center">
                        <p className="text-sm text-om-muted">
                            {__("No work orders currently waiting at")} <strong className="text-om-ink">{selectedWorkstation.name}</strong>
                        </p>
                    </div>
                )}


                {/* ── List view (grouped by status) ── */}
                {view === 'list' && (
                    <QueueList orders={visible} selectedId={selectedId} onSelect={select}
                               lineStatuses={lineStatuses} cycleStatus={cycleStatus} />
                )}

                {view !== 'list' && (
                <>
                {/* ── Active Work Orders ── */}
                <div className="mb-8">
                    <h2 className="text-[13px] font-semibold text-om-ink mb-[9px]">{__("Active Work Orders")}<span className="font-mono text-[11px] font-normal text-om-faint ml-2">{visibleActive.length}</span>
                    </h2>

                    {visibleActive.length === 0 ? (
                        <div className="bg-om-card border border-om-line rounded-xl text-center py-12">
                            <Icon name="file-text" size={48} className="block mx-auto text-om-faintest" />
                            <h3 className="mt-2 text-sm font-medium text-om-ink">{__("No active work orders")}</h3>
                            <p className="mt-1 text-sm text-om-muted">{__("There are no work orders currently in progress on this line.")}</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <AppDataTable
                                    data={visibleActive}
                                    columns={activeCols}
                                    getRowId={(wo) => String(wo.id)}
                                    pageSize={10}
                                    onRowClick={(wo) => select(wo.id)}
                                    onRowDoubleClick={(wo) => router.visit(`/operator/work-order/${wo.id}`)}
                                />
                            )}

                            {/* Card view */}
                            {view === 'cards' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-6">
                                    {visibleActive.map((wo) => (
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
                    <h2 className="text-[13px] font-semibold text-om-ink mb-[9px]">
                        {__("Recently Completed")}
                        <span className="font-mono text-[11px] font-normal text-om-faint ml-2">{visibleCompleted.length}</span>
                    </h2>

                    {visibleCompleted.length === 0 ? (
                        <div className="bg-om-card border border-om-line rounded-xl text-center py-8">
                            <p className="text-sm text-om-muted">{__("No recently completed work orders")}</p>
                        </div>
                    ) : (
                        <>
                            {/* Table view */}
                            {view === 'table' && (
                                <DataTable
                                    data={visibleCompleted}
                                    columns={completedColumns}
                                    searchable={false}
                                    columnToggle={false}
                                    paginated={false}
                                    onRowClick={(wo) => router.visit(`/operator/work-order/${wo.id}`)}
                                />
                            )}

                            {/* Card view */}
                            {view === 'cards' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-6">
                                    {visibleCompleted.map((wo) => (
                                        <Link key={wo.id}
                                              href={`/operator/work-order/${wo.id}`}
                                              className="block bg-om-card border border-om-line rounded-om p-6 cursor-pointer transition-opacity opacity-70 hover:opacity-100">
                                            <div className="flex items-center justify-between mb-4">
                                                <h3 className="font-mono text-[15px] font-semibold text-om-ink">{wo.order_no}</h3>
                                                <StatusPill status="done" label={__("Completed")} />
                                            </div>
                                            <div className="mb-3">
                                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__("Product")}</p>
                                                <p className="text-[15px] font-medium text-om-ink">{wo.product_type?.name ?? '—'}</p>
                                            </div>
                                            <div className="mb-3">
                                                <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__("Completed")}</p>
                                                <p className="font-mono text-[15px] font-medium text-om-ink">{fmtQty(wo.produced_qty, 2)}</p>
                                            </div>
                                            {wo.completed_at && (
                                                <div className="border-t border-om-line2 pt-3 mt-3 text-sm text-om-muted">
                                                    {__("Completed")}: <span className="font-mono text-[12px]">{fmtDate(wo.completed_at, 'long')}</span>
                                                </div>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </div>
                </>
                )}
            </div>

            <ExecutionPanel
                workOrderId={selectedId}
                fallback={selected}
                onReportIssue={issueTypes.length > 0 ? openReport : null}
                mobileOpen={mobileOpen}
                onClose={closeMobile}
            />
            </div>

            {/* ── Modals ── */}
            {issueTypes.length > 0 && (
                <ReportIssueDialog workOrder={reportFor} issueTypes={issueTypes} onClose={() => setReportFor(null)} />
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

Queue.layout = (page) => <OperatorLayout flush>{page}</OperatorLayout>;
