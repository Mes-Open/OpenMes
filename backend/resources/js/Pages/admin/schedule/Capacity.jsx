import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Dropdown, Icon, SegmentedControl } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import AppDatePicker from '../../../components/AppDatePicker';
import DueCountdown from '../../../components/DueCountdown';
import PageTrail from '../../../components/PageTrail';
import { __ } from '../../../lib/i18n';
import { loadBarClass, loadPercClass } from '../../../lib/load';
import { apiCall, apiGet } from '../../../lib/http';

const NAV_LINK = 'inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold text-om-ink hover:bg-om-chip';

// ─── Drill-down row: a single work order with inline reschedule ──────────────

function OrderRow({ order, lines, onSaved }) {
    const [lineId, setLineId] = useState(order.line_id ?? '');
    const [dueDate, setDueDate] = useState(order.due_date ?? '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const dirty = String(lineId) !== String(order.line_id ?? '') || (dueDate || '') !== (order.due_date ?? '');

    const save = async () => {
        setSaving(true);
        setError(null);
        try {
            // updateOrder() nulls any placement field the request omits, so echo
            // the order's existing week/shift placement back unchanged — the
            // drill-down only edits line and due_date, and both week_number (coarse
            // week) and shift_number (the pinned shift within a day) must survive a
            // line- or date-only change, or the order silently re-places / orphans.
            const r = await apiCall(`/admin/schedule/${order.id}`, 'PUT', {
                line_id: lineId || null,
                due_date: dueDate || null,
                week_number: order.week_number ?? null,
                shift_number: order.shift_number ?? null,
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok || data.success === false) {
                setError(data.message || __('Could not reschedule.'));
                return;
            }
            onSaved();
        } catch {
            setError(__('Could not reschedule.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="border border-om-line rounded-om-sm p-2 space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <Link href={`/admin/work-orders/${order.id}`} className="text-xs font-bold text-om-ink hover:underline truncate">
                    {order.order_no}
                </Link>
                <span className="text-[11px] text-om-muted whitespace-nowrap">
                    {order.unestimated ? __('Work orders with no duration estimate') : `${order.hours}h`}
                </span>
            </div>
            {order.product_name && <div className="text-[11px] text-om-muted truncate">{order.product_name}</div>}

            {order.minute_planned ? (
                <div className="text-[11px] text-om-accent">
                    {__('Time-planned — edit in planner')}
                    {' · '}
                    <Link href="/admin/schedule?view_mode=hourly" className="underline">{__('Planner')}</Link>
                </div>
            ) : (
                <div className="flex items-end gap-2 flex-wrap">
                    <div className="min-w-[160px]">
                        <div className="mb-1 text-[10px] text-om-muted">{__('Line')}</div>
                        <Dropdown
                            size="sm"
                            className="w-full"
                            options={lines.map((l) => ({ value: String(l.id), label: l.name }))}
                            value={String(lineId)}
                            onChange={setLineId}
                            aria-label={__('Line')}
                        />
                    </div>
                    <label className="text-[10px] text-om-muted">
                        {/* The countdown reads the value being edited, not the
                            saved one, so a date typed here answers "how much room
                            does that leave?" before Move is pressed. */}
                        {__('Due date')}
                        {dueDate && <DueCountdown due={dueDate} className="ml-1.5" />}
                        <AppDatePicker className="mt-1 w-[170px]" value={dueDate || null} onChange={(d) => setDueDate(d ?? '')} aria-label={__('Due date')} />
                    </label>
                    <Button size="sm" disabled={!dirty || saving} onClick={save}>
                        {saving ? __('Saving…') : __('Move')}
                    </Button>
                </div>
            )}
            {error && <div className="text-[11px] text-om-blocked">{error}</div>}
        </div>
    );
}

// ─── Main page ───────────────────────────────────────────────────────────────

export default function Capacity() {
    const {
        grid = { buckets: [], resources: [] },
        granularity = 'week',
        axis = 'line',
        startDate,
        navPrev,
        navNext,
        lines = [],
    } = usePage().props;

    const { buckets = [], resources = [] } = grid;
    const [cell, setCell] = useState(null); // { resource, bucket, orders, loading }

    const nav = (params) => router.get('/admin/schedule/capacity', params, { preserveState: false });
    const setGranularity = (g) => nav({ axis, granularity: g, start_date: startDate });
    const setAxis = (a) => nav({ axis: a, granularity, start_date: startDate });
    const goTo = (date) => nav({ axis, granularity, start_date: date });

    const openCell = async (resource, bucket) => {
        if (axis !== 'line') return; // drill-down + reschedule is line-axis only
        setCell({ resource, bucket, orders: [], loading: true, error: false });
        try {
            const params = new URLSearchParams({ line_id: resource.id, start: bucket.start, end: bucket.end });
            const r = await apiGet(`/admin/schedule/capacity/cell?${params}`);
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const data = await r.json();
            setCell({ resource, bucket, orders: data.orders ?? [], loading: false, error: false });
        } catch {
            // Distinguish a failed load from a genuinely empty cell, so a busy cell
            // that errors isn't shown as "no work orders".
            setCell({ resource, bucket, orders: [], loading: false, error: true });
        }
    };

    const refreshCell = async () => {
        if (cell) await openCell(cell.resource, cell.bucket);
        router.reload({ only: ['grid'] });
    };

    const resourceLabel = { crew: __('Crew'), workstation: __('Workstation') }[axis] ?? __('Line');
    const emptyText = { crew: __('No active crews.'), workstation: __('No active workstations.') }[axis] ?? __('No active lines.');
    const axisHint = {
        line: __('Click a cell to see and reschedule its work orders.'),
        crew: __('Crew planned hours are the labor demand of the lines each crew staffs.'),
        workstation: __('Workstation planned hours come from applied automatic schedules; work placed only by hand shows on the line axis.'),
    }[axis];

    return (
        <AppLayout>
            <Head title={__('Schedule Capacity')} />
            <PageTrail append={[{ label: __('Capacity') }]} />

            <div className="p-4 sm:p-6 space-y-4">
                {/* Header — same anatomy as the planner and automatic scheduling */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="mb-1.5 font-mono text-[10px] uppercase tracking-[0.1em] text-om-faint">
                            {__('Schedule')} · {{ line: __('Line'), crew: __('Crew'), workstation: __('Workstation') }[axis]}
                        </div>
                        <h2 className="m-0 text-[24px] font-semibold tracking-[-0.022em] text-om-ink">{__('Schedule Capacity')}</h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/schedule" className={NAV_LINK}><Icon name="calendar-range" size={14} />{__('Planner')}</Link>
                        <Link href="/admin/schedule/auto" className={NAV_LINK}><Icon name="calendar-cog" size={14} />{__('Automatic scheduling')}</Link>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SegmentedControl
                        label={__('Capacity axis')}
                        className="w-full sm:w-[340px]"
                        value={axis}
                        onChange={setAxis}
                        options={[
                            { value: 'line', label: __('Line') },
                            { value: 'crew', label: __('Crew') },
                            { value: 'workstation', label: __('Workstation') },
                        ]}
                    />
                    <SegmentedControl
                        label={__('Granularity')}
                        className="w-full sm:w-[220px]"
                        value={granularity}
                        onChange={setGranularity}
                        options={[
                            { value: 'week', label: __('Weekly') },
                            { value: 'day', label: __('Daily') },
                        ]}
                    />
                    <div className="flex items-center gap-1">
                        <Button variant="outline" size="sm" className="p-2" onClick={() => goTo(navPrev)} aria-label={__('Previous')}><Icon name="chevron-left" size={16} /></Button>
                        <Button variant="outline" size="sm" className="p-2" onClick={() => goTo(navNext)} aria-label={__('Next')}><Icon name="chevron-right" size={16} /></Button>
                    </div>
                </div>

                {/* Legend */}
                <div className="flex items-center gap-4 text-[11px] text-om-muted flex-wrap">
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-om-running inline-block" />{__('Under 80%')}</span>
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-orange-500 inline-block" />{__('80–100%')}</span>
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-om-blocked inline-block" />{__('Over 100%')}</span>
                    <span>{axisHint}</span>
                </div>

                <div className="flex gap-4 items-start">
                    {/* Capacity grid */}
                    <div className="overflow-x-auto border border-om-line rounded-om-sm flex-1">
                        <table className="min-w-full border-collapse text-xs">
                            <thead>
                                <tr className="bg-om-panel">
                                    <th className="sticky left-0 z-10 bg-om-panel text-left px-3 py-2 font-semibold text-om-muted border-b border-om-line">
                                        {resourceLabel}
                                    </th>
                                    {buckets.map((b) => (
                                        <th key={b.key} className="px-3 py-2 text-center font-semibold text-om-muted border-b border-l border-om-line whitespace-nowrap">
                                            {b.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {resources.length === 0 && (
                                    <tr>
                                        <td colSpan={buckets.length + 1} className="px-3 py-6 text-center text-om-muted">{emptyText}</td>
                                    </tr>
                                )}
                                {resources.map((r) => (
                                    <tr key={r.id} className="hover:bg-om-line2/30">
                                        <th className="sticky left-0 z-10 bg-om-panel text-left px-3 py-2 font-medium text-om-ink border-b border-om-line whitespace-nowrap">
                                            {r.name}
                                            {r.code ? <span className="text-om-muted font-normal"> · {r.code}</span> : null}
                                            {r.planning_efficiency_pct != null && r.planning_efficiency_pct < 100 && (
                                                <div
                                                    className="text-[10px] font-normal text-om-muted"
                                                    title={__('Load is measured against effective capacity: calendar capacity × planning efficiency.')}
                                                >
                                                    {__('Planning efficiency :pct%', { pct: r.planning_efficiency_pct })}
                                                </div>
                                            )}
                                        </th>
                                        {buckets.map((b) => {
                                            const c = r.cells[b.key] ?? { calendar_h: 0, available_h: 0, planned_h: 0, load_pct: null, unestimated_count: 0 };
                                            const pct = c.load_pct;
                                            // No capacity but work is planned → a real over-capacity
                                            // problem; flag red rather than muting like an empty cell.
                                            const noCapacityOverload = pct === null && c.planned_h > 0;
                                            const barPct = pct === null ? (noCapacityOverload ? 100 : 0) : Math.min(100, pct);
                                            const label = noCapacityOverload ? __('No capacity') : (pct === null ? __('No shifts') : `${pct}%`);
                                            const textClass = noCapacityOverload ? 'text-om-blocked' : loadPercClass(pct);
                                            const barClass = noCapacityOverload ? 'bg-om-blocked' : loadBarClass(pct);
                                            const isSelected = cell && cell.resource.id === r.id && cell.bucket.key === b.key;
                                            const clickable = axis === 'line';
                                            return (
                                                <td
                                                    key={b.key}
                                                    onClick={clickable ? () => openCell(r, b) : undefined}
                                                    className={`px-2 py-2 align-top border-b border-l border-om-line min-w-[90px] ${clickable ? 'cursor-pointer' : ''} ${isSelected ? 'ring-2 ring-inset ring-om-accent' : ''}`}
                                                >
                                                    <div className={`text-[11px] font-bold ${textClass}`}>{label}</div>
                                                    <div className="h-1.5 my-1 rounded-full bg-om-line2 overflow-hidden">
                                                        <div className={`h-full ${barClass}`} style={{ width: `${barPct}%` }} />
                                                    </div>
                                                    <div className="text-[10px] text-om-muted">{c.planned_h}h / {c.available_h}h</div>
                                                    {c.calendar_h != null && c.calendar_h !== c.available_h && (
                                                        <div className="text-[10px] text-om-muted" title={__('Calendar capacity before planning efficiency')}>
                                                            {__('Calendar :hours h', { hours: c.calendar_h })}
                                                        </div>
                                                    )}
                                                    {c.unestimated_count > 0 && (
                                                        <div className="mt-0.5 text-[10px] text-om-accent" title={__('Work orders with no duration estimate')}>
                                                            {__(':count unestimated', { count: c.unestimated_count })}
                                                        </div>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Drill-down panel (line axis) */}
                    {cell && (
                        <div className="w-72 shrink-0 border border-om-line rounded-om-sm bg-om-panel p-3 space-y-2">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="text-xs font-bold text-om-ink">{cell.resource.name}</div>
                                    <div className="text-[11px] text-om-muted">{cell.bucket.label}</div>
                                </div>
                                <button type="button" onClick={() => setCell(null)} className="text-om-muted hover:text-om-ink text-sm leading-none">✕</button>
                            </div>

                            {cell.loading && <div className="text-[11px] text-om-muted">{__('Loading…')}</div>}
                            {!cell.loading && cell.error && (
                                <div className="text-[11px] text-om-blocked">
                                    {__('Could not load work orders.')}{' '}
                                    <button type="button" onClick={() => openCell(cell.resource, cell.bucket)} className="underline">
                                        {__('Retry')}
                                    </button>
                                </div>
                            )}
                            {!cell.loading && !cell.error && cell.orders.length === 0 && (
                                <div className="text-[11px] text-om-muted">{__('No work orders in this cell.')}</div>
                            )}
                            {!cell.loading && !cell.error && cell.orders.map((o) => (
                                <OrderRow key={o.id} order={o} lines={lines} onSaved={refreshCell} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
