import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, Icon, InlineAlert, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import AppDatePicker from '../../../components/AppDatePicker';
import Tooltip from '../../../components/Tooltip';
import PageTrail from '../../../components/PageTrail';
import useConfirm from '../../../components/useConfirm';
import { __, formatDate, formatNumber } from '../../../lib/i18n';

const MONO = 'var(--font-mono)';

// ─── Formatting ──────────────────────────────────────────────────────────────

/** 25 → "25 min", 90 → "1 h 30 min", 120 → "2 h". */
function minutes(value) {
    if (value == null) return '—';
    const m = Math.round(value);
    if (m < 60) return __(':m min', { m });
    const h = Math.floor(m / 60);
    const rest = m % 60;
    return rest ? __(':h h :m min', { h, m: rest }) : __(':h h', { h });
}

/** "2026-10-19 06:25" → "06:25" (labels are already in plant time). */
const timeOf = (label) => (label ? label.slice(11, 16) : '—');
const dayOf = (label) => (label ? label.slice(0, 10) : null);
const clock = (label) => {
    const [h, m] = timeOf(label).split(':').map(Number);
    return h * 60 + m;
};
const dayName = (iso) => formatDate(iso, { weekday: 'short', day: '2-digit', month: '2-digit' });

/** One reason, worded for a planner: material shortages say what is missing. */
function reasonText(reason) {
    if (reason.code === 'material' && Array.isArray(reason.shortages) && reason.shortages.length) {
        return reason.shortages.map((s) => __(':material: :missing :unit missing (needs :required, :available in stock)', {
            material: s.material_name ?? s.material_code,
            missing: formatNumber(s.missing_qty),
            required: formatNumber(s.required_qty),
            available: formatNumber(s.available_qty),
            unit: s.unit_of_measure ? __(s.unit_of_measure) : '',
        })).join(' · ');
    }

    return reason.message;
}

// ─── Grouping operations into work orders ────────────────────────────────────

/** Where an order stands, from its operations: blocked > late > on time. */
function orderOutcome(ops) {
    const blocked = ops.find((op) => !op.scheduled);
    if (blocked) {
        const reason = blocked.reasons?.find((r) => r.code !== 'predecessor_not_schedulable') ?? blocked.reasons?.[0];
        return { key: 'blocked', pill: 'blocked', label: blocked.status_label, reason: reason ? reasonText(reason) : null };
    }
    if (ops.some((op) => op.status === 'scheduled_late')) {
        return { key: 'late', pill: 'downtime', label: __('Late'), reason: __('Finishes after the order’s due date.') };
    }

    return { key: 'on_time', pill: 'running', label: __('On time'), reason: null };
}

function groupByOrder(operations) {
    const byOrder = new Map();
    for (const op of operations) {
        if (!byOrder.has(op.work_order_id)) byOrder.set(op.work_order_id, []);
        byOrder.get(op.work_order_id).push(op);
    }

    return [...byOrder.values()].map((ops) => {
        const steps = [...ops].sort((a, b) => a.step_number - b.step_number);
        const placed = steps.filter((op) => op.planned_start_label);
        const first = steps[0];

        return {
            id: first.work_order_id,
            orderNo: first.order_no,
            product: first.product_name,
            qty: first.planned_qty,
            unit: first.unit,
            dueDate: first.due_date,
            start: placed.length ? placed.reduce((a, op) => (op.planned_start_label < a ? op.planned_start_label : a), placed[0].planned_start_label) : null,
            end: placed.length ? placed.reduce((a, op) => (op.planned_end_label > a ? op.planned_end_label : a), placed[0].planned_end_label) : null,
            outcome: orderOutcome(steps),
            steps,
        };
    });
}

// A stable colour per order, from the status/accent palette the board uses.
const ORDER_COLOURS = ['var(--om-accepted)', 'var(--om-accent)', 'var(--om-running)', 'var(--om-maint)', 'var(--om-downtime)', '#0e7490', '#a16207', '#be185d'];
const colourOf = (orders, id) => ORDER_COLOURS[orders.findIndex((o) => o.id === id) % ORDER_COLOURS.length];

// ─── Summary ─────────────────────────────────────────────────────────────────

function Summary({ orders }) {
    const count = (key) => orders.filter((o) => o.outcome.key === key).length;
    const ends = orders.map((o) => o.end).filter(Boolean).sort();
    const tiles = [
        [__('Work orders'), orders.length, 'text-om-ink'],
        [__('On time'), count('on_time'), 'text-om-running'],
        [__('Late'), count('late'), count('late') ? 'text-om-downtime' : 'text-om-ink'],
        [__('Blocked'), count('blocked'), count('blocked') ? 'text-om-blocked' : 'text-om-ink'],
    ];

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            {tiles.map(([label, value, tone]) => (
                <div key={label} className="rounded-om-sm border border-om-line bg-om-card px-4 py-3">
                    <div style={{ fontFamily: MONO }} className="text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
                    <div className={`mt-1 text-[22px] font-semibold tracking-[-0.02em] ${tone}`}>{value}</div>
                </div>
            ))}
            <div className="col-span-2 rounded-om-sm border border-om-line bg-om-card px-4 py-3 sm:col-span-1">
                <div style={{ fontFamily: MONO }} className="text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Plan ends')}</div>
                <div className="mt-1 text-[15px] font-semibold text-om-ink">
                    {ends.length ? `${dayName(ends[ends.length - 1].replace(' ', 'T'))} ${timeOf(ends[ends.length - 1])}` : '—'}
                </div>
            </div>
        </div>
    );
}

// ─── Timeline: one row per resource, one bar per operation ───────────────────

function Timeline({ operations, orders, onPick }) {
    const placed = operations.filter((op) => op.scheduled && op.planned_start_label && op.planned_end_label);

    const { days, from, to, rows } = useMemo(() => {
        const dayKeys = [...new Set(placed.flatMap((op) => [dayOf(op.planned_start_label), dayOf(op.planned_end_label)]))].sort();
        const starts = placed.map((op) => clock(op.planned_start_label));
        const ends = placed.map((op) => (dayOf(op.planned_end_label) === dayOf(op.planned_start_label) ? clock(op.planned_end_label) : 24 * 60));
        let lo = Math.floor(Math.min(...starts) / 60) * 60;
        let hi = Math.ceil(Math.max(...ends) / 60) * 60;
        if (hi - lo < 4 * 60) hi = Math.min(24 * 60, lo + 4 * 60);

        const byResource = new Map();
        for (const op of placed) {
            const key = op.workstation_id ? `ws:${op.workstation_id}` : `line:${op.line_id}`;
            if (!byResource.has(key)) byResource.set(key, { key, name: op.workstation_name ?? op.line_name, line: op.workstation_id ? op.line_name : null, ops: [] });
            byResource.get(key).ops.push(op);
        }
        const resourceRows = [...byResource.values()].sort((a, b) => `${a.line ?? ''}${a.name}`.localeCompare(`${b.line ?? ''}${b.name}`));

        return { days: dayKeys, from: lo, to: hi, rows: resourceRows };
    }, [operations]);

    if (!placed.length) return null;

    const span = to - from;
    const hourTicks = [];
    for (let t = from; t <= to; t += 60) hourTicks.push(t);

    /** The part of an operation that falls on one day, as % of that day's window. */
    const segment = (op, day) => {
        const startDay = dayOf(op.planned_start_label);
        const endDay = dayOf(op.planned_end_label);
        if (day < startDay || day > endDay) return null;
        const a = day === startDay ? clock(op.planned_start_label) : from;
        const b = day === endDay ? clock(op.planned_end_label) : to;
        const left = Math.max(0, (a - from) / span) * 100;
        const right = Math.min(1, (b - from) / span) * 100;
        return right > left ? { left, width: right - left } : null;
    };

    return (
        <div className="overflow-x-auto rounded-om-sm border border-om-line bg-om-card">
            <div style={{ minWidth: 220 + days.length * 360 }}>
                <div className="flex border-b border-om-line">
                    <div className="w-[220px] shrink-0 px-3 py-2" style={{ fontFamily: MONO }}>
                        <span className="text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Workstation')}</span>
                    </div>
                    {days.map((day) => (
                        <div key={day} className="flex-1 border-l border-om-line">
                            <div className="px-2 pt-2 text-[12px] font-semibold text-om-ink">{dayName(`${day}T12:00:00`)}</div>
                            <div className="relative h-5">
                                {hourTicks.map((t, i) => (
                                    <span
                                        key={t}
                                        className={`absolute text-[9.5px] text-om-faint ${i === 0 ? 'pl-1' : i === hourTicks.length - 1 ? '-translate-x-full pr-1' : '-translate-x-1/2'}`}
                                        style={{ left: `${((t - from) / span) * 100}%`, fontFamily: MONO }}
                                    >
                                        {String(Math.floor(t / 60)).padStart(2, '0')}
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {rows.map((row) => {
                    const total = row.ops.reduce((sum, op) => sum + (op.duration_minutes ?? 0), 0);

                    return (
                        <div key={row.key} className="flex border-b border-om-line last:border-b-0">
                            <div className="w-[220px] shrink-0 px-3 py-2.5">
                                <div className="text-[13px] font-medium text-om-ink">{row.name}</div>
                                <div className="text-[11px] text-om-muted">{[row.line, minutes(total)].filter(Boolean).join(' · ')}</div>
                            </div>
                            {days.map((day) => (
                                <div key={day} className="relative flex-1 border-l border-om-line">
                                    {hourTicks.map((t) => (
                                        <span key={t} className="absolute inset-y-0 border-l border-dashed border-om-line/60" style={{ left: `${((t - from) / span) * 100}%` }} />
                                    ))}
                                    {row.ops.map((op) => {
                                        const seg = segment(op, day);
                                        if (!seg) return null;
                                        const label = `${op.order_no} · ${op.step_name} · ${timeOf(op.planned_start_label)}–${timeOf(op.planned_end_label)}`;

                                        return (
                                            <Tooltip key={op.id} label={label}>
                                                <button
                                                    type="button"
                                                    onClick={() => onPick?.(op.work_order_id)}
                                                    aria-label={label}
                                                    className="absolute top-2 bottom-2 overflow-hidden rounded-[6px] px-1.5 text-left text-[10.5px] font-semibold text-white"
                                                    style={{
                                                        left: `${seg.left}%`,
                                                        width: `${seg.width}%`,
                                                        background: colourOf(orders, op.work_order_id),
                                                        outline: op.status === 'scheduled_late' ? '2px solid var(--om-blocked)' : 'none',
                                                        fontFamily: MONO,
                                                    }}
                                                >
                                                    <span className="block truncate leading-[22px]">{op.order_no}</span>
                                                </button>
                                            </Tooltip>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Workstation override (manual selection, audited server-side) ────────────

const SOURCE_LABEL = () => ({ fixed: __('fixed'), auto: __('auto'), manual: __('manual'), line: __('whole line') });

function StationCell({ op, stationsByType, canDecide }) {
    const options = op.workstation_type_id ? stationsByType[op.workstation_type_id] ?? [] : [];
    const [value, setValue] = useState(op.workstation_id ? String(op.workstation_id) : '');
    const [error, setError] = useState(null);
    const source = SOURCE_LABEL()[op.resource_source];

    if (!canDecide || !op.scheduled || options.length < 2) {
        return (
            <span className="text-[13px] text-om-ink">
                {op.workstation_name ?? op.line_name ?? '—'}
                {source && <span className="text-[11px] text-om-muted"> · {source}</span>}
            </span>
        );
    }

    // A refused move (station busy in this window, wrong class) comes back as a
    // validation error. Keep this cell's state through that visit so the reason
    // shows next to the select instead of the choice silently snapping back.
    const save = (next) => {
        setValue(next);
        setError(null);
        if (next && next !== String(op.workstation_id)) {
            router.put(`/admin/schedule/operations/${op.id}`, { workstation_id: next }, {
                preserveScroll: true,
                preserveState: 'errors',
                onError: (errors) => {
                    setValue(op.workstation_id ? String(op.workstation_id) : '');
                    setError(errors.workstation_id ?? __('The operation could not be moved.'));
                },
            });
        }
    };

    return (
        <span className="inline-flex flex-col gap-1">
            <span className="inline-flex items-center gap-2">
                <Dropdown
                    size="sm"
                    className="min-w-[200px]"
                    options={options.map((st) => ({ value: String(st.id), label: st.line_name ? `${st.name} · ${st.line_name}` : st.name }))}
                    value={value}
                    onChange={save}
                    aria-label={__('Workstation for :order step :step', { order: op.order_no, step: op.step_number })}
                />
                {source && <span className="text-[11px] text-om-muted">{source}</span>}
            </span>
            {error && <span role="alert" className="text-[11.5px] text-om-blocked">{error}</span>}
        </span>
    );
}

// ─── Work orders with their steps ────────────────────────────────────────────

function SetupNote({ op }) {
    if (!op.setup_minutes) return null;
    const byRule = op.reasons?.some((r) => r.code === 'changeover');

    return byRule ? (
        <span className="text-[11px] font-semibold text-om-accent">{__('incl. :time changeover', { time: minutes(op.setup_minutes) })}</span>
    ) : (
        <span className="text-[11px] text-om-muted">{__('incl. :time set-up', { time: minutes(op.setup_minutes) })}</span>
    );
}

function OrderRow({ order, anchor, open, onToggle, stationsByType, canDecide }) {
    return (
        <>
            <tr id={anchor} className="scroll-mt-24 cursor-pointer hover:bg-om-chip/40" onClick={onToggle}>
                <td className="w-8 py-3 pl-3 text-om-muted">
                    <Icon name={open ? 'chevron-down' : 'chevron-right'} size={14} />
                </td>
                <td className="py-3 pr-3">
                    <div className="text-[13px] font-semibold text-om-ink" style={{ fontFamily: MONO }}>{order.orderNo}</div>
                    <div className="text-[12px] text-om-muted">
                        {[order.product, order.qty != null ? `${formatNumber(order.qty)} ${__(order.unit || 'pcs')}` : null].filter(Boolean).join(' · ')}
                    </div>
                </td>
                <td className="py-3 pr-3 text-[13px] text-om-ink whitespace-nowrap">{order.dueDate ? formatDate(`${order.dueDate}T12:00:00`) : '—'}</td>
                <td className="py-3 pr-3 text-[13px] text-om-ink whitespace-nowrap">
                    {order.start ? `${dayName(order.start.replace(' ', 'T'))} ${timeOf(order.start)} → ${dayOf(order.end) === dayOf(order.start) ? '' : `${dayName(order.end.replace(' ', 'T'))} `}${timeOf(order.end)}` : '—'}
                </td>
                <td className="py-3 pr-3">
                    <StatusPill status={order.outcome.pill} label={order.outcome.label} pulse={false} />
                    {order.outcome.reason && <div className="mt-1 max-w-[360px] text-[12px] text-om-muted">{order.outcome.reason}</div>}
                </td>
            </tr>
            {open && (
                <tr>
                    <td />
                    <td colSpan={4} className="pb-3 pr-3">
                        <table className="w-full table-fixed rounded-om-sm bg-om-bg text-[13px]">
                            <colgroup>
                                <col className="w-[22%]" />
                                <col className="w-[32%]" />
                                <col className="w-[20%]" />
                                <col className="w-[14%]" />
                                <col className="w-[12%]" />
                            </colgroup>
                            <tbody>
                                {order.steps.map((op) => (
                                    <tr key={op.id} className="border-t border-om-line first:border-t-0">
                                        <td className="px-3 py-2 align-top">
                                            <span className="text-om-faint" style={{ fontFamily: MONO }}>{op.step_number}</span>{' '}
                                            <span className="text-om-ink">{op.step_name}</span>
                                        </td>
                                        <td className="px-3 py-2 align-top" onClick={(e) => e.stopPropagation()}>
                                            <StationCell op={op} stationsByType={stationsByType} canDecide={canDecide} />
                                        </td>
                                        <td className="px-3 py-2 align-top whitespace-nowrap text-om-ink">
                                            {op.planned_start_label ? (
                                                <>
                                                    <span className="text-om-muted">{dayName(op.planned_start_at)}</span>{' '}
                                                    <span style={{ fontFamily: MONO }}>{timeOf(op.planned_start_label)}–{timeOf(op.planned_end_label)}</span>
                                                </>
                                            ) : '—'}
                                        </td>
                                        <td className="px-3 py-2 align-top whitespace-nowrap">
                                            <div className="text-om-ink">{minutes(op.duration_minutes)}</div>
                                            <SetupNote op={op} />
                                        </td>
                                        <td className="px-3 py-2 align-top text-[12px]">
                                            {!op.scheduled
                                                ? (op.status_label !== order.outcome.label && <span className="text-om-blocked">{op.status_label}</span>)
                                                : op.status === 'scheduled_late'
                                                    ? <span className="text-om-downtime">{__('Late')}</span>
                                                    : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </td>
                </tr>
            )}
        </>
    );
}

function OrdersTable({ orders, scope, openIds, toggle, stationsByType, canDecide }) {
    const head = 'pb-2 pr-3 text-left text-[9.5px] font-normal uppercase tracking-[0.08em] text-om-faint';

    return (
        <div className="overflow-x-auto rounded-om-sm border border-om-line bg-om-card">
            <table className="w-full border-collapse">
                <thead>
                    <tr className="border-b border-om-line" style={{ fontFamily: MONO }}>
                        <th className={`${head} w-8 pt-3`} />
                        <th className={`${head} pt-3`}>{__('Work order')}</th>
                        <th className={`${head} pt-3`}>{__('Due date')}</th>
                        <th className={`${head} pt-3`}>{__('Planned')}</th>
                        <th className={`${head} pt-3`}>{__('Result')}</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-om-line">
                    {orders.map((order) => (
                        <OrderRow
                            key={order.id}
                            order={order}
                            anchor={`${scope}-order-${order.id}`}
                            open={openIds.has(order.id)}
                            onToggle={() => toggle(order.id)}
                            stationsByType={stationsByType}
                            canDecide={canDecide}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Timeline + orders for one plan (the draft or the committed one). */
function PlanView({ scope, operations, stationsByType, canDecide, withSummary = false }) {
    const orders = useMemo(() => groupByOrder(operations), [operations]);
    const [openIds, setOpenIds] = useState(() => new Set(orders.length <= 6 ? orders.map((o) => o.id) : orders.filter((o) => o.outcome.key !== 'on_time').map((o) => o.id)));
    const toggle = (id) => setOpenIds((prev) => {
        const next = new Set(prev);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });
    const pick = (id) => {
        setOpenIds((prev) => new Set(prev).add(id));
        document.getElementById(`${scope}-order-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    return (
        <div className="space-y-4">
            {withSummary && <Summary orders={orders} />}
            <Timeline operations={operations} orders={orders} onPick={pick} />
            <OrdersTable orders={orders} scope={scope} openIds={openIds} toggle={toggle} stationsByType={stationsByType} canDecide={canDecide} />
        </div>
    );
}

// ─── Generate form ───────────────────────────────────────────────────────────

/** Every half hour of the day, 24-hour clock — the plant's own times. */
const HALF_HOURS = Array.from({ length: 48 }, (_, i) => `${String(Math.floor(i / 2)).padStart(2, '0')}:${i % 2 ? '30' : '00'}`);

/** "2026-10-14T16:04" → ["2026-10-14", "16:30"]: the next half hour from now. */
function splitStart(value) {
    const [day, time = '06:00'] = (value ?? '').split('T');
    const [h, m] = time.split(':').map(Number);
    const next = h * 60 + m === 0 ? 0 : Math.ceil((h * 60 + m) / 30) * 30;
    if (next >= 24 * 60) {
        const d = new Date(`${day}T12:00:00`);
        d.setDate(d.getDate() + 1);
        return [d.toISOString().slice(0, 10), '00:00'];
    }
    return [day, `${String(Math.floor(next / 60)).padStart(2, '0')}:${String(next % 60).padStart(2, '0')}`];
}

function GenerateForm({ defaults, canDecide }) {
    const [initialDay, initialTime] = splitStart(defaults.start);
    const form = useForm({
        day: initialDay,
        time: initialTime,
        horizon_days: String(defaults.horizon_days),
        scope: defaults.scope,
        respect_material: defaults.respect_material,
        respect_workforce: defaults.respect_workforce ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform(({ day, time, ...rest }) => ({ ...rest, start: day ? `${day}T${time}` : '' }));
        form.post('/admin/schedule/simulations', { preserveScroll: true });
    };

    const horizons = [[7, __('1 week')], [14, __('2 weeks')], [28, __('4 weeks')], [56, __('8 weeks')], [84, __('12 weeks')]]
        .filter(([days]) => days <= defaults.max_horizon_days)
        .map(([days, text]) => ({ value: String(days), label: text }));
    if (!horizons.some((o) => o.value === form.data.horizon_days)) {
        horizons.push({ value: form.data.horizon_days, label: __(':days days', { days: form.data.horizon_days }) });
    }

    // Same caption style as ResourceForm, so this reads like every other form.
    const label = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
    const error = (key) => form.errors[key] && <p className="mt-1 text-[11.5px] text-om-blocked">{form.errors[key]}</p>;

    return (
        <form onSubmit={submit} className="rounded-om-sm border border-om-line bg-om-card p-5">
            <div className="mb-4">
                <div className="text-[15px] font-semibold text-om-ink">{__('New simulation')}</div>
                <p className="mt-0.5 text-[12.5px] text-om-muted">
                    {__('Calculates a feasible plan from shifts, machines, people and material. Nothing changes until you apply it.')}
                </p>
            </div>

            <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_120px_minmax(150px,0.8fr)_minmax(240px,1.3fr)_auto]">
                <div>
                    <div className={label}>{__('From day')}</div>
                    <AppDatePicker className="w-full" value={form.data.day || null} onChange={(d) => form.setData('day', d ?? '')} aria-label={__('From day')} />
                    {error('start')}
                </div>
                <div>
                    <div className={label}>{__('From time')}</div>
                    <Dropdown
                        className="w-full"
                        options={HALF_HOURS.map((t) => ({ value: t, label: t }))}
                        value={form.data.time}
                        onChange={(v) => form.setData('time', v)}
                        aria-label={__('From time')}
                    />
                </div>
                <div>
                    <div className={label}>{__('Horizon')}</div>
                    <Dropdown
                        className="w-full"
                        options={horizons}
                        value={form.data.horizon_days}
                        onChange={(v) => form.setData('horizon_days', v)}
                        aria-label={__('Horizon')}
                        searchable={false}
                    />
                    {error('horizon_days')}
                </div>
                <div>
                    <div className={label}>{__('Orders to schedule')}</div>
                    <Dropdown
                        className="w-full"
                        options={[
                            { value: 'unplanned', label: __('Only orders without a time plan') },
                            { value: 'all', label: __('Replan every order not yet started') },
                        ]}
                        value={form.data.scope}
                        onChange={(v) => form.setData('scope', v)}
                        aria-label={__('Orders to schedule')}
                        searchable={false}
                    />
                    {error('scope')}
                </div>
                <Button type="submit" className="py-[11px]!" disabled={!canDecide || form.processing} leftIcon={<Icon name="calendar-cog" size={14} />}>
                    {form.processing ? __('Calculating…') : __('Simulate')}
                </Button>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-om-line pt-4">
                <span className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Take into account')}</span>
                <Checkbox
                    checked={!!form.data.respect_material}
                    onChange={(v) => form.setData('respect_material', v)}
                    label={__('Material availability')}
                />
                <Checkbox
                    checked={!!form.data.respect_workforce}
                    onChange={(v) => form.setData('respect_workforce', v)}
                    label={__('Operator availability')}
                />
            </div>

            {!canDecide && (
                <div className="mt-4">
                    <InlineAlert severity="info" title={__('Only a supervisor or administrator can simulate or apply schedules.')} />
                </div>
            )}
        </form>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

const NAV_LINK = 'inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold text-om-ink hover:bg-om-chip';

export default function AutoSchedule() {
    const { draft, committed = [], stationsByType = {}, defaults, canDecide } = usePage().props;
    const { confirm, dialog } = useConfirm();
    const placed = (draft?.counts?.scheduled ?? 0) + (draft?.counts?.scheduled_late ?? 0);

    const apply = () => confirm(
        {
            title: __('Apply this schedule?'),
            body: __('Fully schedulable work orders get the simulated time plan and workstations. Orders with infeasible steps keep their current placement. Each change is logged and can be undone from the planner.'),
            confirmLabel: __('Apply'),
        },
        () => router.post(`/admin/schedule/simulations/${draft.id}/apply`, {}, { preserveScroll: true }),
    );
    const discard = () => confirm(
        { title: __('Discard this simulation?'), body: __('The committed schedule is not affected.'), confirmLabel: __('Discard') },
        () => router.post(`/admin/schedule/simulations/${draft.id}/discard`, {}, { preserveScroll: true }),
    );

    return (
        <>
            <Head title={__('Automatic scheduling')} />
            <PageTrail append={[{ label: __('Automatic scheduling') }]} />

            <div className="space-y-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div style={{ fontFamily: MONO }} className="mb-1.5 text-[10px] uppercase tracking-[0.1em] text-om-faint">
                            {__('Schedule')} · {draft ? __('simulation ready for review') : __(':count operations committed', { count: committed.length })}
                        </div>
                        <h2 className="m-0 text-[24px] font-semibold tracking-[-0.022em] text-om-ink">{__('Automatic scheduling')}</h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/schedule" className={NAV_LINK}><Icon name="calendar-range" size={14} />{__('Planner')}</Link>
                        <Link href="/admin/schedule/capacity" className={NAV_LINK}><Icon name="chart-no-axes-combined" size={14} />{__('Capacity')}</Link>
                        <Link href="/admin/schedule/changeover-rules" className={NAV_LINK}><Icon name="repeat" size={14} />{__('Changeover Rules')}</Link>
                    </div>
                </div>

                <GenerateForm defaults={defaults} canDecide={canDecide} />

                {draft && (
                    <section className="space-y-4 rounded-om-sm border border-om-accent bg-om-panel p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="text-[15px] font-semibold text-om-ink">{__('Simulation — not applied')}</div>
                                <div className="mt-0.5 text-[12px] text-om-muted">
                                    {__('Horizon :range', { range: draft.horizon_label })}
                                    {' · '}
                                    {__('Generated :at by :user', { at: draft.created_label, user: draft.created_by ?? '—' })}
                                </div>
                            </div>
                            {canDecide && (
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" onClick={discard}>{__('Discard')}</Button>
                                    <Button onClick={apply} disabled={placed === 0}>{__('Apply')}</Button>
                                </div>
                            )}
                        </div>

                        {draft.operations?.length ? (
                            <PlanView key={draft.id} scope="draft" operations={draft.operations} stationsByType={stationsByType} canDecide={canDecide} withSummary />
                        ) : (
                            <div className="rounded-om-sm border border-dashed border-om-line px-4 py-8 text-center text-[13px] text-om-muted">
                                {__('No work orders to schedule — every active order already has a time plan, or none has a routing.')}
                            </div>
                        )}
                    </section>
                )}

                <section className="space-y-4">
                    <div>
                        <div className="text-[15px] font-semibold text-om-ink">{__('Committed operation plan')}</div>
                        <div className="mt-0.5 text-[12px] text-om-muted">
                            {__('Operations of active work orders from applied schedules. Moving an order by hand on the planner removes its rows here; the next simulation works around its new time plan.')}
                        </div>
                    </div>
                    {committed.length ? (
                        <PlanView key={committed.map((op) => op.id).join(',')} scope="committed" operations={committed} stationsByType={stationsByType} canDecide={canDecide} />
                    ) : (
                        <div className="rounded-om-sm border border-dashed border-om-line bg-om-card px-4 py-8 text-center text-[13px] text-om-muted">
                            {__('No schedule has been applied yet.')}
                        </div>
                    )}
                </section>
            </div>
            {dialog}
        </>
    );
}

AutoSchedule.layout = (page) => <AppLayout>{page}</AppLayout>;
