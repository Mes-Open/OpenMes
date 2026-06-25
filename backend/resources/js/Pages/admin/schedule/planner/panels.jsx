// Toolbar + Backlog rail, following the OpenMES Schedule design.
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { __ } from '../../../../lib/i18n';
import { OrderCard } from './OrderCard';
import { DraggableOrder } from './dnd';
import { priorityMeta, MONO } from './helpers';

const LEGEND = [
    ['Running', 'var(--om-running)'], ['Accepted', 'var(--om-accepted)'],
    ['Blocked', 'var(--om-blocked)'], ['Paused', 'var(--om-downtime)'], ['Maint', 'var(--om-maint)'],
];

function NavBtn({ children, onClick, title }) {
    return (
        <span onClick={onClick} title={title}
            style={{ width: 30, height: 30, borderRadius: 8, border: '1px solid var(--om-line)', background: 'var(--om-card)', color: 'var(--om-muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 15, cursor: 'pointer' }}>{children}</span>
    );
}

export function Toolbar({ ctx, view, setView, lineFilter, setLineFilter, live, onPrev, onNext, onToday, rangeLabel }) {
    const { data } = ctx;
    const tabs = [['weekly', __('Weekly')], ['daily', __('Daily')], ['hourly', __('Hourly')], ['monthly', __('Monthly')]];
    const seg = (active) => ({ fontSize: 12, fontWeight: 500, padding: '6px 12px', borderRadius: 6, cursor: 'pointer', ...(active ? { background: 'var(--om-ink)', color: 'var(--om-on-ink)' } : { color: 'var(--om-muted)' }) });

    return (
        <div className="flex items-center gap-3 flex-wrap mb-4">
            <div className="flex items-center gap-0.5">
                <NavBtn onClick={onPrev} title={__('Previous')}>‹</NavBtn>
                <span style={{ fontFamily: MONO, fontSize: 12.5, fontWeight: 500, color: 'var(--om-ink)', padding: '0 12px', minWidth: 168, textAlign: 'center' }}>{rangeLabel}</span>
                <NavBtn onClick={onNext} title={__('Next')}>›</NavBtn>
            </div>
            <span onClick={onToday} style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--om-accent)', background: 'var(--om-accent-bg)', borderRadius: 8, padding: '7px 13px', cursor: 'pointer' }}>{__('Today')}</span>
            <span style={{ width: 1, height: 22, background: 'var(--om-line2)' }} />
            <div className="flex gap-0.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 9, padding: 3 }}>
                {tabs.map(([k, label]) => <span key={k} onClick={() => setView(k)} style={seg(view === k)}>{label}</span>)}
            </div>
            <span style={{ width: 1, height: 22, background: 'var(--om-line2)' }} />
            <Dropdown
                className="min-w-[150px]"
                value={lineFilter == null ? '' : String(lineFilter)}
                onChange={(v) => setLineFilter(v)}
                options={[{ value: '', label: __('All lines') }, ...data.allLines.map((l) => ({ value: String(l.id), label: `${l.code} · ${l.name}` }))]}
            />
            <span style={{ width: 1, height: 22, background: 'var(--om-line2)' }} />
            <Link href="/admin/schedule/capacity" style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 500, color: 'var(--om-accent)', padding: '6px 8px' }}>{__('Capacity')} →</Link>
            <Link href={`/admin/schedule/employees?date=${data.range.startDate}`} style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 500, color: 'var(--om-muted)', padding: '6px 8px' }}>{__('Employees')}</Link>
            <div style={{ flex: 1 }} />
            <div className="flex items-center gap-3">
                {LEGEND.map(([label, clr]) => (
                    <span key={label} className="flex items-center gap-1.5" style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.04em', textTransform: 'uppercase', color: 'var(--om-muted)' }}>
                        <span style={{ width: 9, height: 9, borderRadius: 3, background: clr }} />{__(label)}
                    </span>
                ))}
                <span className="flex items-center gap-1.5 px-2.5 py-1.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 9 }}>
                    <span style={{ width: 8, height: 8, borderRadius: 999, background: live ? 'var(--om-running)' : 'var(--om-faint)', animation: live ? 'om-pulse 1.8s infinite' : 'none' }} />
                    <span style={{ fontFamily: MONO, fontSize: 10, letterSpacing: '0.06em', color: 'var(--om-muted)' }}>{live ? __('LIVE') : __('OFF')}</span>
                </span>
            </div>
        </div>
    );
}

// ── BACKLOG RAIL ─────────────────────────────────────────────────────────────
export function BacklogRail({ ctx }) {
    const { data } = ctx;
    const [q, setQ] = useState('');
    const [pf, setPf] = useState('all');

    let items = data.backlog.filter((o) =>
        (q === '' || o.order_no.toLowerCase().includes(q.toLowerCase()) || (o.product_name || '').toLowerCase().includes(q.toLowerCase()))
        && (pf === 'all' || priorityMeta(o.priority).label === pf));
    items = items.slice().sort((a, b) => b.priority - a.priority);

    const groups = {};
    items.forEach((o) => { const l = priorityMeta(o.priority).label; (groups[l] = groups[l] || []).push(o); });
    const order = ['Urgent', 'High', 'Medium', 'Low', 'Lowest'];
    const filters = [['all', 'All'], ['Urgent', 'Urgent'], ['High', 'High'], ['Medium', 'Med']];

    return (
        <div className="flex flex-col shrink-0" style={{ width: 340, maxHeight: 'calc(100vh - 120px)', borderLeft: '1px solid var(--om-line2)', background: 'var(--om-panel)' }}>
            <div style={{ padding: '16px 16px 12px', borderBottom: '1px solid var(--om-line2)' }}>
                <div className="flex items-center justify-between mb-3">
                    <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--om-ink)' }}>{__('Backlog')}</span>
                    <span style={{ fontFamily: MONO, fontSize: 11, color: 'var(--om-muted)', background: 'var(--om-chip)', borderRadius: 20, padding: '2px 9px' }}>{data.backlog.length}</span>
                </div>
                <div className="flex items-center gap-2 mb-2.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 8, padding: '8px 11px' }}>
                    <span style={{ width: 12, height: 12, borderRadius: 999, border: '2px solid var(--om-faint)', flexShrink: 0 }} />
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={__('Search backlog')}
                        className="flex-1 min-w-0 outline-none" style={{ border: 'none', background: 'transparent', fontSize: 12.5, color: 'var(--om-ink)' }} />
                </div>
                <div className="flex gap-1.5">
                    {filters.map(([k, label]) => (
                        <span key={k} onClick={() => setPf(k)} className="flex-1 text-center"
                            style={{ fontSize: 11, fontWeight: 500, padding: 6, borderRadius: 7, cursor: 'pointer', ...(pf === k ? { background: 'var(--om-ink)', color: 'var(--om-on-ink)' } : { background: 'var(--om-card)', color: 'var(--om-muted)', border: '1px solid var(--om-line)' }) }}>{__(label)}</span>
                    ))}
                </div>
            </div>
            <div className="om-bl flex-1 overflow-y-auto" style={{ padding: '12px 14px', minHeight: 0 }}>
                {items.length === 0 && <div className="text-center" style={{ padding: '36px 16px', color: 'var(--om-faint)', fontSize: 12.5 }}>{__('Backlog clear — all orders scheduled.')}</div>}
                {order.filter((g) => groups[g]).map((g) => (
                    <div key={g}>
                        <div className="flex items-center gap-2" style={{ margin: '6px 2px 9px' }}>
                            <span style={{ width: 7, height: 7, borderRadius: 2, background: priorityMeta(g === 'Urgent' ? 5 : g === 'High' ? 4 : g === 'Medium' ? 3 : g === 'Low' ? 2 : 1).color }} />
                            <span style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-muted)' }}>{__(g)}</span>
                            <span style={{ fontFamily: MONO, fontSize: 9.5, color: 'var(--om-faint)' }}>{groups[g].length}</span>
                            <div style={{ flex: 1, height: 1, background: 'var(--om-line2)' }} />
                        </div>
                        <div className="flex flex-col gap-2 mb-2">
                            {groups[g].map((wo) => (
                                <DraggableOrder key={wo.id} wo={wo}>
                                    <OrderCard wo={wo} variant="backlog" selected={ctx.selectedId === wo.id}
                                        onClick={(e) => { e.stopPropagation(); ctx.onSelectOrder(wo); }} />
                                </DraggableOrder>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            <div className="flex gap-1.5" style={{ padding: '10px 14px', borderTop: '1px solid var(--om-line2)' }}>
                <Link href="/admin/work-orders/create" className="flex-1 text-center" style={{ padding: '8px', borderRadius: 8, fontSize: 12, fontWeight: 600, background: 'var(--om-ink)', color: 'var(--om-on-ink)' }}>{__('+ New order')}</Link>
                <Link href="/admin/csv-import" className="flex-1 text-center" style={{ padding: '8px', borderRadius: 8, fontSize: 12, fontWeight: 500, background: 'var(--om-card)', color: 'var(--om-muted)', border: '1px solid var(--om-line)' }}>{__('Import CSV')}</Link>
            </div>
        </div>
    );
}
