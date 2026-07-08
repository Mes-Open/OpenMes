// Edit panel, assign popup, new-order modal, conflict dialog, live tracking,
// toast — styled to the OpenMES Schedule design.
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Dropdown, DatePicker } from '@openmes/ui';
import { __ } from '../../../../lib/i18n';
import WorkOrderForm from '../../work-orders/WorkOrderForm';
import { statusOf, statusLabel, priorityMeta, fmtQty, MONO } from './helpers';
import { StatusPill } from './OrderCard';

const lblStyle = { fontFamily: MONO, fontSize: 8.5, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-faint)', marginBottom: 5 };

function Backdrop({ children, onClose }) {
    return (
        <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 50, background: 'rgba(10,9,8,.42)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 40 }}>
            <div onClick={(e) => e.stopPropagation()}>{children}</div>
        </div>
    );
}

// Bottom-centre edit panel (with inline rescheduling).
export function OrderEditSheet({ wo, ctx, onClose, onSave, onUnassign }) {
    const { data, config } = ctx;
    const s = statusOf(wo.status);
    const [line, setLine] = useState(wo.line_id || '');
    const [extras, setExtras] = useState((wo.placements || []).map((p) => ({ ...p })));
    const [due, setDue] = useState(wo.due_date || '');
    const [endDate, setEndDate] = useState(wo.end_date || '');
    const [shift, setShift] = useState(wo.shift_number || '');
    const [endShift, setEndShift] = useState(wo.end_shift_number || '');
    // shift_number is a 1-based slot index (matching the weekly grid), not sort_order.
    const shiftsPerDay = config?.shiftsPerDay ?? data.shifts.length;
    const shiftOpts = [{ value: '', label: '—' }, ...Array.from({ length: shiftsPerDay }, (_, i) => ({ value: String(i + 1), label: data.shifts[i]?.name ?? ('S' + (i + 1)) }))];

    return (
        <>
            <div onClick={onClose} style={{ position: 'fixed', inset: 0, zIndex: 40 }} />
            <div style={{ position: 'fixed', bottom: 24, left: '50%', transform: 'translateX(-50%)', zIndex: 45, width: 560, maxWidth: '92vw', background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 14, boxShadow: '0 30px 70px -22px rgba(0,0,0,.5)', overflow: 'hidden' }}>
                <div style={{ height: 3, background: s.solid }} />
                <div style={{ padding: '18px 20px' }}>
                    <div className="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <div className="flex items-center gap-2.5 mb-1.5">
                                <span style={{ fontFamily: MONO, fontSize: 13, fontWeight: 600, color: 'var(--om-ink)' }}>{wo.order_no}</span>
                                <StatusPill status={wo.status} />
                                {wo.is_overdue && <span style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-blocked)', background: 'var(--om-blocked-bg)', borderRadius: 20, padding: '2px 7px' }}>⚠ {__('Overdue')}</span>}
                            </div>
                            <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--om-ink)' }}>{wo.product_name} · {fmtQty(wo.planned_qty)} {__('pcs')}</div>
                        </div>
                        <span onClick={onClose} style={{ color: 'var(--om-faint)', fontSize: 19, cursor: 'pointer', lineHeight: 1 }}>×</span>
                    </div>

                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <div style={lblStyle}>{__('Production line')}</div>
                            <Dropdown value={line == null ? '' : String(line)} placeholder={__('Unassigned')}
                                onChange={(v) => setLine(v)}
                                options={[{ value: '', label: __('Unassigned') }, ...data.allLines.map((l) => ({ value: String(l.id), label: `${l.code} · ${l.name}` }))]} />
                        </div>
                        <div>
                            <div style={lblStyle}>{__('Also runs on')}</div>
                            <Dropdown value="" onChange={(v) => {
                                if (!v) return;
                                setExtras((xs) => [...xs, { id: null, line_id: +v, due_date: due || wo.due_date || data.range.startDate, shift_number: shift ? +shift : 1, end_date: null, end_shift_number: null }]);
                            }} placeholder={__('+ Add line')} disabled={!line}
                                options={[{ value: '', label: __('+ Add line') }, ...data.allLines.map((l) => ({ value: String(l.id), label: `${l.code} · ${l.name}` }))]} />
                            {extras.length > 0 && (
                                <div className="flex flex-col gap-1" style={{ marginTop: 6 }}>
                                    {extras.map((p, i) => {
                                        const l = data.allLines.find((x) => x.id === p.line_id);
                                        return (
                                            <div key={p.id ?? 'new' + i} className="flex items-center gap-2" style={{ fontFamily: MONO, fontSize: 10.5, color: 'var(--om-muted)', background: 'var(--om-chip)', borderRadius: 6, padding: '4px 8px' }}>
                                                <span style={{ fontWeight: 600, color: 'var(--om-ink)' }}>{l?.code ?? '?'}</span>
                                                <span>{p.due_date}{p.end_date ? ` → ${p.end_date}` : ''}</span>
                                                <button type="button" onClick={() => setExtras((xs) => xs.filter((_, j) => j !== i))} className="ml-auto"
                                                    style={{ color: 'var(--om-blocked)', fontWeight: 700 }}>✕</button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                        <div>
                            <div style={{ ...lblStyle, display: 'flex', justifyContent: 'space-between' }}>
                                {__('Due date')}
                                {due && <button type="button" onClick={() => setDue('')} style={{ color: 'var(--om-accent)', textTransform: 'none', letterSpacing: 0 }}>{__('Clear')}</button>}
                            </div>
                            <DatePicker value={due || null} onChange={(iso) => setDue(iso ?? '')} className="w-full" />
                        </div>
                        <div>
                            <div style={{ ...lblStyle, display: 'flex', justifyContent: 'space-between' }}>
                                {__('End date')}
                                {endDate && <button type="button" onClick={() => setEndDate('')} style={{ color: 'var(--om-accent)', textTransform: 'none', letterSpacing: 0 }}>{__('Clear')}</button>}
                            </div>
                            <DatePicker value={endDate || null} min={due || undefined} onChange={(iso) => setEndDate(iso ?? '')} className="w-full" />
                        </div>
                        <div><div style={lblStyle}>{__('Start shift')}</div><Dropdown value={shift == null ? '' : String(shift)} onChange={(v) => setShift(v)} placeholder="—" options={shiftOpts} /></div>
                        <div><div style={lblStyle}>{__('End shift')}</div><Dropdown value={endShift == null ? '' : String(endShift)} onChange={(v) => setEndShift(v)} placeholder="—" options={shiftOpts} /></div>
                    </div>

                    <div className="flex gap-2.5">
                        <a href={`/admin/work-orders/${wo.id}`} className="flex-1 text-center" style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--om-on-ink)', background: 'var(--om-ink)', borderRadius: 9, padding: 11 }}>{__('Open work order')} ↗</a>
                        <button onClick={() => onSave(wo, {
                            line_id: line ? +line : null,
                            due_date: due || null, week_number: wo.week_number ?? null, end_date: endDate || null,
                            shift_number: shift ? +shift : null, end_shift_number: endShift ? +endShift : null,
                            extra_placements: extras.map((p) => ({
                                id: p.id ?? null, line_id: p.line_id, due_date: p.due_date,
                                shift_number: p.shift_number ?? null, end_date: p.end_date ?? null, end_shift_number: p.end_shift_number ?? null,
                            })),
                        })}
                            style={{ fontSize: 13.5, fontWeight: 600, color: '#fff', background: 'var(--om-accent)', borderRadius: 9, padding: '11px 18px' }}>{__('Save')}</button>
                        <button onClick={() => onUnassign(wo)} style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--om-blocked)', background: 'var(--om-blocked-bg)', borderRadius: 9, padding: '11px 18px' }}>{__('Unschedule')}</button>
                    </div>
                </div>
            </div>
        </>
    );
}

// Assign popup — pick a backlog order for an empty cell.
export function AssignPopup({ target, ctx, onClose, onPick }) {
    const { data } = ctx;
    const [q, setQ] = useState('');
    const line = data.allLines.find((l) => l.id === target.lineId);
    const items = data.backlog.filter((o) => q === '' || o.order_no.toLowerCase().includes(q.toLowerCase()) || (o.product_name || '').toLowerCase().includes(q.toLowerCase()));
    return (
        <Backdrop onClose={onClose}>
            <div style={{ width: 440, maxWidth: '92vw', maxHeight: 560, display: 'flex', flexDirection: 'column', background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 14, boxShadow: '0 34px 80px -22px rgba(0,0,0,.5)', overflow: 'hidden' }}>
                <div style={{ padding: '18px 20px', borderBottom: '1px solid var(--om-line2)' }}>
                    <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--om-ink)' }}>{__('Assign to')} {line?.code} · {target.date}</div>
                    <div style={{ fontFamily: MONO, fontSize: 10.5, color: 'var(--om-faint)', marginTop: 3 }}>{__('Pick a backlog order for this slot')}</div>
                </div>
                <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--om-line2)' }}>
                    <div className="flex items-center gap-2" style={{ background: 'var(--om-bg)', border: '1px solid var(--om-line)', borderRadius: 8, padding: '8px 11px' }}>
                        <span style={{ width: 12, height: 12, borderRadius: 999, border: '2px solid var(--om-faint)' }} />
                        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={__('Search order or product')} autoFocus
                            className="flex-1 min-w-0 outline-none" style={{ border: 'none', background: 'transparent', fontSize: 12.5, color: 'var(--om-ink)' }} />
                    </div>
                </div>
                <div className="om-bl flex-1 overflow-y-auto" style={{ padding: '12px 20px' }}>
                    {items.map((wo) => (
                        <div key={wo.id} onClick={() => onPick(wo, target)} className="flex items-center gap-3"
                            style={{ padding: '11px 12px', border: '1px solid var(--om-line)', borderRadius: 9, marginBottom: 8, cursor: 'pointer' }}>
                            <div className="flex-1 min-w-0">
                                <div style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 600, color: 'var(--om-ink)', marginBottom: 3 }}>{wo.order_no}</div>
                                <div style={{ fontFamily: MONO, fontSize: 9.5, color: 'var(--om-faint)' }}>{wo.product_name} · {fmtQty(wo.planned_qty)} {__('pcs')}</div>
                            </div>
                            <StatusPill status={wo.status} />
                        </div>
                    ))}
                    {items.length === 0 && <div className="text-center" style={{ padding: 30, color: 'var(--om-faint)', fontSize: 12.5 }}>{__('No matching orders.')}</div>}
                </div>
            </div>
        </Backdrop>
    );
}

// "+ New order" without leaving the planner — renders THE work-order create
// form (the same component the create page uses), posting with `stay` so the
// server sends us back here and the fresh order lands in the backlog.
export function NewOrderModal({ ctx, onClose }) {
    const { productTypes = [], customFields = [] } = usePage().props;
    return (
        <Backdrop onClose={onClose}>
            <div style={{ width: 720, maxWidth: '94vw', maxHeight: '86vh', overflowY: 'auto', background: 'var(--om-bg)', border: '1px solid var(--om-line)', borderRadius: 14, boxShadow: '0 34px 80px -22px rgba(0,0,0,.5)', padding: '20px 22px' }}>
                <div className="flex items-center justify-between mb-4">
                    <span style={{ fontSize: 16, fontWeight: 600, color: 'var(--om-ink)' }}>{__('New work order')}</span>
                    <span onClick={onClose} style={{ color: 'var(--om-faint)', fontSize: 19, cursor: 'pointer', lineHeight: 1 }}>×</span>
                </div>
                <WorkOrderForm
                    lines={ctx.data.allLines}
                    productTypes={productTypes}
                    customFields={customFields}
                    stay
                    onCancel={onClose}
                    onSuccess={onClose}
                />
            </div>
        </Backdrop>
    );
}

export function ConflictDialog({ onCancel, onConfirm }) {
    return (
        <Backdrop onClose={onCancel}>
            <div style={{ width: 400, maxWidth: '92vw', background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 14, boxShadow: '0 34px 80px -22px rgba(0,0,0,.5)', overflow: 'hidden' }}>
                <div style={{ height: 3, background: 'var(--om-blocked)' }} />
                <div style={{ padding: '20px 22px' }}>
                    <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--om-ink)', marginBottom: 6 }}>{__('Scheduling conflict')}</div>
                    <div style={{ fontSize: 13, color: 'var(--om-muted)', lineHeight: 1.5 }}>{__('This slot overlaps another active order on the same line. Schedule it anyway, or pick a different slot.')}</div>
                    <div className="flex gap-2.5 mt-4">
                        <button onClick={onCancel} className="flex-1" style={{ fontSize: 13, fontWeight: 600, color: 'var(--om-muted)', background: 'var(--om-chip)', borderRadius: 9, padding: 11 }}>{__('Cancel')}</button>
                        <button onClick={onConfirm} className="flex-1" style={{ fontSize: 13, fontWeight: 600, color: '#fff', background: 'var(--om-blocked)', borderRadius: 9, padding: 11 }}>{__('Schedule anyway')}</button>
                    </div>
                </div>
            </div>
        </Backdrop>
    );
}

// Always-on live-tracking strip for the planner header. `live` is the
// check-updates payload; `wo` the order being followed (first in-progress).
export function LiveTrackingBar({ wo, live }) {
    const tracked = live || wo;
    if (!tracked) {
        return (
            <div className="flex items-center gap-2.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 10, padding: '10px 14px' }}>
                <span style={{ width: 8, height: 8, borderRadius: 999, background: 'var(--om-faint)' }} />
                <span style={{ fontFamily: MONO, fontSize: 10, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{__('No production running')}</span>
            </div>
        );
    }
    const pct = tracked.progress_percent ?? 0;
    const s = statusOf(tracked.status);
    return (
        <div className="flex items-center gap-3.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 10, padding: '8px 14px', minWidth: 320 }}>
            <div className="flex items-center gap-2">
                <span style={{ width: 8, height: 8, borderRadius: 999, background: 'var(--om-running)', animation: 'om-pulse 1.8s infinite' }} />
                <span style={{ fontFamily: MONO, fontSize: 9, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-running)' }}>{__('Live tracking')}</span>
            </div>
            <div style={{ lineHeight: 1.25, minWidth: 0 }}>
                <div style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 600, color: 'var(--om-ink)' }}>{tracked.order_no}</div>
                <div className="truncate" style={{ fontSize: 11, color: 'var(--om-muted)', maxWidth: 180 }}>
                    {tracked.product || tracked.product_name}{tracked.current_step ? ' · ' + tracked.current_step.name : ''}
                </div>
            </div>
            <div className="flex items-center gap-2" style={{ marginLeft: 'auto' }}>
                <div className="rounded-full overflow-hidden" style={{ width: 90, height: 6, background: 'var(--om-line2)' }}>
                    <div style={{ width: Math.min(100, pct) + '%', height: '100%', background: s.solid }} />
                </div>
                <span style={{ fontFamily: MONO, fontSize: 14, fontWeight: 600, color: s.solid }}>{Math.round(pct)}%</span>
            </div>
        </div>
    );
}

export function Toasts({ toasts }) {
    return (
        <div style={{ position: 'fixed', bottom: 24, right: 24, zIndex: 60, display: 'flex', flexDirection: 'column', gap: 8, alignItems: 'flex-end' }}>
            {toasts.map((t) => {
                const clr = t.kind === 'error' ? 'var(--om-blocked)' : t.kind === 'warning' ? 'var(--om-downtime)' : 'var(--om-running)';
                return (
                    <div key={t.id} className="flex items-center gap-3" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderLeft: `3px solid ${clr}`, borderRadius: 11, padding: '13px 16px', boxShadow: '0 18px 44px -18px rgba(0,0,0,.4)' }}>
                        <span style={{ width: 9, height: 9, borderRadius: 999, background: clr }} />
                        <span style={{ fontSize: 13, color: 'var(--om-ink)' }}>{t.msg}</span>
                    </div>
                );
            })}
        </div>
    );
}

export function SavingOverlay() {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 58, display: 'flex', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' }}>
            <div className="flex items-center gap-2.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 11, padding: '13px 18px', boxShadow: '0 18px 44px -18px rgba(0,0,0,.4)' }}>
                <span className="w-4 h-4 rounded-full border-2 animate-spin" style={{ borderColor: 'var(--om-accent)', borderTopColor: 'transparent' }} />
                <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--om-ink)' }}>{__('Saving…')}</span>
            </div>
        </div>
    );
}
