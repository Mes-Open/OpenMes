import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LiveRefresh from '../../../components/LiveRefresh';
import { formatDate, formatNumber } from '../../../lib/i18n';

// ─── Constants ────────────────────────────────────────────────────────────────

const SHIFT_COLORS = {
    1: { bg: 'bg-sky-300',    label: 'S1', hours: '00-06' },
    2: { bg: 'bg-amber-300',  label: 'S2', hours: '06-12' },
    3: { bg: 'bg-orange-400', label: 'S3', hours: '12-18' },
    4: { bg: 'bg-rose-400',   label: 'S4', hours: '18-24' },
};
const WO_COLORS = {
    PENDING:     'bg-gray-200 border-gray-300 text-gray-700',
    ACCEPTED:    'bg-blue-200 border-blue-400 text-blue-800',
    IN_PROGRESS: 'bg-amber-200 border-amber-400 text-amber-800',
    BLOCKED:     'bg-red-200 border-red-400 text-red-800',
    PAUSED:      'bg-orange-200 border-orange-400 text-orange-800',
    DONE:        'bg-green-200 border-green-400 text-green-800',
};
const STATUS_LABELS = {
    PENDING: 'Pending', ACCEPTED: 'Accepted', IN_PROGRESS: 'In Progress',
    BLOCKED: 'Blocked', PAUSED: 'Paused', DONE: 'Done',
    REJECTED: 'Rejected', CANCELLED: 'Cancelled',
};
const PRIORITY_META = {
    5: { label: 'Urgent',  color: 'text-red-600',    bg: 'bg-red-50 border-red-200',    icon: '⚠' },
    4: { label: 'High',    color: 'text-orange-600',  bg: 'bg-orange-50 border-orange-200', icon: '▲' },
    3: { label: 'Medium',  color: 'text-amber-600',   bg: 'bg-amber-50 border-amber-200',  icon: '●' },
    2: { label: 'Low',     color: 'text-blue-600',    bg: 'bg-blue-50 border-blue-200',    icon: '▼' },
    1: { label: 'Lowest',  color: 'text-gray-500',    bg: 'bg-gray-50 border-gray-200',    icon: '—' },
};
const PX_PER_MINUTE = 2;
const HOUR_PX = 60 * PX_PER_MINUTE;
const TOTAL_WIDTH = 24 * 60 * PX_PER_MINUTE;

// ─── Utilities ────────────────────────────────────────────────────────────────

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

async function apiCall(url, method, body) {
    const r = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    return r;
}

function loadPercClass(pct) {
    if (pct > 100) return 'text-red-600';
    if (pct > 80) return 'text-orange-600';
    return 'text-green-600';
}
function loadBarClass(pct) {
    if (pct > 100) return 'bg-red-500';
    if (pct > 80) return 'bg-orange-500';
    return 'bg-green-500';
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function Planner() {
    const {
        data = [], lines = [], allLines = [], shifts = [],
        viewMode = 'weekly', shiftsPerDay = 1, slotMinutes = 15,
        horizonWeeks = 4, showWeekends = true,
        startDate, rangeStart, rangeEnd, navPrev, navNext,
        backlogOrders = [], maintenanceEvents = [], realtimeMode = 'polling',
    } = usePage().props;

    // ── Navigation ────────────────────────────────────────────────────────────
    const lineId = new URLSearchParams(window.location.search).get('line_id') ?? '';

    const nav = useCallback((params) => {
        router.get('/admin/schedule', params, { preserveState: false });
    }, []);

    const goTo = (startDateStr) => nav({ start_date: startDateStr, view_mode: viewMode, line_id: lineId });

    // ── State ─────────────────────────────────────────────────────────────────
    const [saving, setSaving] = useState(false);
    const [toast, setToast] = useState(null);
    const toastTimeout = useRef(null);

    // Backlog panel
    const [backlogCollapsed, setBacklogCollapsed] = useState(false);
    const [backlogSearch, setBacklogSearch] = useState('');
    const [backlogPriority, setBacklogPriority] = useState('');
    const [backlogLine, setBacklogLine] = useState('');
    const [backlogSort, setBacklogSort] = useState('due_date');

    // Assign popup
    const [assignPopup, setAssignPopup] = useState(false);
    const [assignTarget, setAssignTarget] = useState(null);
    const [assignSearch, setAssignSearch] = useState('');

    // Selection / edit panel
    const [selected, setSelected] = useState(null);

    // Drag state
    const dragOrderId = useRef(null);
    const dragOrderNo = useRef(null);
    const [dragOverCell, setDragOverCell] = useState(null);

    // Live tracking
    const [trackingId, setTrackingId] = useState(null);
    const [trackingData, setTrackingData] = useState(null);
    const trackingTimer = useRef(null);

    // Live-sync indicator state (driven by <LiveRefresh/>)
    const [pollingActive, setPollingActive] = useState(false);

    // Tooltip
    const [tooltip, setTooltip] = useState(null);
    const [tipPos, setTipPos] = useState({ x: 0, y: 0 });

    // ── Backlog items (JS-side mirror for assign popup) ────────────────────────
    const backlogItems = backlogOrders.map((wo) => ({
        id: wo.id,
        order_no: wo.order_no,
        product: wo.product_name ?? '-',
        qty: wo.planned_qty,
        priority: wo.priority,
        status: STATUS_LABELS[wo.status] ?? wo.status,
        due_date: wo.due_date ?? '-',
        line_id: wo.line_id,
    }));

    // ── Toast ─────────────────────────────────────────────────────────────────
    const showToast = useCallback((msg, type = 'success') => {
        setToast({ msg, type });
        clearTimeout(toastTimeout.current);
        toastTimeout.current = setTimeout(() => setToast(null), 3000);
    }, []);

    // ── Save order ────────────────────────────────────────────────────────────
    const saveOrder = useCallback(async (orderId, body) => {
        setSaving(true);
        try {
            const r = await apiCall(`/admin/schedule/${orderId}`, 'PUT', body);
            const json = await r.json();
            if (json.success) {
                showToast(json.message ?? 'Saved');
                return json;
            } else {
                showToast(json.message ?? 'Error saving', 'error');
                return null;
            }
        } catch {
            showToast('Connection error', 'error');
            return null;
        } finally {
            setSaving(false);
        }
    }, [showToast]);

    // ── Refresh ───────────────────────────────────────────────────────────────
    const refreshContent = useCallback(() => {
        router.reload({ preserveState: false });
    }, []);

    // ── Assign ────────────────────────────────────────────────────────────────
    const openAssign = (lineId, date, shift, weekNumber) => {
        setAssignTarget({ lineId, date, shift, weekNumber });
        setAssignSearch('');
        setAssignPopup(true);
    };

    const assignOrder = async (orderId) => {
        const { lineId: lid, date, shift, weekNumber } = assignTarget;
        const body = { line_id: lid };
        if (date) {
            body.due_date = date;
            const shiftHours = { 1: 0, 2: 6, 3: 12, 4: 18 };
            const startH = shiftHours[shift] ?? 8;
            body.planned_start_at = `${date}T${String(startH).padStart(2, '0')}:00:00`;
            body.planned_end_at = `${date}T${String(startH + 6).padStart(2, '0')}:00:00`;
        }
        if (weekNumber) body.week_number = weekNumber;
        if (shift) body.shift_number = shift;
        const result = await saveOrder(orderId, body);
        if (result) { setAssignPopup(false); refreshContent(); }
    };

    const unassignOrder = async (orderId) => {
        if (!confirm('Remove this order from schedule?')) return;
        const result = await saveOrder(orderId, {
            line_id: '', due_date: '', week_number: '', shift_number: '',
            end_date: '', end_shift_number: '', planned_start_at: '', planned_end_at: '',
        });
        if (result) refreshContent();
    };

    // ── Drag & Drop ───────────────────────────────────────────────────────────
    const onDragStart = (e, id, no) => {
        dragOrderId.current = id;
        dragOrderNo.current = no || (backlogItems.find(i => i.id == id)?.order_no ?? 'WO');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', id);
        e.target.style.opacity = '0.4';
    };
    const onDragEnd = (e) => {
        dragOrderId.current = null;
        dragOrderNo.current = null;
        setDragOverCell(null);
        e.target.style.opacity = '';
    };
    const onDrop = async (e, lid, date, shift, weekNumber) => {
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain') || dragOrderId.current;
        setDragOverCell(null);
        dragOrderId.current = null;
        dragOrderNo.current = null;
        if (!id) return;
        setAssignTarget({ lineId: lid, date, shift, weekNumber });
        const body = { line_id: lid };
        if (date) { body.due_date = date; }
        if (weekNumber) body.week_number = weekNumber;
        if (shift) body.shift_number = shift;
        const result = await saveOrder(id, body);
        if (result) refreshContent();
    };

    // ── Selection / Edit panel ────────────────────────────────────────────────
    const selectOrder = (wo, cellDate, shift) => {
        if (dragOrderId.current) return;
        if (selected?.id === wo.id) { setSelected(null); return; }
        setSelected({
            id: wo.id, order_no: wo.order_no,
            line_id: wo.line_id,
            dueDate: wo.due_date ?? '',
            shift: wo.shift_number ?? '',
            endDate: wo.end_date ?? '',
            endShift: wo.end_shift_number ?? '',
            url: `/admin/work-orders/${wo.id}`,
        });
    };

    const saveSelectedDates = async () => {
        if (!selected) return;
        setSaving(true);
        try {
            await saveOrder(selected.id, {
                due_date: selected.dueDate,
                shift_number: selected.shift ? parseInt(selected.shift) : '',
            });
            const spanBody = (selected.endDate && selected.endShift)
                ? { end_date: selected.endDate, end_shift_number: parseInt(selected.endShift) }
                : { end_date: null, end_shift_number: null };
            const r = await apiCall(`/admin/schedule/${selected.id}/resize`, 'PUT', spanBody);
            const json = await r.json();
            if (json.success) showToast(json.message ?? 'Saved');
            else showToast(json.message ?? 'Error', 'error');
            refreshContent();
            setSelected(null);
        } catch { showToast('Connection error', 'error'); }
        finally { setSaving(false); }
    };

    // ── Live tracking ─────────────────────────────────────────────────────────
    const startTracking = (id) => {
        stopTracking();
        setTrackingId(id);
        setTrackingData(null);
        fetchTracking(id);
        trackingTimer.current = setInterval(() => fetchTracking(id), 5000);
    };
    const stopTracking = () => {
        clearInterval(trackingTimer.current);
        setTrackingId(null);
        setTrackingData(null);
    };
    const fetchTracking = async (id) => {
        try {
            const r = await fetch(`/admin/schedule/check-updates?track=${id}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!r.ok) return;
            const d = await r.json();
            if (d.tracked_order) setTrackingData(d.tracked_order);
        } catch { /* silent */ }
    };

    // ── Live sync (Electric) ────────────────────────────────────────────────
    // Cross-window sync is now push-based: <ShapeChangeWatcher> (rendered below)
    // calls onWorkOrdersChanged the instant any work order changes (move, resize,
    // remove, status, create/delete). The 10s check-updates poll was replaced —
    // Electric pushes immediately. We still defer a refresh while the user is
    // mid-drag or saving, then flush it once they finish, so their interaction
    // isn't interrupted.
    const pendingRefresh = useRef(false);

    const onWorkOrdersChanged = useCallback(() => {
        if (saving || dragOrderId.current) {
            pendingRefresh.current = true;
            return;
        }
        refreshContent();
    }, [saving, refreshContent]);

    // Flush a deferred refresh once a save completes (drag-end is usually
    // followed by a save, which re-renders and triggers this).
    useEffect(() => {
        if (!saving && pendingRefresh.current) {
            pendingRefresh.current = false;
            refreshContent();
        }
    }, [saving, refreshContent]);

    useEffect(() => {
        setPollingActive(realtimeMode !== 'off');
    }, [realtimeMode]);

    // ─────────────────────────────────────────────────────────────────────────

    // Filtered + sorted backlog
    const filteredBacklog = backlogItems
        .filter(i => !backlogSearch || (i.order_no + ' ' + i.product).toLowerCase().includes(backlogSearch.toLowerCase()))
        .filter(i => !backlogPriority || String(i.priority) === String(backlogPriority))
        .filter(i => !backlogLine || String(i.line_id) === String(backlogLine) || !i.line_id)
        .sort((a, b) => {
            if (backlogSort === 'priority') return (b.priority ?? 0) - (a.priority ?? 0);
            if (backlogSort === 'planned_qty') return (b.qty ?? 0) - (a.qty ?? 0);
            const da = a.due_date === '-' ? '9999' : a.due_date;
            const db = b.due_date === '-' ? '9999' : b.due_date;
            return da.localeCompare(db);
        });

    const groupedBacklog = {};
    filteredBacklog.forEach(i => {
        const p = i.priority ?? 3;
        if (!groupedBacklog[p]) groupedBacklog[p] = [];
        groupedBacklog[p].push(i);
    });
    const priorityKeys = Object.keys(groupedBacklog).map(Number).sort((a, b) => b - a);

    // ─────────────────────────────────────────────────────────────────────────

    const rangeStartFmt = rangeStart ? formatDate(new Date(rangeStart), { day: '2-digit', month: '2-digit' }) : '';
    const rangeEndFmt = rangeEnd ? formatDate(new Date(rangeEnd), { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';

    return (
        <>
            <Head title="Production Planner" />
            <LiveRefresh
                pollUrl="/admin/schedule/check-updates"
                shape="work_orders_all"
                enabled={realtimeMode !== 'off'}
                onRefresh={onWorkOrdersChanged}
            />
            <div>
                {/* ─── TOOLBAR ─── */}
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-2.5 mb-4 flex flex-wrap items-center gap-3">
                    <button onClick={() => goTo(navPrev)} className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="Previous">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span className="font-semibold text-sm text-gray-700 dark:text-gray-200">
                        {rangeStartFmt} &ndash; {rangeEndFmt}
                    </span>
                    <button onClick={() => goTo(navNext)} className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="Next">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <div className="h-6 border-l border-gray-300 dark:border-gray-600 mx-1" />

                    {/* View mode tabs */}
                    <div className="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                        {[['weekly','Weekly'],['daily','Daily'],['hourly','Hourly'],['monthly','Monthly']].map(([m, l]) => (
                            <button key={m}
                                onClick={() => nav({ start_date: startDate, view_mode: m, line_id: lineId })}
                                className={`px-3 py-1 text-xs font-medium rounded-md transition ${viewMode === m ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
                                {l}
                            </button>
                        ))}
                        <Link href={`/admin/schedule/employees?date=${startDate}`}
                           className="px-3 py-1 text-xs font-medium rounded-md transition text-amber-600 hover:text-amber-700 dark:text-amber-400"
                           title="Employee day planner — tachograph view">
                            Employees
                        </Link>
                    </div>

                    <div className="h-6 border-l border-gray-300 dark:border-gray-600 mx-1" />

                    {/* Line filter */}
                    <select value={lineId} onChange={(e) => nav({ start_date: startDate, view_mode: viewMode, line_id: e.target.value })}
                            className="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2">
                        <option value="">All Lines</option>
                        {allLines.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                    </select>

                    <div className="flex-1" />

                    {/* Shift legend */}
                    <div className="flex items-center gap-2 text-[10px] text-gray-500">
                        {Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => (
                            <span key={s} className="inline-flex items-center gap-1">
                                <span className={`w-3 h-2.5 rounded-sm ${SHIFT_COLORS[s]?.bg ?? 'bg-gray-300'}`}></span>
                                {SHIFT_COLORS[s]?.label ?? `S${s}`}
                            </span>
                        ))}
                    </div>

                    <div className="h-6 border-l border-gray-300 dark:border-gray-600 mx-1" />

                    {/* Polling indicator */}
                    <div className="flex items-center gap-1.5" title={pollingActive ? 'Auto-refresh: polling every 10s' : 'Auto-refresh disabled'}>
                        <span className="relative flex h-2.5 w-2.5">
                            {pollingActive && <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75" />}
                            <span className={`relative inline-flex rounded-full h-2.5 w-2.5 ${pollingActive ? 'bg-green-500' : 'bg-gray-400'}`} />
                        </span>
                        <span className="text-[10px] text-gray-400">{pollingActive ? 'Live' : 'Off'}</span>
                    </div>

                    <div className="h-6 border-l border-gray-300 dark:border-gray-600 mx-1" />
                    <button onClick={() => nav({ view_mode: viewMode, line_id: lineId })} className="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition">
                        Today
                    </button>
                </div>

                {/* ─── MAIN LAYOUT ─── */}
                <div className="flex gap-4">
                    {/* Left: Grid area */}
                    <div className="flex-1 min-w-0">
                        {viewMode === 'weekly' && (
                            <WeeklyView
                                data={data} shiftsPerDay={shiftsPerDay} showWeekends={showWeekends}
                                maintenanceEvents={maintenanceEvents}
                                dragOverCell={dragOverCell}
                                dragOrderNo={dragOrderNo}
                                selectedId={selected?.id}
                                onDragStart={onDragStart} onDragEnd={onDragEnd}
                                onDragOver={(e, cellId) => { e.preventDefault(); setDragOverCell(cellId); }}
                                onDragLeave={(e, cellId) => { if (dragOverCell === cellId) setDragOverCell(null); }}
                                onDrop={onDrop}
                                onSelectOrder={selectOrder}
                                onOpenAssign={openAssign}
                                onUnassign={unassignOrder}
                                onShowTip={(e, d) => {
                                    const r = e.target.getBoundingClientRect();
                                    setTipPos({ x: r.left + window.scrollX, y: r.bottom + window.scrollY + 8 });
                                    setTooltip(d);
                                }}
                                onHideTip={() => setTooltip(null)}
                            />
                        )}
                        {viewMode === 'daily' && (
                            <DailyView
                                data={data} lines={lines} maintenanceEvents={maintenanceEvents}
                                onUnassign={unassignOrder}
                            />
                        )}
                        {viewMode === 'hourly' && (
                            <HourlyView
                                data={data} slotMinutes={slotMinutes} startDate={startDate}
                                shiftsPerDay={shiftsPerDay} maintenanceEvents={maintenanceEvents}
                                onUnassign={unassignOrder}
                                onRefresh={refreshContent}
                                showToast={showToast}
                            />
                        )}
                        {viewMode === 'monthly' && (
                            <MonthlyView data={data} onUnassign={unassignOrder} />
                        )}
                    </div>

                    {/* Right: Backlog panel */}
                    <div className={`shrink-0 transition-all duration-300 ${backlogCollapsed ? 'w-10' : 'w-[380px]'}`}>
                        {backlogCollapsed ? (
                            <button onClick={() => setBacklogCollapsed(false)}
                                    className="w-10 h-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm flex flex-col items-center pt-4 hover:bg-gray-50 transition">
                                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                <span className="mt-2 text-[10px] font-medium text-gray-500" style={{ writingMode: 'vertical-lr' }}>Backlog ({backlogOrders.length})</span>
                            </button>
                        ) : (
                            <BacklogPanel
                                backlogOrders={backlogOrders} backlogItems={backlogItems}
                                allLines={allLines}
                                search={backlogSearch} onSearch={setBacklogSearch}
                                filterLine={backlogLine} onFilterLine={setBacklogLine}
                                filterPriority={backlogPriority} onFilterPriority={setBacklogPriority}
                                sort={backlogSort} onSort={setBacklogSort}
                                groupedBacklog={groupedBacklog} priorityKeys={priorityKeys}
                                dragOverCell={dragOverCell}
                                onDragStart={onDragStart} onDragEnd={onDragEnd}
                                onCollapse={() => setBacklogCollapsed(true)}
                            />
                        )}
                    </div>
                </div>

                {/* ─── ASSIGN POPUP ─── */}
                {assignPopup && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                         onClick={(e) => { if (e.target === e.currentTarget) setAssignPopup(false); }}
                         onKeyDown={(e) => e.key === 'Escape' && setAssignPopup(false)}>
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-[420px] max-h-[70vh] flex flex-col border border-gray-200 dark:border-gray-700">
                            <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-bold text-gray-800 dark:text-gray-100">Assign order to shift</h3>
                                    <p className="text-[10px] text-gray-400 mt-0.5">Select an unassigned order to place in this slot</p>
                                </div>
                                <button onClick={() => setAssignPopup(false)} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div className="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                                <input type="text" value={assignSearch} onChange={(e) => setAssignSearch(e.target.value)}
                                       placeholder="Search by order number or product..."
                                       className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2.5 placeholder-gray-400"
                                       autoFocus />
                            </div>
                            <div className="flex-1 overflow-y-auto px-4 py-2 space-y-1.5">
                                {backlogItems.filter(i => !assignSearch || (i.order_no + ' ' + i.product).toLowerCase().includes(assignSearch.toLowerCase())).map((item) => (
                                    <button key={item.id} onClick={() => assignOrder(item.id)}
                                            className="w-full text-left p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-bold text-gray-800 dark:text-gray-100">{item.order_no}</span>
                                            <span className="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500">{item.status}</span>
                                        </div>
                                        <div className="flex items-center gap-3 mt-1 text-[10px] text-gray-500">
                                            <span>{item.product}</span><span>&middot;</span>
                                            <span>{item.qty} pcs</span><span>&middot;</span>
                                            <span>Due: {item.due_date}</span>
                                        </div>
                                    </button>
                                ))}
                                {backlogItems.filter(i => !assignSearch || (i.order_no + ' ' + i.product).toLowerCase().includes(assignSearch.toLowerCase())).length === 0 && (
                                    <div className="text-center py-6 text-xs text-gray-400">No matching orders found</div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* ─── EDIT PANEL ─── */}
                {selected && (
                    <>
                        <div className="fixed inset-0 z-30" onClick={() => setSelected(null)} />
                        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-4 w-[480px] max-w-[95vw]">
                            <div className="flex items-center justify-between mb-3">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-bold text-gray-800 dark:text-gray-100">{selected.order_no}</span>
                                    <Link href={selected.url} className="text-[10px] text-blue-600 hover:underline">View details &rarr;</Link>
                                </div>
                                <div className="flex items-center gap-1">
                                    <button onClick={() => trackingId === selected.id ? stopTracking() : startTracking(selected.id)}
                                            className={`flex items-center gap-1 px-2 py-1 text-[10px] font-medium rounded-lg transition ${trackingId === selected.id ? 'bg-green-100 text-green-700 ring-1 ring-green-400' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'}`}>
                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        {trackingId === selected.id ? 'Tracking' : 'Track live'}
                                    </button>
                                    <button onClick={() => setSelected(null)} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[10px] font-semibold text-gray-500 uppercase mb-1">Start date</label>
                                    <input type="date" value={selected.dueDate}
                                           onChange={(e) => setSelected(s => ({ ...s, dueDate: e.target.value }))}
                                           className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2" />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold text-gray-500 uppercase mb-1">Start shift</label>
                                    <select value={selected.shift} onChange={(e) => setSelected(s => ({ ...s, shift: e.target.value }))}
                                            className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2">
                                        <option value="">—</option>
                                        {Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => (
                                            <option key={s} value={s}>{SHIFT_COLORS[s]?.label ?? `S${s}`} ({SHIFT_COLORS[s]?.hours ?? ''})</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold text-gray-500 uppercase mb-1">End date</label>
                                    <input type="date" value={selected.endDate}
                                           onChange={(e) => setSelected(s => ({ ...s, endDate: e.target.value }))}
                                           className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2" />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold text-gray-500 uppercase mb-1">End shift</label>
                                    <select value={selected.endShift} onChange={(e) => setSelected(s => ({ ...s, endShift: e.target.value }))}
                                            className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2">
                                        <option value="">—</option>
                                        {Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => (
                                            <option key={s} value={s}>{SHIFT_COLORS[s]?.label ?? `S${s}`} ({SHIFT_COLORS[s]?.hours ?? ''})</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 mt-3">
                                <button onClick={saveSelectedDates}
                                        className="flex-1 px-3 py-2 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                                    Save
                                </button>
                                <button onClick={() => setSelected(null)}
                                        className="px-3 py-2 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </>
                )}

                {/* ─── LIVE TRACKING PANEL ─── */}
                {trackingId && trackingData && (
                    <div className="fixed top-4 right-4 z-50 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 w-[320px]">
                        <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="relative flex h-2.5 w-2.5">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75" />
                                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500" />
                                </span>
                                <span className="text-xs font-bold text-gray-800 dark:text-gray-100">{trackingData.order_no}</span>
                                <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${trackingData.is_overdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}`}>
                                    {trackingData.status}
                                </span>
                            </div>
                            <button onClick={stopTracking} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400" title="Stop tracking">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div className="px-4 py-3 space-y-3">
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <span className="text-[10px] font-semibold text-gray-500 uppercase">Progress</span>
                                    <span className={`text-xs font-bold ${trackingData.progress_percent >= 100 ? 'text-green-600' : trackingData.is_overdue ? 'text-red-600' : 'text-blue-600'}`}>
                                        {trackingData.progress_percent}%
                                    </span>
                                </div>
                                <div className="w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div className={`h-full rounded-full transition-all duration-500 ${trackingData.progress_percent >= 100 ? 'bg-green-500' : trackingData.is_overdue ? 'bg-red-500' : 'bg-blue-500'}`}
                                         style={{ width: `${Math.min(100, trackingData.progress_percent || 0)}%` }} />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2 text-center">
                                    <div className="text-[10px] text-gray-400 uppercase">Produced</div>
                                    <div className="text-lg font-bold text-gray-800 dark:text-gray-100">{trackingData.produced_qty}</div>
                                </div>
                                <div className="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2 text-center">
                                    <div className="text-[10px] text-gray-400 uppercase">Planned</div>
                                    <div className="text-lg font-bold text-gray-800 dark:text-gray-100">{trackingData.planned_qty}</div>
                                </div>
                            </div>
                            <div className="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                                <div className="flex justify-between"><span>Line</span><span className="font-medium text-gray-800 dark:text-gray-200">{trackingData.line}</span></div>
                                <div className="flex justify-between"><span>Product</span><span className="font-medium text-gray-800 dark:text-gray-200">{trackingData.product}</span></div>
                                {trackingData.current_step && (
                                    <div className="flex justify-between"><span>Current step</span><span className="font-medium text-gray-800 dark:text-gray-200">{trackingData.current_step.name}</span></div>
                                )}
                            </div>
                            {trackingData.is_overdue && (
                                <div className="flex items-center gap-2 px-3 py-2 bg-red-50 dark:bg-red-900/20 rounded-lg text-xs text-red-700 dark:text-red-400">
                                    <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                    This order is overdue!
                                </div>
                            )}
                        </div>
                        <div className="px-4 py-2 border-t border-gray-100 dark:border-gray-700 text-[10px] text-gray-400 text-center">Auto-refreshing every 5s</div>
                    </div>
                )}

                {/* ─── TOAST ─── */}
                {toast && (
                    <div className={`fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium max-w-sm ${toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`}>
                        {toast.type === 'success'
                            ? <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                            : <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        }
                        {toast.msg}
                    </div>
                )}

                {/* ─── SAVING OVERLAY ─── */}
                {saving && (
                    <div className="fixed inset-0 z-40 bg-white/10 pointer-events-none flex items-center justify-center">
                        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg px-4 py-2 text-sm text-gray-600 flex items-center gap-2">
                            <svg className="animate-spin h-4 w-4 text-blue-500" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Saving...
                        </div>
                    </div>
                )}

                {/* ─── TOOLTIP ─── */}
                {tooltip && (
                    <div className="fixed z-40 bg-gray-900 text-white rounded-lg shadow-xl px-3 py-2 text-xs pointer-events-none max-w-xs"
                         style={{ left: tipPos.x, top: tipPos.y }}>
                        <div className="font-bold mb-1">{tooltip.order_no}</div>
                        <div className="opacity-80">{tooltip.product}</div>
                        <div className="opacity-80">Qty: {tooltip.qty}</div>
                        <div className="opacity-80">Status: {tooltip.status}</div>
                    </div>
                )}
            </div>
        </>
    );
}

Planner.layout = (page) => <AppLayout>{page}</AppLayout>;

// ─── WeeklyView ───────────────────────────────────────────────────────────────

function WeeklyView({ data, shiftsPerDay, showWeekends, maintenanceEvents, dragOverCell, dragOrderNo,
    selectedId, onDragStart, onDragEnd, onDragOver, onDragLeave, onDrop,
    onSelectOrder, onOpenAssign, onUnassign, onShowTip, onHideTip }) {

    const daysInWeek = showWeekends ? 7 : 5;
    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className="space-y-4">
            {data.map((period, idx) => {
                const isOverloaded = period.total_load_percent > 100;
                const weekStart = period.start;
                const isCurrentWeek = (() => {
                    const now = new Date();
                    const ws = new Date(period.start);
                    const we = new Date(period.end);
                    return now >= ws && now <= we;
                })();
                const prevYear = idx > 0 ? new Date(data[idx - 1].start).getFullYear() : null;
                const thisYear = new Date(period.start).getFullYear();

                // Build day headers
                const dayHeaders = [];
                const ws = new Date(period.start);
                for (let d = 0; d < daysInWeek; d++) {
                    const cur = new Date(ws);
                    cur.setDate(ws.getDate() + d);
                    dayHeaders.push({
                        date: cur.toISOString().slice(0, 10),
                        dayLabel: formatDate(cur, { weekday: 'short' }),
                        dayNum: formatDate(cur, { day: '2-digit', month: '2-digit' }),
                        isToday: cur.toISOString().slice(0, 10) === today,
                        isWeekend: cur.getDay() === 0 || cur.getDay() === 6,
                    });
                }

                return (
                    <div key={period.number}>
                        {prevYear !== null && thisYear !== prevYear && (
                            <div className="flex items-center gap-4 py-3">
                                <div className="flex-1 border-t-2 border-gray-300 dark:border-gray-500" />
                                <span className="text-lg font-black text-gray-500 dark:text-gray-400 tracking-wider">{thisYear}</span>
                                <div className="flex-1 border-t-2 border-gray-300 dark:border-gray-500" />
                            </div>
                        )}
                        <div className={`bg-white dark:bg-gray-800 rounded-xl border-2 overflow-hidden shadow-sm ${isOverloaded ? 'border-red-400' : isCurrentWeek ? 'border-blue-400' : 'border-gray-200 dark:border-gray-700'}`}>
                            {/* Week header */}
                            <div className="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                <div className="flex items-center gap-3">
                                    <span className="text-lg font-black text-gray-800 dark:text-gray-100">wk. {period.number}</span>
                                    <span className="text-sm text-gray-500">
                                        {formatDate(new Date(period.start), { day: '2-digit', month: '2-digit' })}&ndash;{formatDate(new Date(period.end), { day: '2-digit', month: '2-digit' })}
                                    </span>
                                    <div className="flex gap-0.5">
                                        {Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => (
                                            <div key={s} className={`w-5 h-3 rounded-sm ${SHIFT_COLORS[s]?.bg ?? 'bg-gray-300'} flex items-center justify-center text-[7px] font-bold text-white/80`}>
                                                {SHIFT_COLORS[s]?.label ?? `S${s}`}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="flex items-center gap-4 text-xs">
                                    <span className="text-gray-500">orders: <strong className="text-gray-800 dark:text-gray-100">{period.total_orders}</strong></span>
                                    <span>load: <strong className={loadPercClass(period.total_load_percent)}>{period.total_load_percent}%</strong></span>
                                    <div className="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div className={`h-full rounded-full ${loadBarClass(period.total_load_percent)}`} style={{ width: `${Math.min(period.total_load_percent, 100)}%` }} />
                                    </div>
                                </div>
                            </div>

                            {/* Grid */}
                            <div>
                                <table className="w-full border-collapse table-fixed">
                                    <colgroup>
                                        <col style={{ width: '100px' }} />
                                        {dayHeaders.map((_, i) => <col key={i} style={{ width: `${100 / daysInWeek}%` }} />)}
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th className="p-1.5 text-left text-[10px] font-semibold text-gray-400 uppercase border-r border-gray-100 dark:border-gray-700">
                                                Line / Shift
                                            </th>
                                            {dayHeaders.map((dh) => (
                                                <th key={dh.date} className={`p-1 text-center border-r border-gray-100 dark:border-gray-700 ${dh.isToday ? 'bg-blue-100 dark:bg-blue-900/40' : ''} ${dh.isWeekend ? 'bg-gray-50/50' : ''}`}>
                                                    <div className="text-[10px] text-gray-400 uppercase">{dh.dayLabel}</div>
                                                    <div className={`text-xs font-bold ${dh.isToday ? 'text-blue-700' : 'text-gray-700 dark:text-gray-200'}`}>{dh.dayNum}</div>
                                                    {dh.isToday && <div className="h-0.5 bg-blue-500 rounded-full mt-0.5 mx-auto w-8" />}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {period.lines.map((lineData) => (
                                            <WeekLineRows
                                                key={lineData.line.id}
                                                lineData={lineData}
                                                dayHeaders={dayHeaders}
                                                shiftsPerDay={shiftsPerDay}
                                                weekNumber={period.number}
                                                maintenanceEvents={maintenanceEvents}
                                                dragOverCell={dragOverCell}
                                                dragOrderNo={dragOrderNo}
                                                selectedId={selectedId}
                                                onDragStart={onDragStart} onDragEnd={onDragEnd}
                                                onDragOver={onDragOver} onDragLeave={onDragLeave}
                                                onDrop={onDrop}
                                                onSelectOrder={onSelectOrder}
                                                onOpenAssign={onOpenAssign}
                                                onUnassign={onUnassign}
                                                onShowTip={onShowTip} onHideTip={onHideTip}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function WeekLineRows({ lineData, dayHeaders, shiftsPerDay, weekNumber, maintenanceEvents,
    dragOverCell, dragOrderNo, selectedId,
    onDragStart, onDragEnd, onDragOver, onDragLeave, onDrop,
    onSelectOrder, onOpenAssign, onUnassign, onShowTip, onHideTip }) {

    const { line, grid = {}, spans = {}, load_percent } = lineData;

    return (
        <>
            {/* Line header row */}
            <tr className="bg-gray-50/80 dark:bg-gray-700/30">
                <td colSpan={dayHeaders.length + 1} className="px-2 py-1">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-gray-700 dark:text-gray-200">{line.name}</span>
                        <div className="flex items-center gap-2">
                            <span className="text-[10px] text-gray-400">load:</span>
                            <div className="w-16 h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                <div className={`h-full rounded-full ${loadBarClass(load_percent)}`} style={{ width: `${Math.min(load_percent, 100)}%` }} />
                            </div>
                            <span className={`text-[10px] font-semibold ${loadPercClass(load_percent)}`}>{load_percent}%</span>
                        </div>
                    </div>
                </td>
            </tr>

            {/* Shift rows */}
            {Array.from({ length: shiftsPerDay }, (_, i) => i + 1).map((s) => (
                <tr key={s} className="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50/30">
                    <td className="px-2 py-2 text-[10px] font-medium border-r border-gray-100 dark:border-gray-700 whitespace-nowrap">
                        <span className="inline-flex items-center gap-1">
                            <span className={`w-2.5 h-2 rounded-sm ${SHIFT_COLORS[s]?.bg ?? 'bg-gray-300'}`} />
                            <span className="text-gray-500">{SHIFT_COLORS[s]?.label ?? `S${s}`}</span>
                            <span className="text-gray-400">{SHIFT_COLORS[s]?.hours ?? ''}</span>
                        </span>
                    </td>
                    {dayHeaders.map((dh) => {
                        const gridKey = `${dh.date}-${s}`;
                        const slotOrder = grid[gridKey];
                        const spanInfo = spans[gridKey];
                        const isCont = spanInfo?.type === 'cont';
                        if (isCont) return null;

                        const rowspan = spanInfo && ['start', 'day-start'].includes(spanInfo.type) ? spanInfo.rowspan : 1;
                        const cellId = `cell-${line.id}-${dh.date}-${s}`;
                        const isOrderObj = slotOrder && slotOrder !== '__span__' && typeof slotOrder === 'object';
                        const isDragOver = dragOverCell === cellId;

                        const cellTd = (
                            <td key={dh.date}
                                className={`p-0.5 relative border-r border-gray-50 dark:border-gray-700/30 transition-colors ${dh.isToday ? 'bg-blue-50 dark:bg-blue-900/20' : ''} ${dh.isWeekend ? 'bg-gray-50/30' : ''} ${selectedId && isOrderObj && slotOrder.id === selectedId ? 'ring-2 ring-inset ring-blue-500 bg-blue-100 dark:bg-blue-900/40' : ''}`}
                                data-cell-line={line.id} data-cell-date={dh.date} data-cell-shift={s}
                                rowSpan={rowspan > 1 ? rowspan : undefined}
                            >
                                {isOrderObj ? (
                                    <div className="relative group/cell h-full"
                                         draggable
                                         onDragStart={(e) => onDragStart(e, slotOrder.id, slotOrder.order_no)}
                                         onDragEnd={onDragEnd}
                                         onDragOver={(e) => onDragOver(e, cellId)}
                                         onDragLeave={(e) => onDragLeave(e, cellId)}
                                         onDrop={(e) => onDrop(e, line.id, dh.date, s, weekNumber)}
                                    >
                                        {(() => {
                                            const isOverdue = slotOrder.due_date && new Date(slotOrder.due_date) < new Date() && !['DONE','REJECTED','CANCELLED'].includes(slotOrder.status);
                                            const colorClass = isOverdue ? 'bg-red-500 text-white animate-pulse ring-2 ring-red-400' : (WO_COLORS[slotOrder.status] ?? 'bg-gray-200 border-gray-300 text-gray-700');
                                            return (
                                                <Link href={`/admin/work-orders/${slotOrder.id}`}
                                                   className={`block px-2 py-4 rounded text-[11px] font-medium truncate cursor-pointer hover:opacity-80 transition h-full flex items-center border-2 ${colorClass}`}
                                                   onClick={(e) => { e.preventDefault(); onSelectOrder(slotOrder, dh.date, s); }}
                                                   onMouseEnter={(e) => onShowTip(e, { order_no: slotOrder.order_no, product: slotOrder.product_name ?? '-', qty: slotOrder.planned_qty, status: STATUS_LABELS[slotOrder.status] ?? slotOrder.status })}
                                                   onMouseLeave={onHideTip}
                                                >
                                                    {slotOrder.order_no}
                                                </Link>
                                            );
                                        })()}
                                        <button onClick={(e) => { e.preventDefault(); onUnassign(slotOrder.id); }}
                                                className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-10"
                                                title="Remove from schedule">
                                            ✕
                                        </button>
                                    </div>
                                ) : (
                                    <div>
                                        <div
                                            onClick={() => onOpenAssign(line.id, dh.date, s, weekNumber)}
                                            onDragOver={(e) => { e.preventDefault(); onDragOver(e, cellId); }}
                                            onDragLeave={(e) => onDragLeave(e, cellId)}
                                            onDrop={(e) => onDrop(e, line.id, dh.date, s, weekNumber)}
                                            data-cell-line={line.id} data-cell-date={dh.date} data-cell-shift={s}
                                            className={`h-[52px] rounded transition-all cursor-pointer relative overflow-hidden ${isDragOver ? 'bg-blue-200 border-2 border-dashed border-blue-500 scale-[1.02]' : `${SHIFT_COLORS[s]?.bg ?? 'bg-gray-200'} opacity-15 hover:opacity-40`}`}
                                        >
                                            {isDragOver && dragOrderNo.current && (
                                                <span className="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-blue-700">
                                                    {dragOrderNo.current}
                                                </span>
                                            )}
                                        </div>
                                        {/* Maintenance events */}
                                        {(maintenanceEvents ?? []).filter(m => m.line_id == line.id && m.scheduled_at_date === dh.date).map((maint, mi) => (
                                            <div key={mi} className="mt-0.5 px-1.5 py-1 rounded text-[9px] font-medium truncate border border-purple-400 bg-purple-100 text-purple-800"
                                                 title={`${maint.title} — ${maint.scheduled_at_time}`}>
                                                🔧 {maint.title}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </td>
                        );
                        return cellTd;
                    })}
                </tr>
            ))}
        </>
    );
}

// ─── DailyView ────────────────────────────────────────────────────────────────

function DailyView({ data, lines, maintenanceEvents, onUnassign }) {
    const today = new Date().toISOString().slice(0, 10);
    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-x-auto">
            <table className="w-full border-collapse table-fixed" style={{ minWidth: `${data.length * 80 + 120}px` }}>
                <colgroup>
                    <col style={{ width: '120px' }} />
                    {data.map((_, i) => <col key={i} />)}
                </colgroup>
                <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                        <th className="sticky left-0 z-10 bg-white dark:bg-gray-800 p-2 text-xs font-semibold text-gray-500 text-left border-r border-gray-200 dark:border-gray-700">
                            Production line
                        </th>
                        {data.map((day) => {
                            const isToday = day.date === today;
                            const isWeekend = (() => { const d = new Date(day.date); return d.getDay() === 0 || d.getDay() === 6; })();
                            return (
                                <th key={day.date} className={`p-1.5 text-center border-r border-gray-100 dark:border-gray-700 ${isToday ? 'bg-blue-100 dark:bg-blue-900/40' : ''} ${isWeekend ? 'bg-gray-50 dark:bg-gray-800/60' : ''}`}>
                                    <div className="text-[10px] text-gray-400 uppercase">{formatDate(new Date(day.date), { weekday: 'short' })}</div>
                                    <div className={`text-xs font-bold ${isToday ? 'text-blue-700' : 'text-gray-700 dark:text-gray-200'}`}>{formatDate(new Date(day.date), { day: '2-digit', month: '2-digit' })}</div>
                                    {isToday && <div className="h-0.5 bg-blue-500 rounded-full mt-0.5 mx-auto w-8" />}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line) => (
                        <tr key={line.id} className="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50/50">
                            <td className="sticky left-0 z-10 bg-white dark:bg-gray-800 p-2 text-xs font-medium text-gray-700 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700 whitespace-nowrap">
                                {line.code ?? line.name}
                            </td>
                            {data.map((day) => {
                                const isToday = day.date === today;
                                const dayLineData = day.lines?.find(l => l.line.id === line.id);
                                const dayOrders = dayLineData?.orders ?? [];
                                const dayMaint = (maintenanceEvents ?? []).filter(m => m.line_id == line.id && m.scheduled_at_date === day.date);
                                return (
                                    <td key={day.date} className={`p-1 border-r border-gray-100 dark:border-gray-700/50 align-top ${isToday ? 'bg-blue-50 dark:bg-blue-900/10' : ''}`}>
                                        <div className="flex flex-col gap-0.5">
                                            {dayOrders.map((wo) => {
                                                const isOverdue = wo.due_date && new Date(wo.due_date) < new Date() && !['DONE','REJECTED','CANCELLED'].includes(wo.status);
                                                return (
                                                    <div key={wo.id} className="relative group/cell">
                                                        <Link href={`/admin/work-orders/${wo.id}`}
                                                           className={`block px-2 py-4 rounded text-[11px] font-medium truncate border ${isOverdue ? 'bg-red-500 border-red-600 text-white animate-pulse ring-2 ring-red-400' : `${WO_COLORS[wo.status] ?? 'bg-gray-200 border-gray-300 text-gray-800'}`}`}
                                                           title={wo.order_no}>
                                                            {wo.order_no}
                                                        </Link>
                                                        <button onClick={() => onUnassign(wo.id)}
                                                                className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-10"
                                                                title="Remove from schedule">✕</button>
                                                    </div>
                                                );
                                            })}
                                            {dayMaint.map((maint, mi) => (
                                                <div key={mi} className="block px-2 py-2 rounded text-[10px] font-medium truncate border-2 border-purple-400 bg-purple-100 text-purple-800"
                                                     title={`${maint.title} — ${maint.scheduled_at_time}`}>
                                                    {maint.title}
                                                </div>
                                            ))}
                                            {dayOrders.length === 0 && dayMaint.length === 0 && <div className="h-8" />}
                                        </div>
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── HourlyView ───────────────────────────────────────────────────────────────

function HourlyView({ data, slotMinutes, startDate, shiftsPerDay, maintenanceEvents, onUnassign, onRefresh, showToast }) {
    const scrollerRef = useRef(null);
    const [nowLeft, setNowLeft] = useState(null);
    const isToday = data?.date === new Date().toISOString().slice(0, 10);
    const dayStartIso = data?.date ? `${data.date}T00:00:00` : null;

    // Drag/resize state
    const dragRef = useRef({ active: false, mode: null, card: null, origLeft: 0, origWidth: 0, origStart: 0, origDur: 0, origLineId: null, startX: 0 });
    const [statusText, setStatusText] = useState('');

    function snapToSlot(min) { return Math.round(min / slotMinutes) * slotMinutes; }
    function fmtMin(m) { return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; }
    function toIso(min) {
        if (!dayStartIso) return '';
        const d = new Date(dayStartIso);
        d.setMinutes(d.getMinutes() + min);
        return d.toISOString();
    }

    useEffect(() => {
        const update = () => {
            if (!isToday) { setNowLeft(null); return; }
            const now = new Date();
            setNowLeft((now.getHours() * 60 + now.getMinutes()) * PX_PER_MINUTE);
        };
        update();
        if (!isToday) return;
        const t = setInterval(update, 60000);
        return () => clearInterval(t);
    }, [isToday]);

    useEffect(() => {
        if (!scrollerRef.current) return;
        if (isToday && nowLeft !== null) {
            scrollerRef.current.scrollLeft = Math.max(0, nowLeft - 200);
        } else {
            scrollerRef.current.scrollLeft = 6 * 60 * PX_PER_MINUTE;
        }
    }, []);

    // Shift boundary lines
    const shiftBoundaries = [];
    if (shiftsPerDay >= 1) {
        const len = Math.floor(1440 / shiftsPerDay);
        for (let b = len; b < 1440; b += len) shiftBoundaries.push(b);
    }

    const getCsrfToken = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

    const handleMouseDown = (e, card, mode) => {
        if (e.button !== 0) return;
        dragRef.current = {
            active: true, mode, card,
            origLeft: parseFloat(card.style.left) || 0,
            origWidth: parseFloat(card.style.width) || 0,
            origStart: parseInt(card.dataset.startMinute, 10) || 0,
            origDur: parseInt(card.dataset.durationMinutes, 10) || 0,
            origLineId: parseInt(card.dataset.lineId, 10) || null,
            startX: e.clientX,
        };
        card.classList.add(mode === 'move' ? 'opacity-80' : 'ring-2');
        setStatusText(mode === 'move' ? `${fmtMin(dragRef.current.origStart)} (move)` : `${dragRef.current.origDur}m (resize)`);
        e.preventDefault();
    };

    useEffect(() => {
        const onMove = (e) => {
            const d = dragRef.current;
            if (!d.active || !d.card) return;
            const dx = e.clientX - d.startX;
            if (d.mode === 'move') {
                const maxLeft = TOTAL_WIDTH - d.origWidth;
                const clamped = Math.max(0, Math.min(maxLeft, d.origLeft + dx));
                d.card.style.left = clamped + 'px';
                setStatusText(`${fmtMin(snapToSlot(clamped / PX_PER_MINUTE))} (${d.origDur}m)`);
            } else if (d.mode === 'resize') {
                const minW = slotMinutes * PX_PER_MINUTE;
                const maxW = TOTAL_WIDTH - d.origLeft;
                const clamped = Math.max(minW, Math.min(maxW, d.origWidth + dx));
                d.card.style.width = clamped + 'px';
                setStatusText(`${fmtMin(d.origStart)} (${snapToSlot(clamped / PX_PER_MINUTE)}m)`);
            }
        };
        const onUp = async (e) => {
            const d = dragRef.current;
            if (!d.active || !d.card) return;
            d.active = false;
            d.card.classList.remove('opacity-80', 'ring-2');
            const woId = d.card.dataset.woId;

            if (d.mode === 'move') {
                const rawMin = (parseFloat(d.card.style.left) || 0) / PX_PER_MINUTE;
                const newStart = Math.max(0, Math.min(24 * 60 - d.origDur, snapToSlot(rawMin)));
                d.card.style.left = (newStart * PX_PER_MINUTE) + 'px';
                let targetLineId = d.origLineId;
                const under = document.elementFromPoint(e.clientX, e.clientY);
                if (under) {
                    const lane = under.closest('[data-lane-id]');
                    if (lane?.dataset.laneId) targetLineId = parseInt(lane.dataset.laneId, 10);
                }
                if (newStart === d.origStart && targetLineId === d.origLineId) { setStatusText(''); return; }
                await saveHourly(woId, toIso(newStart), toIso(newStart + d.origDur), targetLineId, false, onRefresh, showToast, getCsrfToken);
                d.card.dataset.startMinute = newStart;
            } else {
                const rawDur = (parseFloat(d.card.style.width) || 0) / PX_PER_MINUTE;
                const newDur = Math.min(snapToSlot(Math.max(slotMinutes, rawDur)), 24 * 60 - d.origStart);
                d.card.style.width = (newDur * PX_PER_MINUTE) + 'px';
                if (newDur === d.origDur) { setStatusText(''); return; }
                await saveHourlyResize(woId, toIso(d.origStart), toIso(d.origStart + newDur), false, onRefresh, showToast, getCsrfToken);
                d.card.dataset.durationMinutes = newDur;
            }
            setStatusText('');
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        return () => { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); };
    }, [slotMinutes, dayStartIso]);

    if (!data || !data.lines) return <div className="p-8 text-gray-400 text-sm text-center">No hourly data.</div>;

    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60">
                <div className="flex items-center gap-3">
                    <span className="text-sm font-bold text-gray-800 dark:text-gray-100">{data.label}</span>
                    {isToday && <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">Today</span>}
                    <span className="text-[10px] text-gray-400">{slotMinutes} min snap</span>
                    {statusText && <span className="text-[10px] font-mono bg-gray-900 text-white px-2 py-0.5 rounded">{statusText}</span>}
                </div>
                <div className="flex items-center gap-3 text-[10px] text-gray-500">
                    <span className="inline-flex items-center gap-1"><span className="w-3 h-2.5 rounded-sm bg-blue-300 border border-blue-500" /> Scheduled</span>
                    <span className="inline-flex items-center gap-1"><span className="w-3 h-2.5 rounded-sm bg-red-200 border border-red-500" /> Conflict</span>
                    <span className="inline-flex items-center gap-1"><span className="w-3 h-2.5 rounded-sm bg-gray-200 border border-gray-400 border-dashed" /> Legacy</span>
                </div>
            </div>

            <div className="flex">
                {/* Left sticky: line names */}
                <div className="shrink-0 w-[200px] border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <div className="h-9 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60 flex items-center px-3">
                        <span className="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Production line</span>
                    </div>
                    {data.lines.map((lr) => {
                        const maxLanes = lr.orders.reduce((m, o) => Math.max(m, o.total_lanes ?? 1), 1);
                        const labelH = Math.max(114, 6 + maxLanes * 34);
                        return (
                            <div key={lr.line.id} className="border-b border-gray-100 dark:border-gray-700/60 px-3 py-2 flex flex-col justify-center" style={{ height: `${labelH}px` }}>
                                <div className="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{lr.line.code ?? lr.line.name}</div>
                                <div className="flex items-center gap-1.5 mt-1">
                                    <div className="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div className={`h-full rounded-full ${lr.load_percent >= 90 ? 'bg-red-500' : lr.load_percent >= 70 ? 'bg-amber-500' : 'bg-emerald-500'}`} style={{ width: `${lr.load_percent}%` }} />
                                    </div>
                                    <span className={`text-[10px] font-bold ${lr.load_percent >= 90 ? 'text-red-600' : lr.load_percent >= 70 ? 'text-amber-600' : 'text-emerald-600'}`}>{lr.load_percent}%</span>
                                </div>
                                <div className="text-[10px] text-gray-400 mt-0.5">
                                    {Math.floor(lr.used_minutes / 60)}h {lr.used_minutes % 60}m / 24h
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Right scrollable grid */}
                <div className="flex-1 overflow-x-auto" ref={scrollerRef}>
                    <div className="relative" style={{ width: `${TOTAL_WIDTH}px` }}>
                        {/* Hour axis */}
                        <div className="flex h-9 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60 sticky top-0 z-20">
                            {Array.from({ length: 24 }, (_, h) => (
                                <div key={h} className="relative shrink-0 border-r border-gray-200 dark:border-gray-700 flex items-center justify-start pl-1.5" style={{ width: `${HOUR_PX}px` }}>
                                    <span className="text-[10px] font-semibold text-gray-500">{String(h).padStart(2, '0')}:00</span>
                                    <div className="absolute inset-y-0 w-px bg-gray-200 dark:bg-gray-700/60" style={{ left: `${HOUR_PX / 4}px` }} />
                                    <div className="absolute inset-y-0 w-px bg-gray-200 dark:bg-gray-700/60" style={{ left: `${HOUR_PX / 2}px` }} />
                                    <div className="absolute inset-y-0 w-px bg-gray-200 dark:bg-gray-700/60" style={{ left: `${(HOUR_PX * 3) / 4}px` }} />
                                </div>
                            ))}
                        </div>

                        {/* Lane rows */}
                        {data.lines.map((lr) => {
                            const maxLanes = lr.orders.reduce((m, o) => Math.max(m, o.total_lanes ?? 1), 1);
                            const rowH = Math.max(114, 6 + maxLanes * 34);
                            const lineMaint = (maintenanceEvents ?? []).filter(m => m.line_id == lr.line.id && m.scheduled_at_date === data.date);
                            return (
                                <div key={lr.line.id} data-lane-id={lr.line.id}
                                     className="lane relative border-b border-gray-100 dark:border-gray-700/60"
                                     style={{ height: `${rowH}px` }}>
                                    {/* Hour gridlines */}
                                    {Array.from({ length: 23 }, (_, h) => (
                                        <div key={h} className="absolute top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700/60" style={{ left: `${(h + 1) * HOUR_PX}px` }} />
                                    ))}
                                    {/* Shift boundaries */}
                                    {shiftBoundaries.map((b) => (
                                        <div key={b} className="absolute top-0 bottom-0 w-px border-l border-dashed border-indigo-400/70 dark:border-indigo-400/50" style={{ left: `${b * PX_PER_MINUTE}px` }} />
                                    ))}
                                    {/* Now line */}
                                    {isToday && nowLeft !== null && (
                                        <div className="absolute top-0 bottom-0 w-0.5 bg-red-500 z-30 pointer-events-none" style={{ left: `${nowLeft}px` }}>
                                            <div className="absolute -top-1 -left-1 w-2 h-2 rounded-full bg-red-500" />
                                        </div>
                                    )}
                                    {/* Work order cards */}
                                    {lr.orders.map((order) => {
                                        const wo = order.wo;
                                        const totalLanes = order.total_lanes ?? 1;
                                        const lane = order.lane ?? 0;
                                        const laneH = totalLanes > 1 ? Math.max(30, Math.floor(90 / totalLanes)) : 90;
                                        const laneTop = 6 + lane * (laneH + 2);
                                        const cardClass = order.has_conflict
                                            ? 'bg-red-100 border-red-500 text-red-800'
                                            : order.is_legacy
                                                ? 'bg-gray-200 border-gray-400 text-gray-700 border-dashed'
                                                : wo.status === 'IN_PROGRESS'
                                                    ? 'bg-amber-200 border-amber-500 text-amber-900'
                                                    : wo.status === 'DONE'
                                                        ? 'bg-green-200 border-green-500 text-green-900'
                                                        : wo.status === 'BLOCKED'
                                                            ? 'bg-red-200 border-red-500 text-red-900'
                                                            : 'bg-blue-200 border-blue-500 text-blue-900';
                                        const startH = Math.floor(order.start_minute / 60);
                                        const startM = order.start_minute % 60;
                                        const endH = Math.floor(order.end_minute / 60);
                                        const endM = order.end_minute % 60;
                                        return (
                                            <div key={wo.id}
                                                 className={`wo-card absolute rounded border-2 shadow-sm px-1.5 py-1 overflow-visible cursor-grab active:cursor-grabbing select-none group ${cardClass}`}
                                                 style={{ left: `${order.start_minute * PX_PER_MINUTE}px`, width: `${Math.max(20, order.duration_minutes * PX_PER_MINUTE)}px`, top: `${laneTop}px`, height: `${laneH}px` }}
                                                 data-wo-id={wo.id} data-line-id={lr.line.id}
                                                 data-start-minute={order.start_minute} data-duration-minutes={order.duration_minutes}
                                                 onMouseDown={(e) => handleMouseDown(e, e.currentTarget, 'move')}
                                                 title={`${wo.order_no} — ${wo.product_name ?? ''}`}
                                            >
                                                {order.start_minute === 0 && wo.planned_start_at && <span className="absolute left-0 top-1/2 -translate-y-1/2 -ml-0.5 text-[12px] leading-none text-gray-600">&laquo;</span>}
                                                {order.end_minute >= data.minutes_per_day && wo.planned_end_at && <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] leading-none text-gray-600">&raquo;</span>}
                                                <div className="flex flex-col h-full pointer-events-none">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={`/admin/work-orders/${wo.id}`} className="text-[11px] font-bold truncate pointer-events-auto hover:underline" onClick={(e) => e.stopPropagation()}>
                                                            {wo.order_no}
                                                        </Link>
                                                        {order.has_conflict && <span className="text-[9px] font-bold uppercase text-red-700">Conflict</span>}
                                                        {order.is_legacy && <span className="text-[9px] font-bold uppercase text-gray-600">Legacy</span>}
                                                    </div>
                                                    <div className="text-[10px] truncate opacity-80">{wo.product_name ?? '-'}</div>
                                                    <div className="text-[9px] mt-auto opacity-70 truncate">
                                                        {String(startH).padStart(2, '0')}:{String(startM).padStart(2, '0')} – {String(endH).padStart(2, '0')}:{String(endM).padStart(2, '0')}
                                                        ({Math.floor(order.duration_minutes / 60)}h{order.duration_minutes % 60 ? ` ${order.duration_minutes % 60}m` : ''})
                                                    </div>
                                                </div>
                                                {/* Unassign */}
                                                <button className="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center opacity-0 group-hover:opacity-100 hover:!opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-20 pointer-events-auto"
                                                        onClick={(e) => { e.stopPropagation(); if (confirm('Remove this order from schedule?')) onUnassign(wo.id); }}
                                                        title="Remove from schedule">✕</button>
                                                {/* Resize handle */}
                                                {!order.is_legacy && (
                                                    <div className="wo-resize-handle absolute right-0 top-0 bottom-0 w-1.5 cursor-ew-resize bg-black/0 hover:bg-black/20 rounded-r pointer-events-auto"
                                                         onMouseDown={(e) => { e.stopPropagation(); handleMouseDown(e, e.currentTarget.closest('[data-wo-id]'), 'resize'); }} />
                                                )}
                                            </div>
                                        );
                                    })}
                                    {/* Maintenance events */}
                                    {lineMaint.map((maint, mi) => {
                                        const maintMin = maint.scheduled_at_minute ?? 0;
                                        const maintDur = maint.duration_minutes ?? 60;
                                        return (
                                            <div key={mi} className="absolute rounded-lg border-2 border-purple-500 bg-purple-200 px-2 py-1.5 text-[11px] font-bold text-purple-900 truncate z-10 shadow-md flex items-center gap-1"
                                                 style={{ left: `${maintMin * PX_PER_MINUTE}px`, width: `${Math.max(80, maintDur * PX_PER_MINUTE)}px`, top: '6px', height: '40px' }}
                                                 title={`${maint.title} — ${maint.scheduled_at_time}`}>
                                                🔧 <span className="truncate">{maint.title}</span>
                                            </div>
                                        );
                                    })}
                                    {lr.orders.length === 0 && lineMaint.length === 0 && (
                                        <div className="absolute inset-0 flex items-center justify-center text-[11px] text-gray-300 italic pointer-events-none">
                                            No scheduled orders
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}

async function saveHourly(woId, startAt, endAt, lineId, force, onRefresh, showToast, getCsrf) {
    const body = { planned_start_at: startAt, planned_end_at: endAt, line_id: lineId };
    if (force) body.force_conflict = 1;
    const r = await fetch(`/admin/schedule/${woId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    if (r.status === 409) {
        const d = await r.json().catch(() => ({}));
        if (confirm((d.message || 'Conflict detected.') + '\n\nSave anyway?')) {
            return saveHourly(woId, startAt, endAt, lineId, true, onRefresh, showToast, getCsrf);
        }
        location.reload();
        return;
    }
    if (!r.ok) { alert(`Save failed (${r.status}).`); location.reload(); return; }
    if (lineId !== parseInt(new URLSearchParams(window.location.search).get('line_id') || '0')) location.reload();
}

async function saveHourlyResize(woId, startAt, endAt, force, onRefresh, showToast, getCsrf) {
    const body = { planned_start_at: startAt, planned_end_at: endAt };
    if (force) body.force_conflict = 1;
    const r = await fetch(`/admin/schedule/${woId}/resize`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    if (r.status === 409) {
        const d = await r.json().catch(() => ({}));
        if (confirm((d.message || 'Conflict detected.') + '\n\nSave anyway?')) {
            return saveHourlyResize(woId, startAt, endAt, true, onRefresh, showToast, getCsrf);
        }
        location.reload();
    }
    if (!r.ok) { alert(`Save failed (${r.status}).`); location.reload(); }
}

// ─── MonthlyView ──────────────────────────────────────────────────────────────

function MonthlyView({ data, onUnassign }) {
    return (
        <div className="space-y-4">
            {data.map((period) => (
                <div key={`${period.month}-${period.year}`} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                        <span className="text-lg font-bold text-gray-800 dark:text-gray-100">{period.label}</span>
                        <div className="flex items-center gap-4 text-sm">
                            <span>orders: <strong>{period.total_orders}</strong></span>
                            <span>load: <strong className={loadPercClass(period.total_load_percent)}>{period.total_load_percent}%</strong></span>
                        </div>
                    </div>
                    <div className="divide-y divide-gray-100 dark:divide-gray-700/50">
                        {period.lines.map((lineData) => (
                            <div key={lineData.line.id} className="flex items-center px-4 py-2.5 hover:bg-gray-50/50">
                                <div className="w-32 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">{lineData.line.name}</div>
                                <div className="flex-1 flex flex-wrap gap-1.5">
                                    {lineData.orders.map((wo) => {
                                        const isOverdue = wo.due_date && new Date(wo.due_date) < new Date() && !['DONE','REJECTED','CANCELLED'].includes(wo.status);
                                        return (
                                            <Link key={wo.id} href={`/admin/work-orders/${wo.id}`}
                                               className={`inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-medium ${isOverdue ? 'bg-red-500 border-red-600 text-white animate-pulse ring-2 ring-red-400' : `${WO_COLORS[wo.status] ?? 'bg-gray-200 border-gray-300 text-gray-800'}`}`}>
                                                {wo.order_no} <span className="opacity-50">&middot;</span> {wo.planned_qty}pcs
                                            </Link>
                                        );
                                    })}
                                    {lineData.orders.length === 0 && <span className="text-xs text-gray-400 italic">No orders</span>}
                                </div>
                                <div className={`w-20 text-right text-sm font-bold ${loadPercClass(lineData.load_percent)}`}>
                                    {lineData.load_percent}%
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

// ─── BacklogPanel ─────────────────────────────────────────────────────────────

function BacklogPanel({ backlogOrders, backlogItems, allLines,
    search, onSearch, filterLine, onFilterLine, filterPriority, onFilterPriority,
    sort, onSort, groupedBacklog, priorityKeys, dragOverCell, onDragStart, onDragEnd, onCollapse }) {

    const totalPcs = backlogOrders.reduce((s, o) => s + (Number(o.planned_qty) || 0), 0);
    const urgentCount = backlogOrders.filter(o => (o.priority ?? 0) >= 4).length;

    return (
        <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm flex flex-col sticky top-4" style={{ height: 'calc(100vh - 180px)' }}>
            {/* Header */}
            <div className="px-3 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span className="text-sm font-bold text-gray-700 dark:text-gray-200">Backlog</span>
                    <span className="text-xs text-gray-400">({backlogOrders.length})</span>
                </div>
                <button onClick={onCollapse} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            {/* Search */}
            <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
                <input type="text" value={search} onChange={(e) => onSearch(e.target.value)}
                       placeholder="Search orders..."
                       className="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2.5 placeholder-gray-400" />
            </div>

            {/* Filters */}
            <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-700 space-y-2">
                {/* Line filter */}
                <div className="flex flex-wrap gap-1">
                    <button onClick={() => onFilterLine('')} className={`px-2 py-0.5 text-[10px] font-medium rounded transition ${filterLine === '' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>All</button>
                    {allLines.map((l) => (
                        <button key={l.id} onClick={() => onFilterLine(filterLine === String(l.id) ? '' : String(l.id))}
                                className={`px-2 py-0.5 text-[10px] font-medium rounded transition ${filterLine === String(l.id) ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                            {l.code ?? l.name}
                        </button>
                    ))}
                </div>
                {/* Priority filter */}
                <div className="flex flex-wrap gap-1">
                    {Object.entries(PRIORITY_META).map(([pv, pl]) => (
                        <button key={pv} onClick={() => onFilterPriority(filterPriority === pv ? '' : pv)}
                                className={`px-2 py-0.5 text-[10px] font-medium rounded border ${pl.bg} ${pl.color} transition ${filterPriority === pv ? 'ring-2 ring-gray-400' : ''}`}>
                            {pl.icon} {pl.label}
                        </button>
                    ))}
                </div>
                {/* Sort */}
                <div className="flex items-center gap-1.5">
                    <span className="text-[10px] text-gray-400">Sort:</span>
                    <select value={sort} onChange={(e) => onSort(e.target.value)} className="text-[10px] border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded py-0.5 px-1.5">
                        <option value="due_date">Due date</option>
                        <option value="priority">Priority</option>
                        <option value="planned_qty">Quantity</option>
                    </select>
                </div>
            </div>

            {/* Items */}
            <div className="flex-1 overflow-y-auto px-3 py-2 space-y-2">
                {backlogOrders.length === 0 ? (
                    <div className="text-center py-8">
                        <p className="text-xs text-gray-400">No unassigned orders</p>
                    </div>
                ) : priorityKeys.length === 0 ? (
                    <div className="text-center py-8 text-xs text-gray-400">No results match filters</div>
                ) : (
                    priorityKeys.map((p) => {
                        const pl = PRIORITY_META[p] ?? PRIORITY_META[3];
                        return (
                            <div key={p}>
                                <div className="flex items-center gap-1.5 pt-1">
                                    <span className={`text-[10px] font-bold ${pl.color}`}>{pl.icon} {pl.label}</span>
                                    <span className="text-[10px] text-gray-400">({groupedBacklog[p].length})</span>
                                    <div className="flex-1 border-t border-gray-100 dark:border-gray-700" />
                                </div>
                                {groupedBacklog[p].map((item) => (
                                    <div key={item.id}
                                         className={`border rounded-lg p-2.5 ${pl.bg} hover:shadow-sm transition text-xs cursor-grab active:cursor-grabbing`}
                                         draggable
                                         onDragStart={(e) => onDragStart(e, item.id)}
                                         onDragEnd={onDragEnd}
                                    >
                                        <div className="flex items-start justify-between mb-1.5">
                                            <div>
                                                <Link href={`/admin/work-orders/${item.id}`} className="font-bold text-gray-800 dark:text-gray-100 hover:underline">
                                                    {item.order_no}
                                                </Link>
                                                <div className="text-[10px] text-gray-500 mt-0.5">{item.product}</div>
                                            </div>
                                            <span className={`px-1.5 py-0.5 rounded text-[9px] font-medium border ${WO_COLORS[backlogOrders.find(o => o.id === item.id)?.status] ?? 'bg-gray-200 border-gray-300 text-gray-700'}`}>
                                                {item.status}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[10px] text-gray-600 dark:text-gray-400">
                                            <div>Qty: <strong className="text-gray-800 dark:text-gray-200">{formatNumber(Number(item.qty))}</strong></div>
                                            <div>Due: <strong className={item.due_date !== '-' && new Date(item.due_date) < new Date() ? 'text-red-600' : 'text-gray-800 dark:text-gray-200'}>{item.due_date}</strong></div>
                                            <div>Line: <strong className="text-gray-800 dark:text-gray-200">{item.line_id ? allLines.find(l => l.id == item.line_id)?.name ?? 'unassigned' : 'unassigned'}</strong></div>
                                            <div>Priority: <strong className={pl.color}>{item.priority ?? '-'}</strong></div>
                                        </div>
                                        {!item.line_id && (
                                            <div className="mt-1.5 text-[10px] text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-1">
                                                Suggestion: Assign to available line with free capacity
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        );
                    })
                )}
            </div>

            {/* Footer */}
            <div className="px-3 py-2.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/80 rounded-b-xl">
                <div className="grid grid-cols-3 gap-2 text-center mb-2.5">
                    <div>
                        <div className="text-[10px] text-gray-400">total pcs</div>
                        <div className="text-sm font-bold text-gray-800 dark:text-gray-100">{formatNumber(totalPcs)}</div>
                    </div>
                    <div>
                        <div className="text-[10px] text-gray-400">orders</div>
                        <div className="text-sm font-bold text-gray-800 dark:text-gray-100">{backlogOrders.length}</div>
                    </div>
                    <div>
                        <div className="text-[10px] text-gray-400">urgent</div>
                        <div className="text-sm font-bold text-red-600">{urgentCount}</div>
                    </div>
                </div>
                <div className="flex gap-1.5">
                    <Link href="/admin/work-orders/create" className="flex-1 text-center py-1.5 rounded-lg text-[10px] font-medium bg-blue-600 text-white hover:bg-blue-700 transition">
                        + Add
                    </Link>
                    <Link href="/admin/csv-import" className="flex-1 text-center py-1.5 rounded-lg text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition border border-gray-300 dark:border-gray-600">
                        Import CSV
                    </Link>
                </div>
            </div>
        </div>
    );
}
