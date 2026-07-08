// HourlyView (timeline, lanes, conflicts, move/resize) + MonthlyView (calendar),
// following the OpenMES Schedule design. Bars are positioned as a % of the day,
// drag uses px→minute conversion measured from the track width.
import { useState, useRef } from 'react';
import { __, formatDate, formatTime } from '../../../../lib/i18n';
import { TwinChip } from './OrderCard';
import {
    hourlyLanes, onMonthlyDay, statusOf, parseDate, todayKey, loadColor, chainChipMeta, MONO,
} from './helpers';

const HLANE = 46;
const HGAP = 5;
const LBL_W = 150;

function fmtMin(m) {
    m = ((Math.round(m) % 1440) + 1440) % 1440;
    const p = (n) => (n < 10 ? '0' + n : '' + n);
    return p(Math.floor(m / 60)) + ':' + p(m % 60);
}

function HourlyBar({ item, ctx, slotMinutes, laneTop }) {
    const { wo } = item;
    const twinMeta = chainChipMeta(wo, item.placementKey, ctx.data.allLines);
    const readOnly = item.spansOutside || item.placementKey !== 'primary';
    const [drag, setDrag] = useState(null);
    // Suppress the click the browser fires after a real drag — it must not
    // open the edit sheet.
    const draggedRef = useRef(false);
    const cur = drag || { start: item.start, end: item.end };
    const s = statusOf(wo.status);

    function begin(mode, e) {
        if (readOnly) return;
        e.preventDefault(); e.stopPropagation();
        const track = e.currentTarget.closest('[data-track]');
        const ppm = (track ? track.getBoundingClientRect().width : 700) / 1440;
        const startX = e.clientX, oS = item.start, oE = item.end;
        draggedRef.current = false;
        const snap = (m) => Math.round(m / slotMinutes) * slotMinutes;
        function move(ev) {
            if (Math.abs(ev.clientX - startX) > 4) draggedRef.current = true;
            const d = (ev.clientX - startX) / ppm;
            let ns = oS, ne = oE;
            if (mode === 'move') { ns = snap(oS + d); ne = ns + (oE - oS); if (ns < 0) { ne -= ns; ns = 0; } if (ne > 1440) { ns -= (ne - 1440); ne = 1440; } }
            else if (mode === 'l') { ns = snap(oS + d); ns = Math.max(0, Math.min(ne - slotMinutes, ns)); }
            else { ne = snap(oE + d); ne = Math.min(1440, Math.max(ns + slotMinutes, ne)); }
            setDrag({ start: ns, end: ne });
        }
        function up() {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            setDrag((d) => { if (d && (d.start !== item.start || d.end !== item.end)) ctx.onHourlyChange(wo, d.start, d.end); return null; });
        }
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    const left = (cur.start / 1440) * 100;
    const width = ((cur.end - cur.start) / 1440) * 100;
    const dur = (Math.round((cur.end - cur.start) / 6) / 10) + 'h';

    return (
        <div style={{ position: 'absolute', top: laneTop, height: HLANE, left: left + '%', width: width + '%', minWidth: 10, zIndex: drag ? 30 : 2 }}>
            <div className="om-wo relative" title={item.placeholder && !readOnly ? __('No exact time yet — drag to schedule') : undefined} style={{ height: '100%', background: s.soft, border: item.placeholder ? '1px dashed var(--om-accent)' : '1px solid var(--om-line2)', borderRadius: 7, overflow: 'hidden', boxShadow: item.conflict ? '0 0 0 1.5px var(--om-blocked)' : 'none' }}>
                {!readOnly && <span onPointerDown={(e) => begin('l', e)} style={{ position: 'absolute', left: 0, top: 0, bottom: 0, width: 7, cursor: 'ew-resize', zIndex: 3 }} />}
                <div onPointerDown={(e) => begin('move', e)} onClick={(e) => { e.stopPropagation(); if (draggedRef.current) { draggedRef.current = false; return; } ctx.onSelectOrder(wo); }}
                    style={{ height: '100%', padding: '6px 10px', cursor: readOnly ? 'pointer' : 'grab', display: 'flex', flexDirection: 'column', gap: 1, overflow: 'hidden' }}>
                    <div className="flex items-center gap-1.5">
                        <span style={{ fontFamily: MONO, fontSize: 10, fontWeight: 600, color: 'var(--om-ink)', whiteSpace: 'nowrap' }}>{wo.order_no}</span>
                        {twinMeta && <TwinChip code={twinMeta.code} dir={twinMeta.dir} />}
                        {width > 16 && <span className="truncate" style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-muted)' }}>{wo.product_name}</span>}
                    </div>
                    <span style={{ fontFamily: MONO, fontSize: 9, fontWeight: 500, color: 'var(--om-muted)', whiteSpace: 'nowrap' }}>{fmtMin(cur.start)}–{fmtMin(cur.end)} · {dur}</span>
                </div>
                {item.conflict && <span style={{ position: 'absolute', top: 3, right: 9, fontFamily: MONO, fontSize: 7.5, fontWeight: 700, letterSpacing: '0.04em', textTransform: 'uppercase', color: '#fff', background: 'var(--om-blocked)', borderRadius: 3, padding: '1px 4px', zIndex: 4, pointerEvents: 'none' }}>{__('overlap')}</span>}
                {readOnly && <span title={item.placementKey !== 'primary' ? __('Minute plan is shared — edit it from the primary line') : __('Spans another day — edit it from that day')} style={{ position: 'absolute', bottom: 2, right: 4, fontSize: 9, color: 'var(--om-faint)' }}>⤢</span>}
                {!readOnly && <span onPointerDown={(e) => begin('r', e)} style={{ position: 'absolute', right: 0, top: 0, bottom: 0, width: 7, cursor: 'ew-resize', zIndex: 3, display: 'flex', alignItems: 'center', justifyContent: 'center' }}><span style={{ width: 2, height: 14, borderRadius: 2, background: s.solid, opacity: 0.5 }} /></span>}
            </div>
        </div>
    );
}

export function HourlyView({ ctx }) {
    const { data, config } = ctx;
    const [snap, setSnap] = useState(config.slotMinutes || 15);
    const dateStr = data.range.start;
    const hours = Array.from({ length: 12 }, (_, i) => String(i * 2).padStart(2, '0'));
    const nowMin = dateStr === todayKey() ? (() => {
        const [h, m] = formatTime(new Date(), { hour: '2-digit', minute: '2-digit', hour12: false }).split(':').map(Number);
        return (h || 0) * 60 + (m || 0);
    })() : null;

    return (
        <div>
            <div className="flex items-center gap-2.5 mb-3">
                <span style={{ fontFamily: MONO, fontSize: 9, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{__('Snap')}</span>
                <div className="flex gap-0.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 8, padding: 3 }}>
                    {[5, 10, 15, 30].map((n) => (
                        <span key={n} onClick={() => setSnap(n)}
                            style={{ fontFamily: MONO, fontSize: 11, padding: '5px 10px', borderRadius: 6, cursor: 'pointer', ...(snap === n ? { background: 'var(--om-ink)', color: 'var(--om-on-ink)' } : { color: 'var(--om-muted)' }) }}>{n}m</span>
                    ))}
                </div>
                <div style={{ flex: 1 }} />
                <span className="flex items-center gap-1.5" style={{ fontFamily: MONO, fontSize: 10, color: 'var(--om-faint)' }}><span style={{ width: 8, height: 8, borderRadius: 2, boxShadow: '0 0 0 1.5px var(--om-blocked)' }} />{__('overlap')}</span>
            </div>

            <div className="om-grid" style={{ overflow: 'auto', border: '1px solid var(--om-line)', borderRadius: 12, background: 'var(--om-card)' }}>
                <div style={{ minWidth: 920 }}>
                    {/* hour header */}
                    <div className="flex" style={{ borderBottom: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
                        <div style={{ width: LBL_W, flexShrink: 0, padding: '10px 14px', fontFamily: MONO, fontSize: 9, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'var(--om-faint)', borderRight: '1px solid var(--om-line2)' }}>
                            {formatDate(parseDate(dateStr), { weekday: 'short', day: '2-digit', month: 'short' })}
                        </div>
                        <div className="flex" style={{ flex: 1 }}>
                            {hours.map((h) => <div key={h} style={{ flex: 1, padding: '10px 0', textAlign: 'center', fontFamily: MONO, fontSize: 9.5, color: 'var(--om-faint)', borderRight: '1px solid var(--om-line2)' }}>{h}</div>)}
                        </div>
                    </div>
                    {/* line tracks */}
                    {data.lines.map((line) => {
                        const { items, totalLanes } = hourlyLanes(data.workOrders, line.id, dateStr);
                        const h = totalLanes * (HLANE + HGAP) + 11;
                        return (
                            <div key={line.id} className="flex" style={{ borderBottom: '1px solid var(--om-line2)', minHeight: 60 }}>
                                <div style={{ width: LBL_W, flexShrink: 0, padding: '12px 14px', borderRight: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
                                    <div className="flex items-center gap-1.5 mb-0.5">
                                        <span style={{ width: 7, height: 7, borderRadius: 999, background: 'var(--om-accent)' }} />
                                        <span style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 600, color: 'var(--om-ink)' }}>{line.code}</span>
                                    </div>
                                    <div style={{ fontSize: 10, color: 'var(--om-muted)' }}>{line.name}</div>
                                </div>
                                <div data-track="1" style={{ flex: 1, position: 'relative', minHeight: h }}>
                                    <div style={{ position: 'absolute', inset: 0, display: 'flex', pointerEvents: 'none' }}>
                                        {hours.map((h2) => <div key={h2} style={{ flex: 1, borderRight: '1px solid var(--om-line2)', opacity: 0.6 }} />)}
                                    </div>
                                    {nowMin != null && (
                                        <div style={{ position: 'absolute', top: 0, bottom: 0, left: (nowMin / 1440 * 100) + '%', width: 1.5, background: 'var(--om-accent)', pointerEvents: 'none', zIndex: 1 }}>
                                            <span style={{ position: 'absolute', top: -1, left: -3, width: 7, height: 7, borderRadius: 999, background: 'var(--om-accent)' }} />
                                        </div>
                                    )}
                                    {items.map((it) => <HourlyBar key={it.wo.id + ':' + it.placementKey} item={it} ctx={ctx} slotMinutes={snap} laneTop={it.lane * (HLANE + HGAP) + 8} />)}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
            <div style={{ marginTop: 14, fontFamily: MONO, fontSize: 11, color: 'var(--om-faint)' }}>
                {__('Drag a bar sideways to reschedule · drag edges to resize · snaps to')} {snap} {__('min')}
            </div>
        </div>
    );
}

// ── MONTHLY — calendar for the month containing the range start ─────────────
export function MonthlyView({ ctx }) {
    const { data } = ctx;
    const anchor = parseDate(data.range.start) || new Date();
    const y = anchor.getFullYear(); const mo = anchor.getMonth();
    const monthLabel = formatDate(new Date(y, mo, 1, 12), { month: 'long', year: 'numeric' });
    const firstDow = (new Date(y, mo, 1).getDay() + 6) % 7; // Monday-first
    const dim = new Date(y, mo + 1, 0).getDate();
    const dow = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
    const today = todayKey();

    const cells = [];
    for (let i = 0; i < firstDow; i++) cells.push(null);
    for (let d = 1; d <= dim; d++) {
        const iso = `${y}-${String(mo + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const orders = data.workOrders.filter((o) => onMonthlyDay(o, iso, d, mo + 1));
        cells.push({ d, iso, orders });
    }

    return (
        <div style={{ border: '1px solid var(--om-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--om-card)' }}>
            <div className="flex items-center justify-between" style={{ padding: '12px 16px', borderBottom: '1px solid var(--om-line2)' }}>
                <span style={{ fontSize: 15, fontWeight: 600, color: 'var(--om-ink)' }}>{monthLabel}</span>
                <span style={{ fontFamily: MONO, fontSize: 11, color: 'var(--om-faint)' }}>{__('read-only')}</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', borderBottom: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
                {dow.map((d) => <div key={d} style={{ padding: 10, textAlign: 'center', fontFamily: MONO, fontSize: 9, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{d}</div>)}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)' }}>
                {cells.map((c, i) => {
                    if (!c) return <div key={'e' + i} style={{ minHeight: 96, borderRight: '1px solid var(--om-line2)', borderBottom: '1px solid var(--om-line2)', background: 'var(--om-panel)' }} />;
                    const isToday = c.iso === today;
                    return (
                        <div key={c.iso} style={{ minHeight: 96, padding: '9px 10px', borderRight: '1px solid var(--om-line2)', borderBottom: '1px solid var(--om-line2)', background: isToday ? 'color-mix(in srgb, var(--om-accent-bg) 66%, transparent)' : 'var(--om-card)' }}>
                            <div className="flex items-center justify-between mb-2">
                                <span style={{ fontFamily: MONO, fontSize: 12, fontWeight: isToday ? 700 : 500, color: isToday ? 'var(--om-accent)' : 'var(--om-ink)' }}>{c.d}</span>
                                {c.orders.length > 0 && <span style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-muted)', background: 'var(--om-chip)', borderRadius: 20, padding: '1px 6px' }}>{c.orders.length}</span>}
                            </div>
                            <div className="flex flex-col gap-1">
                                {c.orders.slice(0, 4).map((o) => (
                                    <div key={o.id} style={{ height: 4, borderRadius: 3, background: statusOf(o.status).solid, width: Math.min(100, 40 + (o.planned_qty || 0) / 6) + '%' }} />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
