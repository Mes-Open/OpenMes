// WeeklyView (day×shift column gantt) + DailyView, following the OpenMES Schedule
// design. Weekly orders are spanning blocks: drag to move (across shifts, days and
// lines), drag the edges to stretch across shifts/days. Backlog cards drop onto
// any cell via react-dnd; scheduled blocks move via pointer (hit-testing cells).
import { useState, memo } from 'react';
import { __, formatDate } from '../../../../lib/i18n';
import { OrderCard } from './OrderCard';
import { DraggableOrder, useOrderDrop } from './dnd';
import {
    weeklySlot, weeklyPlacements, lineLoad, loadColor, shiftColor, statusOf, fmtQty, parseDate, dayList, MONO,
} from './helpers';

const LINE_COL_W = 172;
const COL_MIN = 92;
const LANE_H = 46;
const LANE_GAP = 5;
// Minimum row height — keep rows at least as tall as the (compact) line-info
// column so a single-lane block fills the cell with no gap.
const MIN_ROW = 56;
// Height of the maintenance strip tucked directly under the work blocks, so an
// order and a maintenance event sharing a cell are both visible and connected.
const MAINT_H = 17;

const fmtDow = (d) => formatDate(parseDate(d), { weekday: 'short' });
const fmtDayMon = (d) => formatDate(parseDate(d), { day: '2-digit', month: 'short' });

function MaintPill({ m }) {
    return (
        <div className="truncate" style={{ border: '1px dashed var(--om-maint)', background: 'var(--om-maint-bg)', borderRadius: 6, padding: '3px 7px', display: 'flex', alignItems: 'center', gap: 5 }}
            title={m.title + (m.scheduled_at_time ? ' · ' + m.scheduled_at_time : '')}>
            <span style={{ width: 5, height: 5, borderRadius: 999, background: 'var(--om-maint)', flexShrink: 0 }} />
            <span className="truncate" style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-maint)' }}>{m.title}</span>
        </div>
    );
}

// Background drop cell — a react-dnd target for backlog cards + a pointer
// hit-test target (data-cell) for moving scheduled blocks; click to assign.
// Memoised so the drag preview re-render doesn't re-run every cell.
const WeekDropCell = memo(function WeekDropCell({ line, date, shift, col, N, ctx, today, weekend, firstShift }) {
    const target = { lineId: line.id, date, shift };
    const [isOver, drop] = useOrderDrop(target, ctx.onDropOrder);
    return (
        <div ref={drop} data-cell={`${line.id}|${date}|${shift}`} onClick={() => ctx.onCellClick(target)}
            style={{
                position: 'absolute', top: 0, bottom: 0, left: (col / N * 100) + '%', width: (100 / N) + '%',
                borderRight: '1px solid var(--om-line2)', borderLeft: firstShift ? '1px solid var(--om-line2)' : 'none',
                background: isOver ? 'var(--om-accent-bg)' : today ? 'color-mix(in srgb, var(--om-accent-bg) 40%, transparent)' : weekend ? 'color-mix(in srgb, var(--om-chip) 50%, transparent)' : 'transparent',
                boxShadow: isOver ? 'inset 0 0 0 1.5px var(--om-accent)' : 'none', cursor: 'pointer',
            }} />
    );
});

// A spanning, movable, resizable order block.
function WeekBlock({ item, ctx, N, laneH, setPreview }) {
    const { wo } = item;
    const [drag, setDrag] = useState(null); // { startCol, endCol, lineId, mode }
    const s = statusOf(wo.status);
    // While moving, the block stays put (dimmed) and only the ghost follows the
    // cursor (in any direction). While resizing, the block itself follows.
    const moving = drag && drag.mode === 'move';
    const pos = (drag && !moving) ? drag : { startCol: item.startCol, endCol: item.endCol };

    function begin(mode, e) {
        e.preventDefault(); e.stopPropagation();
        const row = e.currentTarget.closest('[data-weekrow]');
        const ppc = (row ? row.getBoundingClientRect().width : 700) / N;
        const startX = e.clientX, oS = item.startCol, oE = item.endCol;
        const rows = [...document.querySelectorAll('[data-weekrow]')].map((el) => {
            const r = el.getBoundingClientRect();
            return { lineId: +el.getAttribute('data-line'), top: r.top, bottom: r.bottom };
        });
        function move(ev) {
            const dCol = Math.round((ev.clientX - startX) / ppc);
            let ns = oS, ne = oE, ln = wo.line_id;
            if (mode === 'move') {
                ns = oS + dCol; ne = oE + dCol;
                if (ns < 0) { ne -= ns; ns = 0; }
                if (ne > N - 1) { ns -= (ne - (N - 1)); ne = N - 1; }
                for (const r of rows) { if (ev.clientY >= r.top && ev.clientY <= r.bottom) { ln = r.lineId; break; } }
            } else if (mode === 'l') {
                ns = Math.max(0, Math.min(oE, oS + dCol));
            } else {
                ne = Math.min(N - 1, Math.max(oS, oE + dCol));
            }
            setDrag({ startCol: ns, endCol: ne, lineId: ln, mode });
            if (mode === 'move') setPreview({ lineId: ln, startCol: ns, endCol: ne });
        }
        function up() {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            setPreview(null);
            setDrag((d) => {
                if (d && (d.startCol !== item.startCol || d.endCol !== item.endCol || (d.lineId && d.lineId !== wo.line_id))) {
                    ctx.onSpanChange(wo, d.startCol, d.endCol, d.lineId !== wo.line_id ? d.lineId : undefined);
                }
                return null;
            });
        }
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    const left = (pos.startCol / N) * 100;
    const width = ((pos.endCol - pos.startCol + 1) / N) * 100;
    return (
        <div style={{ position: 'absolute', left: left + '%', width: width + '%', top: LANE_GAP + item.lane * (laneH + LANE_GAP), height: laneH, padding: '0 2px', zIndex: drag ? 30 : 5 }}>
            <div className="om-wo relative" style={{ height: '100%', background: s.soft, border: '1px solid var(--om-line2)', borderRadius: 6, overflow: 'hidden', opacity: moving ? 0.3 : 1, boxShadow: wo.is_overdue ? '0 0 0 1.5px var(--om-blocked)' : 'none', touchAction: 'none' }}>
                <span className="om-x" onClick={(e) => { e.stopPropagation(); ctx.onUnassign(wo); }} title={__('Send to backlog')}
                    style={{ position: 'absolute', top: -6, right: -6, width: 15, height: 15, borderRadius: 999, background: 'var(--om-blocked)', color: '#fff', fontSize: 8, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0, transition: 'opacity .15s', zIndex: 4, cursor: 'pointer' }}>✕</span>
                <span onPointerDown={(e) => begin('l', e)} style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: 6, cursor: 'ew-resize', zIndex: 3 }} />
                <div onPointerDown={(e) => begin('move', e)} onClick={(e) => { e.stopPropagation(); ctx.onSelectOrder(wo); }}
                    style={{ height: '100%', padding: '5px 9px', cursor: 'grab', display: 'flex', flexDirection: 'column', gap: 1, overflow: 'hidden' }}>
                    <div className="flex items-center gap-1.5">
                        <span className="whitespace-nowrap" style={{ fontFamily: MONO, fontSize: 10, fontWeight: 600, color: 'var(--om-ink)' }}>{wo.order_no}</span>
                        {wo.is_overdue && <span className="ml-auto" style={{ fontFamily: MONO, fontSize: 8, color: '#fff', background: 'var(--om-blocked)', borderRadius: 3, padding: '0 3px' }}>!</span>}
                    </div>
                    <span className="truncate" style={{ fontSize: 10, color: 'var(--om-muted)' }}>{wo.product_name || '—'} · {fmtQty(wo.planned_qty)}</span>
                </div>
                <span onPointerDown={(e) => begin('r', e)} className="flex items-center justify-center" style={{ position: 'absolute', right: 0, top: 0, bottom: 0, width: 7, cursor: 'ew-resize', zIndex: 3 }}>
                    <span style={{ width: 2, height: 14, borderRadius: 2, background: s.solid, opacity: 0.5 }} />
                </span>
            </div>
        </div>
    );
}

function StickyLineCell({ line, load, lc }) {
    return (
        <div style={{ width: LINE_COL_W, flexShrink: 0, position: 'sticky', left: 0, zIndex: 4, background: 'var(--om-card)', borderRight: '1px solid var(--om-line2)', padding: '7px 12px', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
            <div className="flex items-center gap-2">
                <span style={{ width: 8, height: 8, borderRadius: 999, background: lc, flexShrink: 0 }} />
                <span style={{ fontFamily: MONO, fontSize: 12, fontWeight: 600, color: 'var(--om-ink)' }}>{line.code}</span>
                <span className="truncate" style={{ fontSize: 11, color: 'var(--om-muted)' }}>{line.name}</span>
            </div>
            <div className="flex items-center gap-2" style={{ marginTop: 5 }}>
                <div className="rounded-full overflow-hidden" style={{ flex: 1, height: 5, background: 'var(--om-chip)', border: '1px solid var(--om-line2)' }}>
                    <div style={{ width: Math.min(100, load) + '%', height: '100%', background: lc, borderRadius: 20 }} />
                </div>
                <span style={{ fontFamily: MONO, fontSize: 10, fontWeight: 600, color: lc }}>{load}%</span>
            </div>
        </div>
    );
}

function WeekLineRow({ line, ctx, days, shiftsPerDay, today, gridMinW, preview, setPreview }) {
    const { items, lanes, N } = weeklyPlacements(ctx.data.workOrders.filter((o) => o.line_id === line.id), days, shiftsPerDay);
    const load = lineLoad(ctx.data.workOrders, line.id, days, shiftsPerDay);
    const lc = loadColor(load);
    const maint = (ctx.data.maintenance || []).filter((m) => m.line_id === line.id && days.some((d) => d.date === m.scheduled_at_date));
    const hasMaint = maint.length > 0;
    // Work blocks fill the top area; the maintenance strip tucks directly under
    // them (a couple px gap) so they read as connected within the same cell.
    const maintReserve = hasMaint ? MAINT_H + 2 : 0;
    const rowH = Math.max(MIN_ROW, lanes * (LANE_H + LANE_GAP) + LANE_GAP + maintReserve);
    const blockAreaH = rowH - maintReserve;
    const laneH = (blockAreaH - (lanes + 1) * LANE_GAP) / lanes;
    const maintTop = blockAreaH - LANE_GAP + 2;
    const showGhost = preview && preview.lineId === line.id;
    return (
        <div className="flex" style={{ height: rowH, borderBottom: '1px solid var(--om-line2)' }}>
            <StickyLineCell line={line} load={load} lc={lc} />
            <div data-weekrow data-line={line.id} style={{ flex: 1, minWidth: gridMinW, position: 'relative', height: rowH }}>
                {Array.from({ length: N }, (_, col) => {
                    const di = Math.floor(col / shiftsPerDay);
                    const shift = (col % shiftsPerDay) + 1;
                    const d = days[di];
                    return <WeekDropCell key={col} line={line} date={d.date} shift={shift} col={col} N={N} ctx={ctx} today={d.date === today} weekend={d.isWeekend} firstShift={col % shiftsPerDay === 0} />;
                })}
                {items.map((it) => <WeekBlock key={it.wo.id} item={it} ctx={ctx} N={N} laneH={laneH} setPreview={setPreview} />)}
                {/* drop preview ghost — where the dragged block will land */}
                {showGhost && (
                    <div style={{ position: 'absolute', left: (preview.startCol / N * 100) + '%', width: ((preview.endCol - preview.startCol + 1) / N * 100) + '%', top: LANE_GAP, height: laneH, padding: '0 2px', zIndex: 20, pointerEvents: 'none' }}>
                        <div style={{ height: '100%', border: '2px dashed var(--om-accent)', borderRadius: 6, background: 'color-mix(in srgb, var(--om-accent-bg) 70%, transparent)' }} />
                    </div>
                )}
                {/* maintenance strip — connected directly under the work blocks */}
                {maint.map((m, i) => {
                    const di = days.findIndex((d) => d.date === m.scheduled_at_date);
                    if (di < 0) return null;
                    const hr = Math.floor((m.scheduled_at_minute ?? 0) / 60);
                    const sh = Math.min(shiftsPerDay - 1, Math.floor(hr / (24 / shiftsPerDay)));
                    const col = di * shiftsPerDay + sh;
                    return (
                        <div key={'m' + i} style={{ position: 'absolute', left: (col / N * 100) + '%', width: (100 / N) + '%', top: maintTop, height: MAINT_H, padding: '0 3px', zIndex: 6 }}>
                            <MaintPill m={m} />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export function WeeklyView({ ctx }) {
    const { data, config } = ctx;
    const days = ctx.days;
    const shiftsPerDay = config.shiftsPerDay;
    const [preview, setPreview] = useState(null);
    if (!days.length) return null;
    const today = data.range.today;
    const N = days.length * shiftsPerDay;
    const dayCols = `repeat(${days.length}, minmax(${COL_MIN * shiftsPerDay}px, 1fr))`;
    const shiftCols = `repeat(${N}, minmax(${COL_MIN}px, 1fr))`;
    const gridMinW = N * COL_MIN;

    return (
        <div className="om-grid" style={{ overflow: 'auto' }}>
            <div style={{ minWidth: LINE_COL_W + gridMinW, border: '1px solid var(--om-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--om-card)' }}>
                {/* header: line column + day labels + shift labels */}
                <div className="flex" style={{ position: 'sticky', top: 0, zIndex: 6, borderBottom: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
                    <div style={{ width: LINE_COL_W, flexShrink: 0, position: 'sticky', left: 0, zIndex: 7, background: 'var(--om-panel)', borderRight: '1px solid var(--om-line2)', display: 'flex', alignItems: 'flex-end', padding: '11px 14px' }}>
                        <span style={{ fontFamily: MONO, fontSize: 9, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{__('Line · Load')}</span>
                    </div>
                    <div style={{ flex: 1, minWidth: gridMinW }}>
                        <div style={{ display: 'grid', gridTemplateColumns: dayCols }}>
                            {days.map((d) => {
                                const isToday = d.date === today;
                                return (
                                    <div key={d.date} className="flex items-baseline justify-center gap-1.5" style={{ padding: '9px 6px', borderRight: '1px solid var(--om-line2)', background: isToday ? 'var(--om-accent-bg)' : (d.isWeekend ? 'var(--om-chip)' : 'transparent') }}>
                                        <span style={{ fontFamily: MONO, fontSize: 9, letterSpacing: '0.08em', textTransform: 'uppercase', color: isToday ? 'var(--om-accent)' : 'var(--om-faint)' }}>{fmtDow(d.date)}</span>
                                        <span style={{ fontFamily: MONO, fontSize: 13, fontWeight: 600, color: isToday ? 'var(--om-accent)' : (d.isWeekend ? 'var(--om-faint)' : 'var(--om-ink)') }}>{fmtDayMon(d.date)}</span>
                                        {isToday && <div style={{ height: 2, width: 18, background: 'var(--om-accent)', borderRadius: 2, alignSelf: 'center' }} />}
                                    </div>
                                );
                            })}
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: shiftCols, borderTop: '1px solid var(--om-line2)' }}>
                            {Array.from({ length: N }, (_, col) => {
                                const shiftNum = (col % shiftsPerDay) + 1;
                                const sh = data.shifts[shiftNum - 1];
                                return (
                                    <div key={col} className="flex items-center justify-center gap-1.5" style={{ padding: '6px 4px', borderRight: '1px solid var(--om-line2)', borderLeft: col % shiftsPerDay === 0 ? '1px solid var(--om-line2)' : 'none' }}>
                                        <span style={{ width: 7, height: 7, borderRadius: 2, background: shiftColor(shiftNum), flexShrink: 0 }} />
                                        <span className="truncate" style={{ fontFamily: MONO, fontSize: 9.5, fontWeight: 500, color: 'var(--om-muted)' }}>{sh ? sh.name : 'S' + shiftNum}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
                {/* line rows */}
                {data.lines.map((line) => <WeekLineRow key={line.id} line={line} ctx={ctx} days={days} shiftsPerDay={shiftsPerDay} today={today} gridMinW={gridMinW} preview={preview} setPreview={setPreview} />)}
            </div>
            <div style={{ marginTop: 14, fontFamily: MONO, fontSize: 11, color: 'var(--om-faint)' }}>
                {__('Drag a block to move it (across shifts, days or lines) · drag its edges to stretch across shifts · click an empty cell to assign · ✕ returns to backlog')}
            </div>
        </div>
    );
}

// ── DAILY VIEW — one day, orders grouped per line ───────────────────────────
function DailyLine({ line, date, ctx }) {
    const [isOver, drop] = useOrderDrop({ lineId: line.id, date, shift: 1 }, ctx.onDropOrder);
    const wos = ctx.data.workOrders.filter((o) => o.line_id === line.id && weeklySlot(o, 1).date === date);
    const lc = loadColor(lineLoad(ctx.data.workOrders, line.id, [{ date }], 1) || 0);
    return (
        <div style={{ display: 'flex', borderBottom: '1px solid var(--om-line2)' }}>
            <div style={{ width: 170, flexShrink: 0, padding: 14, borderRight: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
                <div className="flex items-center gap-2 mb-1.5">
                    <span style={{ width: 8, height: 8, borderRadius: 999, background: lc }} />
                    <span style={{ fontFamily: MONO, fontSize: 12, fontWeight: 600, color: 'var(--om-ink)' }}>{line.code}</span>
                </div>
                <div style={{ fontSize: 11, color: 'var(--om-muted)' }}>{line.name}</div>
            </div>
            <div ref={drop} className="flex gap-2.5 flex-wrap items-start"
                style={{ flex: 1, padding: '12px 14px', minHeight: 60, background: isOver ? 'var(--om-accent-bg)' : 'transparent' }}>
                {wos.map((wo) => (
                    <DraggableOrder key={wo.id} wo={wo}>
                        <OrderCard wo={wo} variant="day" selected={ctx.selectedId === wo.id}
                            onClick={(e) => { e.stopPropagation(); ctx.onSelectOrder(wo); }} onUnassign={ctx.onUnassign} />
                    </DraggableOrder>
                ))}
                {wos.length === 0 && <span style={{ fontFamily: MONO, fontSize: 11, color: 'var(--om-faintest)', padding: 8 }}>— {__('idle')} —</span>}
            </div>
        </div>
    );
}

// One day section: header + a row per line. The daily view stacks these for
// every day in the visible range so a planner can scan several days at once.
function DayBlock({ date, ctx, today }) {
    const isToday = date === today;
    const count = ctx.data.workOrders.filter((o) => weeklySlot(o, ctx.config.shiftsPerDay).date === date).length;
    return (
        <div style={{ border: '1px solid var(--om-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--om-card)' }}>
            <div className="flex items-center justify-between" style={{ padding: '14px 18px', borderBottom: '1px solid var(--om-line2)', background: isToday ? 'var(--om-accent-bg)' : 'var(--om-panel)' }}>
                <span style={{ fontSize: 15, fontWeight: 600, color: isToday ? 'var(--om-accent)' : 'var(--om-ink)' }}>{formatDate(parseDate(date), { weekday: 'long', day: '2-digit', month: 'long' })}</span>
                <span style={{ fontFamily: MONO, fontSize: 11, color: 'var(--om-faint)' }}>{count} {__('orders')}</span>
            </div>
            {ctx.data.lines.map((line) => <DailyLine key={line.id} line={line} date={date} ctx={ctx} />)}
        </div>
    );
}

export function DailyView({ ctx }) {
    const { data, config } = ctx;
    const range = dayList(data.range.start, 14, config.showWeekends);
    if (!range.length) return null;
    return (
        <div className="flex flex-col gap-4">
            {range.map((d) => <DayBlock key={d.date} date={d.date} ctx={ctx} today={data.range.today} />)}
        </div>
    );
}
