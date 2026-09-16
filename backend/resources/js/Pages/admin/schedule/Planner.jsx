import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { DndProvider, useDragDropManager } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend';
import { Icon, ConfirmDialog } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import LiveRefresh from '../../../components/LiveRefresh';
import { apiCall, apiGet } from '../../../lib/http';
import { __, formatDate } from '../../../lib/i18n';
import { todayKey, dayList } from './planner/helpers';
import { WeeklyView } from './planner/views';
import { HourlyView, MonthlyView } from './planner/views2';
import { Toolbar, BacklogRail } from './planner/panels';
import {
    OrderEditSheet, AssignPopup, ConflictDialog, LiveTrackingBar, Toasts, SavingOverlay,
    AddMaintenanceModal,
} from './planner/modals';

// Hover affordances for the design's work-order blocks (brightness + reveal ✕).
const STYLE = `
.om-wo:hover { filter: brightness(0.97); }
.om-wo:hover .om-x { opacity: 1; }
`;

let TOAST_SEQ = 1;
const pad = (n) => String(n).padStart(2, '0');

// Tracks whether a drag is in flight so live-sync refreshes can be deferred
// until the drop completes (a mid-drag re-render would abort the native drag).
function DragWatcher({ draggingRef }) {
    const manager = useDragDropManager();
    useEffect(() => {
        const monitor = manager.getMonitor();
        return monitor.subscribeToStateChange(() => { draggingRef.current = monitor.isDragging(); });
    }, [manager, draggingRef]);
    return null;
}

export default function Planner() {
    const {
        workOrders = [], lines = [], allLines = [], shifts = [],
        viewMode = 'weekly', shiftsPerDay = 1, slotMinutes = 15, showWeekends = true,
        startDate, rangeStart, rangeEnd, navPrev, navNext,
        backlogOrders = [], maintenanceEvents = [], maintenanceSchedules = [], realtimeMode = 'polling',
        overdueImportant = { count: 0, orders: [] },
    } = usePage().props;

    const lineId = new URLSearchParams(window.location.search).get('line_id') ?? '';
    const live = realtimeMode !== 'off';

    // ── Navigation (server-driven: view/range/filter all reload) ───────────────
    const nav = useCallback((params) => {
        router.get('/admin/schedule', params, { preserveState: false, preserveScroll: true });
    }, []);
    const goTo = (start) => nav({ start_date: start, view_mode: viewMode, line_id: lineId });
    const setView = (k) => nav({
        view_mode: k, line_id: lineId,
        // Let the server choose today's date when opening the daily plan.
        ...(viewMode === 'weekly' && k === 'daily' ? {} : { start_date: startDate }),
    });
    const setLineFilter = (v) => nav({ view_mode: viewMode, line_id: v, start_date: startDate });

    const refreshContent = useCallback(() => new Promise((resolve) => {
        router.reload({ preserveScroll: true, onFinish: resolve });
    }), []);

    // ── State ──────────────────────────────────────────────────────────────────
    const [saving, setSaving] = useState(false);
    const [toasts, setToasts] = useState([]);
    const [selected, setSelected] = useState(null);     // edit sheet
    const [assignTarget, setAssignTarget] = useState(null);
    const [conflict, setConflict] = useState(null);     // { apply }
    const [confirmBox, setConfirmBox] = useState(null); // { title, body, confirmLabel, apply }
    const [trackingData, setTrackingData] = useState(null);
    const [maintOpen, setMaintOpen] = useState(false);
    const draggingRef = useRef(false);

    const toast = useCallback((msg, kind = 'success') => {
        const id = TOAST_SEQ++;
        setToasts((ts) => [...ts, { id, msg, kind }]);
        setTimeout(() => setToasts((ts) => ts.filter((x) => x.id !== id)), 3200);
    }, []);

    // ── Context handed to the views ────────────────────────────────────────────
    const days = dayList(startDate, showWeekends ? 7 : 5, showWeekends);
    const data = {
        lines, allLines, shifts, workOrders, backlog: backlogOrders, maintenance: maintenanceEvents,
        range: { start: rangeStart, end: rangeEnd, today: todayKey(), startDate },
    };
    const config = { shiftsPerDay, slotMinutes, showWeekends };

    // ── Saving / writes ────────────────────────────────────────────────────────
    const saveOrder = useCallback(async function savePlacement(orderId, body) {
        setSaving(true);
        try {
            const r = await apiCall(`/admin/schedule/${orderId}`, 'PUT', body);
            if (r.status === 409 && !body.force_conflict) {
                setSaving(false);
                return await new Promise((resolve) => setConflict({
                    apply: () => resolve(savePlacement(orderId, { ...body, force_conflict: true })),
                    cancel: () => resolve(null),
                }));
            }
            const json = await r.json();
            if (json.success) return json;
            toast(json.message ?? __('Error saving'), 'error');
            return null;
        } catch {
            toast(__('Connection error'), 'error');
            return null;
        } finally {
            setSaving(false);
        }
    }, [toast]);

    // The full extra-segments payload for updateOrder's sync, with one
    // segment updated, removed or appended.
    const placementsPayload = (wo, { update, remove, add } = {}) => {
        let list = (wo.placements || []).map((p) => ({
            id: p.id, line_id: p.line_id, due_date: p.due_date,
            shift_number: p.shift_number || '', end_date: p.end_date || '', end_shift_number: p.end_shift_number || '',
        }));
        if (remove != null) list = list.filter((p) => p.id !== remove);
        if (update) list = list.map((p) => (p.id === update.id ? { ...p, ...update } : p));
        if (add) list = [...list, add];
        return list;
    };

    const slotStart = (date, shift) => date ? `${date}T${shifts?.[Math.max(0, Number(shift || 1) - 1)]?.start_time?.slice(0, 5) || '00:00'}:00` : '';

    const dayEnd = (date) => date ? `${dayList(date, 2, true)[1].date}T00:00:00` : '';

    const performDrop = useCallback(async (wo, target, placement) => {
        const body = placement !== 'primary'
            ? {
                extra_placements: placementsPayload(wo, {
                    update: { id: placement, line_id: target.lineId, due_date: target.date || '', shift_number: target.shift || '', end_date: '', end_shift_number: '' },
                }),
            }
            : {
                line_id: target.lineId,
                planned_start_at: slotStart(target.date, target.shift),
                shift_number: target.shift || '',
                week_number: '', end_date: target.date || '', end_shift_number: target.shift || '',
                planned_end_at: dayEnd(target.date),
            };
        const result = await saveOrder(wo.id, body);
        if (result) {
            const code = allLines.find((l) => l.id === target.lineId)?.code ?? '';
            toast(`${wo.order_no} → ${code}`);
            refreshContent();
        }
    }, [saveOrder, allLines, toast, refreshContent, shifts]);

    // Drop a card onto a coarse (weekly/daily) cell. `placement` says which
    // schedule segment was dragged — only that segment moves. A coarse primary
    // placement clears any exact minute plan — confirm first so an accidental
    // drag doesn't silently destroy hourly timing.
    const dropToCell = useCallback((wo, target, placement = 'primary') => {
        if (placement === 'primary' && wo.planned_start_at && wo.planned_end_at) {
            setConfirmBox({
                title: __('Replace exact time plan?'),
                body: __('This order has an exact start and end time. Moving it to a shift cell will use the shift start and clear the exact end.'),
                confirmLabel: __('Replace'),
                apply: () => performDrop(wo, target, placement),
            });
            return;
        }
        performDrop(wo, target, placement);
    }, [performDrop]);

    // Hourly move/resize commit → resize endpoint (handles minute conflicts).
    const onHourlyChange = useCallback(async (wo, startMin, endMin, force = false) => {
        const day = data.range.start;
        const iso = (m) => m === 1440 ? `${dayList(day, 2, true)[1].date}T00:00:00` : `${day}T${pad(Math.floor(m / 60))}:${pad(m % 60)}:00`;
        const body = { planned_start_at: iso(startMin), planned_end_at: iso(endMin) };
        if (force) body.force_conflict = true;
        setSaving(true);
        try {
            const r = await apiCall(`/admin/schedule/${wo.id}/resize`, 'PUT', body);
            if (r.status === 409) {
                setConflict({ apply: () => onHourlyChange(wo, startMin, endMin, true) });
                return;
            }
            const json = await r.json();
            if (json.success) { toast(`${wo.order_no} ${pad(Math.floor(startMin / 60))}:${pad(startMin % 60)}–${pad(Math.floor(endMin / 60))}:${pad(endMin % 60)}`); await refreshContent(); }
            else toast(json.message ?? __('Error saving'), 'error');
        } catch {
            toast(__('Connection error'), 'error');
        } finally {
            setSaving(false);
        }
    }, [data.range.start, toast, refreshContent]);

    const saveEdit = useCallback(async (wo, patch) => {
        const result = await saveOrder(wo.id, patch);
        if (result) { setSelected(null); toast(`${wo.order_no} ${__('updated')}`); refreshContent(); }
    }, [saveOrder, toast, refreshContent]);

    const performUnassign = useCallback(async (wo) => {
        // Clearing the primary line also deletes every extra segment server-side.
        const result = await saveOrder(wo.id, {
            line_id: '', week_number: '', shift_number: '',
            end_date: '', end_shift_number: '', planned_end_at: '',
        });
        setSelected(null);
        if (result) { toast(`${wo.order_no} → ${__('Backlog')}`); refreshContent(); }
    }, [saveOrder, toast, refreshContent]);

    const unassign = useCallback((wo) => {
        setConfirmBox({
            title: `${__('Unschedule')} ${wo.order_no}?`,
            body: __('The order will be removed from the schedule and returned to the backlog.'),
            confirmLabel: __('Unschedule'),
            apply: () => performUnassign(wo),
        });
    }, [performUnassign]);

    // Drop one extra segment — the other placements stay untouched.
    const detachPlacement = useCallback(async (wo, key) => {
        const result = await saveOrder(wo.id, { extra_placements: placementsPayload(wo, { remove: key }) });
        if (result) { toast(`${wo.order_no} ${__('updated')}`); refreshContent(); }
    }, [saveOrder, toast, refreshContent]);

    // Commit a weekly span/move: map start/end grid columns back to a date +
    // shift span and persist. Segments are independent — `placement` says
    // which one was dragged, and only that segment is written.
    const onSpanChange = useCallback((wo, startCol, endCol, newLineId, placement = 'primary') => {
        const sp = config.shiftsPerDay;
        const cell = (col) => ({ date: days[Math.floor(col / sp)]?.date, shift: (col % sp) + 1 });
        const a = cell(startCol); const b = cell(endCol);
        if (!a.date || !b.date) return;
        const spanned = endCol > startCol;

        let body;
        if (placement !== 'primary') {
            const current = (wo.placements || []).find((p) => p.id === placement);
            body = {
                extra_placements: placementsPayload(wo, {
                    update: {
                        id: placement, line_id: newLineId ?? current?.line_id,
                        due_date: a.date, shift_number: a.shift,
                        end_date: spanned ? b.date : '', end_shift_number: spanned ? b.shift : '',
                    },
                }),
            };
        } else {
            body = {
                line_id: newLineId ?? wo.line_id,
                planned_start_at: slotStart(a.date, a.shift), week_number: '', shift_number: a.shift,
                end_date: b.date, end_shift_number: b.shift,
                planned_end_at: dayEnd(b.date),
            };
        }
        return saveOrder(wo.id, body).then((r) => { if (r) { toast(`${wo.order_no} ${__('updated')}`); return refreshContent(); } });
    }, [shifts, config.shiftsPerDay, days, saveOrder, toast, refreshContent]);

    // Diagonal edge-stretch: the order continues on another line — the
    // extension is APPENDED as a new segment, so the block chain reads as a
    // staircase across the board (any number of steps).
    const onDiagonalExtend = useCallback((wo, lineId, startCol, endCol) => {
        const sp = config.shiftsPerDay;
        const cell = (col) => ({ date: days[Math.floor(col / sp)]?.date, shift: (col % sp) + 1 });
        const a = cell(startCol); const b = cell(endCol);
        if (!a.date || !b.date) return;
        const spanned = endCol > startCol;
        return saveOrder(wo.id, {
            extra_placements: placementsPayload(wo, {
                add: {
                    line_id: lineId, due_date: a.date, shift_number: a.shift,
                    end_date: spanned ? b.date : '', end_shift_number: spanned ? b.shift : '',
                },
            }),
        }).then((r) => {
            if (r) {
                const code = allLines.find((l) => l.id === lineId)?.code ?? '';
                toast(`${wo.order_no} ⇄ ${code}`);
                return refreshContent();
            }
        });
    }, [shifts, config.shiftsPerDay, days, saveOrder, allLines, toast, refreshContent]);

    const ctx = {
        data, config, days,
        draggingRef,
        onSelectDay: (date) => nav({ start_date: date, view_mode: 'daily', line_id: lineId }),
        onSelectOrder: setSelected, selectedId: selected?.id, onHourlyChange,
        onDropOrder: dropToCell,
        onUnassign: unassign,
        onDetachPlacement: detachPlacement,
        onSpanChange,
        onDiagonalExtend,
        onRefreshContent: refreshContent,
        onCellClick: (target) => setAssignTarget(target),
    };

    // ── Live tracking — always on, auto-follows the first in-progress order ────
    const firstInProgress = workOrders.find((o) => o.status === 'IN_PROGRESS');
    const trackId = firstInProgress?.id ?? null;
    useEffect(() => {
        setTrackingData(null);
        if (!trackId) return undefined;
        let alive = true;
        let t = null;
        const fetchIt = async () => {
            try {
                const r = await apiGet(`/admin/schedule/check-updates?track=${trackId}`);
                // Session expired → stop this 5s poll instead of 401ing forever
                // on a left-open planner tab (a real action will redirect to login).
                if (r.status === 401 || r.status === 419) { clearInterval(t); return; }
                if (!r.ok) return;
                const d = await r.json();
                if (alive && d.tracked_order) setTrackingData(d.tracked_order);
            } catch { /* silent */ }
        };
        fetchIt();
        t = setInterval(fetchIt, 5000);
        return () => { alive = false; clearInterval(t); };
    }, [trackId]);

    // ── Live sync — defer refresh while dragging/saving ────────────────────────
    const pendingRefresh = useRef(false);
    const onWorkOrdersChanged = useCallback(() => {
        if (saving || draggingRef.current) { pendingRefresh.current = true; return; }
        refreshContent();
    }, [saving, refreshContent]);
    useEffect(() => {
        if (!saving && pendingRefresh.current) { pendingRefresh.current = false; refreshContent(); }
    }, [saving, refreshContent]);

    const rangeLabel = (rangeStart && rangeEnd)
        ? rangeStart === rangeEnd
            ? formatDate(new Date(rangeStart), { day: '2-digit', month: '2-digit', year: 'numeric' })
            : `${formatDate(new Date(rangeStart), { day: '2-digit', month: '2-digit' })} – ${formatDate(new Date(rangeEnd), { day: '2-digit', month: '2-digit', year: 'numeric' })}`
        : '';

    return (
        <>
            <Head title={__('Production Planner')} />
            <style>{STYLE}</style>
            <LiveRefresh pollUrl="/admin/schedule/check-updates" shape="work_orders_all" instant enabled={live} onRefresh={onWorkOrdersChanged} />

            <div className="w-full min-w-0">
            <DndProvider backend={HTML5Backend}>
            <DragWatcher draggingRef={draggingRef} />
            <div className="p-4 sm:p-6">
            {/* page header */}
            <div className="flex items-start justify-between gap-4 mb-4 flex-wrap">
                <div>
                    <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--om-faint)', marginBottom: 6 }}>
                        {__('Production')} · {backlogOrders.length} {__('unscheduled in backlog')}
                    </div>
                    <h2 style={{ margin: 0, fontSize: 24, fontWeight: 600, letterSpacing: '-0.022em', color: 'var(--om-ink)' }}>{__('Production Planner')}</h2>
                </div>
                <LiveTrackingBar wo={firstInProgress} live={trackingData} />
            </div>

            {/* High-value customers with overdue orders — ported from develop's banner */}
            {overdueImportant.count > 0 && (
                <div className="mb-3 flex items-start gap-2 rounded-om border border-om-blocked/30 bg-om-blocked-bg px-4 py-2.5 text-[13px] text-om-blocked">
                    <Icon name="triangle-alert" size={16} className="mt-0.5 shrink-0" />
                    <div>
                        <span className="font-semibold">
                            {__(':count high-tier customer order(s) overdue', { count: overdueImportant.count })}
                        </span>
                        {overdueImportant.orders.length > 0 && (
                            <span className="text-om-muted">
                                {' — '}
                                {overdueImportant.orders.slice(0, 5).map((o, idx) => (
                                    <span key={o.id}>
                                        {idx > 0 && ', '}
                                        <Link href={`/admin/work-orders/${o.id}`} className="hover:underline font-medium text-om-blocked">
                                            {o.order_no}
                                        </Link>
                                        {o.customer_name ? ` (${o.customer_name})` : ''}
                                    </span>
                                ))}
                                {overdueImportant.count > 5 && ` +${overdueImportant.count - 5}`}
                            </span>
                        )}
                    </div>
                </div>
            )}

            <Toolbar ctx={ctx} view={viewMode} setView={setView} lineFilter={lineId} setLineFilter={setLineFilter}
                live={live} onMaintenance={() => setMaintOpen(true)} onPrev={() => goTo(navPrev)} onNext={() => goTo(navNext)} onToday={() => nav({ view_mode: viewMode, line_id: lineId })} rangeLabel={rangeLabel} />

            </div>
            <BacklogRail ctx={ctx} />
            <div className="w-full min-w-0" style={{ border: '1px solid var(--om-line)', borderRadius: 0, overflow: 'hidden', background: 'var(--om-bg)' }}>
                <div className="om-main w-full min-w-0 overflow-auto">
                    {viewMode === 'weekly' && <WeeklyView ctx={ctx} />}
                    {viewMode === 'daily' && <HourlyView ctx={ctx} />}
                    {viewMode === 'monthly' && <MonthlyView ctx={ctx} />}
                </div>
            </div>
            </DndProvider>
            </div>

            {selected && <OrderEditSheet wo={selected} ctx={ctx} onClose={() => setSelected(null)} onSave={saveEdit} onUnassign={unassign} />}
            {assignTarget && (
                <AssignPopup
                    target={assignTarget}
                    ctx={ctx}
                    schedules={maintenanceSchedules}
                    onClose={() => setAssignTarget(null)}
                    onPick={(wo, target) => { setAssignTarget(null); dropToCell(wo, target); }}
                    onPickMaintenance={(s, target) => {
                        setAssignTarget(null);
                        router.post('/admin/schedule/maintenance', {
                            schedule_id: s.id,
                            title: s.name || null,
                            event_type: s.event_type || 'planned',
                            line_id: target.lineId,
                            scheduled_at: `${target.date} 08:00`,
                            duration_minutes: s.duration_minutes || 60,
                        }, {
                            preserveScroll: true,
                            onSuccess: () => toast(__('Maintenance added to the planner.')),
                            onError: (errors) => toast(Object.values(errors || {})[0] || __('Could not add maintenance.'), 'error'),
                        });
                    }}
                />
            )}
            {conflict && <ConflictDialog onCancel={() => { conflict.cancel?.(); setConflict(null); }} onConfirm={() => { conflict.apply(); setConflict(null); }} />}
            {confirmBox && (
                <ConfirmDialog open onClose={() => setConfirmBox(null)}
                    onConfirm={() => { const apply = confirmBox.apply; setConfirmBox(null); apply(); }}
                    title={confirmBox.title} confirmLabel={confirmBox.confirmLabel} cancelLabel={__('Cancel')}>
                    {confirmBox.body}
                </ConfirmDialog>
            )}
            {maintOpen && (
                <AddMaintenanceModal
                    lines={allLines}
                    schedules={maintenanceSchedules}
                    startDate={startDate}
                    onClose={() => setMaintOpen(false)}
                    onCreated={() => { setMaintOpen(false); toast(__('Maintenance added to the planner.')); }}
                    onError={(msg) => toast(msg || __('Could not add maintenance.'), 'error')}
                />
            )}
            {saving && <SavingOverlay />}
            <Toasts toasts={toasts} />
        </>
    );
}

Planner.layout = (page) => <AppLayout>{page}</AppLayout>;
