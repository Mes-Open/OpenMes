import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DndProvider, useDragDropManager } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend';
import AppLayout from '../../../layouts/AppLayout';
import LiveRefresh from '../../../components/LiveRefresh';
import { apiCall, apiGet } from '../../../lib/http';
import { __, formatDate } from '../../../lib/i18n';
import { todayKey, dayList } from './planner/helpers';
import { WeeklyView, DailyView } from './planner/views';
import { HourlyView, MonthlyView } from './planner/views2';
import { Toolbar, BacklogRail } from './planner/panels';
import {
    OrderEditSheet, AssignPopup, ConflictDialog, LiveTrackingBar, Toasts, SavingOverlay,
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
        backlogOrders = [], maintenanceEvents = [], realtimeMode = 'polling',
    } = usePage().props;

    const lineId = new URLSearchParams(window.location.search).get('line_id') ?? '';
    const live = realtimeMode !== 'off';

    // ── Navigation (server-driven: view/range/filter all reload) ───────────────
    const nav = useCallback((params) => {
        router.get('/admin/schedule', params, { preserveState: false, preserveScroll: true });
    }, []);
    const goTo = (start) => nav({ start_date: start, view_mode: viewMode, line_id: lineId });
    const setView = (k) => nav({ view_mode: k, line_id: lineId, start_date: startDate });
    const setLineFilter = (v) => nav({ view_mode: viewMode, line_id: v, start_date: startDate });

    const refreshContent = useCallback(() => { router.reload({ preserveScroll: true }); }, []);

    // ── State ──────────────────────────────────────────────────────────────────
    const [saving, setSaving] = useState(false);
    const [toasts, setToasts] = useState([]);
    const [selected, setSelected] = useState(null);     // edit sheet
    const [assignTarget, setAssignTarget] = useState(null);
    const [conflict, setConflict] = useState(null);     // { apply }
    const [trackingData, setTrackingData] = useState(null);
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
    const saveOrder = useCallback(async (orderId, body) => {
        setSaving(true);
        try {
            const r = await apiCall(`/admin/schedule/${orderId}`, 'PUT', body);
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

    // Drop a card onto a coarse (weekly/daily) cell.
    const dropToCell = useCallback(async (wo, target) => {
        // A coarse (day/shift) placement clears any exact minute plan — confirm
        // first so an accidental drag doesn't silently destroy hourly timing.
        if (wo.planned_start_at && wo.planned_end_at
            && !window.confirm(__('This order has an exact time plan — replace it with a day/shift placement?'))) {
            return;
        }
        const body = {
            line_id: target.lineId,
            due_date: target.date || '',
            shift_number: target.shift || '',
            week_number: '', end_date: '', end_shift_number: '',
            planned_start_at: '', planned_end_at: '',
        };
        const result = await saveOrder(wo.id, body);
        if (result) {
            const code = allLines.find((l) => l.id === target.lineId)?.code ?? '';
            toast(`${wo.order_no} → ${code}`);
            refreshContent();
        }
    }, [saveOrder, allLines, toast, refreshContent]);

    // Hourly move/resize commit → resize endpoint (handles minute conflicts).
    const onHourlyChange = useCallback(async (wo, startMin, endMin, force = false) => {
        const day = data.range.start;
        const iso = (m) => `${day}T${pad(Math.floor(m / 60))}:${pad(m % 60)}:00`;
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
            if (json.success) { toast(`${wo.order_no} ${pad(Math.floor(startMin / 60))}:${pad(startMin % 60)}–${pad(Math.floor(endMin / 60))}:${pad(endMin % 60)}`); refreshContent(); }
            else toast(json.message ?? __('Error saving'), 'error');
        } catch {
            toast(__('Connection error'), 'error');
        } finally {
            setSaving(false);
        }
    }, [data.range.start, toast, refreshContent]);

    const saveEdit = useCallback(async (wo, patch) => {
        const result = await saveOrder(wo.id, patch);
        setSelected(null);
        if (result) { toast(`${wo.order_no} ${__('updated')}`); refreshContent(); }
    }, [saveOrder, toast, refreshContent]);

    const unassign = useCallback(async (wo) => {
        if (!window.confirm(__('Remove this order from the schedule?'))) return;
        const result = await saveOrder(wo.id, {
            line_id: '', due_date: '', week_number: '', shift_number: '',
            end_date: '', end_shift_number: '', planned_start_at: '', planned_end_at: '',
        });
        setSelected(null);
        if (result) { toast(`${wo.order_no} → ${__('Backlog')}`); refreshContent(); }
    }, [saveOrder, toast, refreshContent]);

    // Commit a weekly span/move: map start/end grid columns back to due/end
    // date + shift (and optionally a new line) and persist.
    const onSpanChange = useCallback((wo, startCol, endCol, newLineId) => {
        const sp = config.shiftsPerDay;
        const cell = (col) => ({ date: days[Math.floor(col / sp)]?.date, shift: (col % sp) + 1 });
        const a = cell(startCol); const b = cell(endCol);
        if (!a.date || !b.date) return;
        const spanned = endCol > startCol;
        saveOrder(wo.id, {
            line_id: newLineId ?? wo.line_id,
            due_date: a.date, week_number: '', shift_number: a.shift,
            end_date: spanned ? b.date : '', end_shift_number: spanned ? b.shift : '',
            planned_start_at: '', planned_end_at: '',
        }).then((r) => { if (r) { toast(`${wo.order_no} ${__('updated')}`); refreshContent(); } });
    }, [config.shiftsPerDay, days, saveOrder, toast, refreshContent]);

    const ctx = {
        data, config, days,
        onSelectOrder: setSelected, selectedId: selected?.id, onHourlyChange,
        onDropOrder: dropToCell,
        onUnassign: unassign,
        onSpanChange,
        onCellClick: (target) => setAssignTarget(target),
    };

    // ── Live tracking — always on, auto-follows the first in-progress order ────
    const firstInProgress = workOrders.find((o) => o.status === 'IN_PROGRESS');
    const trackId = firstInProgress?.id ?? null;
    useEffect(() => {
        setTrackingData(null);
        if (!trackId) return undefined;
        let alive = true;
        const fetchIt = async () => {
            try {
                const r = await apiGet(`/admin/schedule/check-updates?track=${trackId}`);
                if (!r.ok) return;
                const d = await r.json();
                if (alive && d.tracked_order) setTrackingData(d.tracked_order);
            } catch { /* silent */ }
        };
        fetchIt();
        const t = setInterval(fetchIt, 5000);
        return () => { alive = false; clearInterval(t); };
    }, [trackId]);

    // ── Live sync (Electric) — defer refresh while dragging/saving ─────────────
    const pendingRefresh = useRef(false);
    const onWorkOrdersChanged = useCallback(() => {
        if (saving || draggingRef.current) { pendingRefresh.current = true; return; }
        refreshContent();
    }, [saving, refreshContent]);
    useEffect(() => {
        if (!saving && pendingRefresh.current) { pendingRefresh.current = false; refreshContent(); }
    }, [saving, refreshContent]);

    const rangeLabel = (rangeStart && rangeEnd)
        ? `${formatDate(new Date(rangeStart), { day: '2-digit', month: '2-digit' })} – ${formatDate(new Date(rangeEnd), { day: '2-digit', month: '2-digit', year: 'numeric' })}`
        : '';

    return (
        <>
            <Head title={__('Production Planner')} />
            <style>{STYLE}</style>
            <LiveRefresh pollUrl="/admin/schedule/check-updates" shape="work_orders_all" instant enabled={live} onRefresh={onWorkOrdersChanged} />

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

            <DndProvider backend={HTML5Backend}>
            <DragWatcher draggingRef={draggingRef} />

            <Toolbar ctx={ctx} view={viewMode} setView={setView} lineFilter={lineId} setLineFilter={setLineFilter}
                live={live} onPrev={() => goTo(navPrev)} onNext={() => goTo(navNext)} onToday={() => nav({ view_mode: viewMode, line_id: lineId })} rangeLabel={rangeLabel} />

            <div className="flex items-start" style={{ border: '1px solid var(--om-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--om-bg)' }}>
                <div className="om-main flex-1 min-w-0" style={{ padding: '18px 20px', overflow: 'auto' }}>
                    {viewMode === 'weekly' && <WeeklyView ctx={ctx} />}
                    {viewMode === 'daily' && <DailyView ctx={ctx} />}
                    {viewMode === 'hourly' && <HourlyView ctx={ctx} />}
                    {viewMode === 'monthly' && <MonthlyView ctx={ctx} />}
                </div>
                <BacklogRail ctx={ctx} />
            </div>
            </DndProvider>

            {selected && <OrderEditSheet wo={selected} ctx={ctx} onClose={() => setSelected(null)} onSave={saveEdit} onUnassign={unassign} />}
            {assignTarget && <AssignPopup target={assignTarget} ctx={ctx} onClose={() => setAssignTarget(null)} onPick={(wo, target) => { setAssignTarget(null); dropToCell(wo, target); }} />}
            {conflict && <ConflictDialog onCancel={() => setConflict(null)} onConfirm={() => { conflict.apply(); setConflict(null); }} />}
            {saving && <SavingOverlay />}
            <Toasts toasts={toasts} />
        </>
    );
}

Planner.layout = (page) => <AppLayout>{page}</AppLayout>;
