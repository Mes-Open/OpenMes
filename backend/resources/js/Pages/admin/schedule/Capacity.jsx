import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';
import { loadBarClass, loadPercClass } from '../../../lib/load';
import { apiCall, apiGet } from '../../../lib/http';

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
                    <label className="text-[10px] text-om-muted">
                        {__('Line')}
                        <select
                            value={lineId}
                            onChange={(e) => setLineId(e.target.value)}
                            className="block mt-0.5 text-xs border border-om-line rounded-om-sm px-1.5 py-1 bg-om-panel"
                        >
                            {lines.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-[10px] text-om-muted">
                        {__('Due date')}
                        <input
                            type="date"
                            value={dueDate ?? ''}
                            onChange={(e) => setDueDate(e.target.value)}
                            className="block mt-0.5 text-xs border border-om-line rounded-om-sm px-1.5 py-1 bg-om-panel"
                        />
                    </label>
                    <button
                        type="button"
                        disabled={!dirty || saving}
                        onClick={save}
                        className={`text-xs px-2 py-1 rounded-om-sm ${dirty && !saving ? 'bg-om-ink text-om-on-ink' : 'bg-om-line2 text-om-muted'}`}
                    >
                        {saving ? __('Saving…') : __('Move')}
                    </button>
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

    const resourceLabel = axis === 'crew' ? __('Crew') : __('Line');
    const emptyText = axis === 'crew' ? __('No active crews.') : __('No active lines.');

    return (
        <AppLayout>
            <Head title={__('Schedule Capacity')} />

            <div className="p-4 space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between flex-wrap gap-2">
                    <div className="flex items-center gap-3">
                        <h1 className="text-lg font-bold text-om-ink">{__('Schedule Capacity')}</h1>
                        <Link href="/admin/schedule" className="text-xs text-om-accent hover:underline">
                            {__('Planner')} &rarr;
                        </Link>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="inline-flex rounded-om-sm border border-om-line overflow-hidden">
                            <button type="button" onClick={() => setAxis('line')} className={`px-3 py-1 text-xs ${axis === 'line' ? 'bg-om-ink text-om-on-ink' : 'bg-om-panel text-om-muted'}`}>
                                {__('Line')}
                            </button>
                            <button type="button" onClick={() => setAxis('crew')} className={`px-3 py-1 text-xs ${axis === 'crew' ? 'bg-om-ink text-om-on-ink' : 'bg-om-panel text-om-muted'}`}>
                                {__('Crew')}
                            </button>
                        </div>
                        <div className="inline-flex rounded-om-sm border border-om-line overflow-hidden">
                            <button type="button" onClick={() => setGranularity('week')} className={`px-3 py-1 text-xs ${granularity === 'week' ? 'bg-om-ink text-om-on-ink' : 'bg-om-panel text-om-muted'}`}>
                                {__('Weekly')}
                            </button>
                            <button type="button" onClick={() => setGranularity('day')} className={`px-3 py-1 text-xs ${granularity === 'day' ? 'bg-om-ink text-om-on-ink' : 'bg-om-panel text-om-muted'}`}>
                                {__('Daily')}
                            </button>
                        </div>
                        <button type="button" onClick={() => goTo(navPrev)} className="px-2 py-1 text-xs rounded-om-sm border border-om-line bg-om-panel">&larr;</button>
                        <button type="button" onClick={() => goTo(navNext)} className="px-2 py-1 text-xs rounded-om-sm border border-om-line bg-om-panel">&rarr;</button>
                    </div>
                </div>

                {/* Legend */}
                <div className="flex items-center gap-4 text-[11px] text-om-muted flex-wrap">
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-om-running inline-block" />{__('Under 80%')}</span>
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-orange-500 inline-block" />{__('80–100%')}</span>
                    <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-om-blocked inline-block" />{__('Over 100%')}</span>
                    <span>{axis === 'line' ? __('Click a cell to see and reschedule its work orders.') : __('Crew planned hours are the labor demand of the lines each crew staffs.')}</span>
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
                                        </th>
                                        {buckets.map((b) => {
                                            const c = r.cells[b.key] ?? { available_h: 0, planned_h: 0, load_pct: null, unestimated_count: 0 };
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
