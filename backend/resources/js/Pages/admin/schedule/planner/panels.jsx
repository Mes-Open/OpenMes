// Toolbar + Backlog rail (with the Changes/undo tab), following the OpenMES
// Schedule design.
import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button, Dropdown, Icon, SegmentedControl } from '@openmes/ui';
import CustomerTierBadge from '../../../../components/CustomerTierBadge';
import Tooltip from '../../../../components/Tooltip';
import { __, formatDate } from '../../../../lib/i18n';
import { apiGet, apiCall } from '../../../../lib/http';
import { OrderCard } from './OrderCard';
import { DraggableOrder } from './dnd';
import { NewOrderModal } from './modals';
import { TIER_VALUES } from '../../customers/fields';
import { priorityMeta, MONO } from './helpers';

const LEGEND = [
    ['Running', 'var(--om-running)'], ['Accepted', 'var(--om-accepted)'],
    ['Blocked', 'var(--om-blocked)'], ['Paused', 'var(--om-downtime)'], ['Maint', 'var(--om-maint)'],
];

function NavBtn({ children, onClick, title }) {
    return (
        <Tooltip label={title}>
            <Button variant="outline" size="sm" onClick={onClick} aria-label={title} className="p-2">
                {children}
            </Button>
        </Tooltip>
    );
}

export function Toolbar({ ctx, view, setView, lineFilter, setLineFilter, live, onPrev, onNext, onToday, rangeLabel, onMaintenance }) {
    const { data } = ctx;
    // Employee scheduling lives under the (core) Schedule area but is gated by
    // the HR module — hide the shortcut when HR is off, mirroring the nav.
    const accessibleTabs = usePage().props?.auth?.user?.accessibleTabs ?? [];
    const hrEnabled = accessibleTabs.includes('hr');
    const tabs = [['weekly', __('Weekly')], ['daily', __('Daily')], ['hourly', __('Hourly')], ['monthly', __('Monthly')]];


    return (
        <div className="flex items-center gap-3 flex-wrap mb-4">
            <div className="flex items-center gap-0.5">
                <NavBtn onClick={onPrev} title={__('Previous')}><Icon name="chevron-left" size={16} /></NavBtn>
                <span style={{ fontFamily: MONO, fontSize: 12.5, fontWeight: 500, color: 'var(--om-ink)', padding: '0 12px', minWidth: 168, textAlign: 'center' }}>{rangeLabel}</span>
                <NavBtn onClick={onNext} title={__('Next')}><Icon name="chevron-right" size={16} /></NavBtn>
            </div>
            <Button variant="outline" size="sm" onClick={onToday} leftIcon={<Icon name="calendar-days" size={14} />}>{__('Today')}</Button>
            <SegmentedControl label={__('Production Planner')} className="w-full sm:w-[350px]" value={view} onChange={setView}
                options={tabs.map(([value, label]) => ({ value, label }))} />
            <Dropdown
                className="min-w-[150px]"
                value={lineFilter == null ? '' : String(lineFilter)}
                onChange={(v) => setLineFilter(v)}
                options={[{ value: '', label: __('All lines') }, ...data.allLines.map((l) => ({ value: String(l.id), label: `${l.code} · ${l.name}` }))]}
            />
            <Link href="/admin/schedule/capacity" className="inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold hover:bg-om-chip">
                <Icon name="chart-no-axes-combined" size={14} />{__('Capacity')}
            </Link>
            {hrEnabled && (
                <Link href={`/admin/schedule/employees?date=${data.range.startDate}`} className="inline-flex items-center gap-2 rounded-om-sm border border-om-line px-3 py-2 text-xs font-semibold hover:bg-om-chip">
                    <Icon name="users" size={14} />{__('Employees')}
                </Link>
            )}
            <div className="flex w-full flex-wrap items-center gap-3 pt-1">
                {LEGEND.map(([label, clr]) => (
                    <span key={label} className="flex items-center gap-1.5" style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.04em', textTransform: 'uppercase', color: 'var(--om-muted)' }}>
                        <span style={{ width: 9, height: 9, borderRadius: 3, background: clr }} />{__(label)}
                    </span>
                ))}
                <span className="flex items-center gap-1.5 px-2.5 py-1.5" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 9 }}>
                    <span style={{ width: 8, height: 8, borderRadius: 999, background: live ? 'var(--om-running)' : 'var(--om-faint)', animation: live ? 'om-pulse 1.8s infinite' : 'none' }} />
                    <span style={{ fontFamily: MONO, fontSize: 10, letterSpacing: '0.06em', color: 'var(--om-muted)' }}>{live ? __('LIVE') : __('OFF')}</span>
                </span>
                <Button variant="outline" size="sm" className="ml-auto" leftIcon={<Icon name="wrench" size={14} />} onClick={onMaintenance}>{__('Maintenance')}</Button>
            </div>
        </div>
    );
}

// ── CHANGES TAB ──────────────────────────────────────────────────────────────
// Human summary of one logged edit, from its before/after snapshots.
function changeSummary(c, allLines) {
    const code = (id) => allLines.find((l) => l.id === id)?.code ?? '?';
    const slot = (s) => (s.line_id ? `${code(s.line_id)} ${s.due_date ?? ''}${s.shift_number ? ' S' + s.shift_number : ''}`.trim() : __('Backlog'));
    const b = c.before || {}; const a = c.after || {};
    const parts = [];
    const primaryChanged = ['line_id', 'due_date', 'shift_number', 'end_date', 'end_shift_number'].some((k) => (b[k] ?? null) !== (a[k] ?? null));
    if (primaryChanged) parts.push(`${slot(b)} → ${slot(a)}`);
    const key = (p) => `${p.line_id}|${p.due_date}|${p.shift_number ?? ''}|${p.end_date ?? ''}|${p.end_shift_number ?? ''}`;
    const bp = (b.placements || []).map(key); const ap = (a.placements || []).map(key);
    (a.placements || []).forEach((p) => { if (!bp.includes(key(p))) parts.push(`+ ${code(p.line_id)} ${p.due_date}`); });
    (b.placements || []).forEach((p) => { if (!ap.includes(key(p))) parts.push(`− ${code(p.line_id)} ${p.due_date}`); });
    if (!parts.length && ((b.planned_start_at ?? '') !== (a.planned_start_at ?? '') || (b.planned_end_at ?? '') !== (a.planned_end_at ?? ''))) {
        const t = (x) => (x ? x.slice(11, 16) : '—');
        parts.push(`${t(b.planned_start_at)}–${t(b.planned_end_at)} → ${t(a.planned_start_at)}–${t(a.planned_end_at)}`);
    }
    return parts.join(' · ') || __('updated');
}

function ChangesPanel({ ctx }) {
    const [items, setItems] = useState(null);
    const [busy, setBusy] = useState(null);

    const load = async () => {
        try {
            const r = await apiGet('/admin/schedule/changes');
            const d = await r.json();
            setItems(d.changes || []);
        } catch { setItems([]); }
    };
    // Reload whenever the board data refreshes (covers our own edits + live sync).
    useEffect(() => { load(); }, [ctx.data.workOrders]); // eslint-disable-line react-hooks/exhaustive-deps

    const undo = async (c) => {
        setBusy(c.id);
        try {
            const r = await apiCall(`/admin/schedule/changes/${c.id}/undo`, 'POST', {});
            if (r.ok) { ctx.onRefreshContent?.(); await load(); }
        } catch { /* surfaced by reload */ } finally { setBusy(null); }
    };

    if (items === null) return <div className="text-center" style={{ padding: 30, color: 'var(--om-faint)', fontSize: 12.5 }}>…</div>;
    if (items.length === 0) return <div className="text-center" style={{ padding: '12px 16px', color: 'var(--om-faint)', fontSize: 12.5 }}>{__('No schedule changes yet.')}</div>;

    return (
        <div className="flex flex-col gap-2">
            {items.map((c) => {
                const undone = !!c.undone_at;
                return (
                    <div key={c.id} style={{ border: '1px solid var(--om-line)', borderRadius: 9, padding: '9px 11px', background: 'var(--om-card)', opacity: undone ? 0.55 : 1 }}>
                        <div className="flex items-center gap-2">
                            {c.action === 'undo' && (
                                <Tooltip label={__('Undo')}>
                                    <span style={{ fontSize: 10, color: 'var(--om-accent)' }}>↩</span>
                                </Tooltip>
                            )}
                            <span style={{ fontFamily: MONO, fontSize: 11, fontWeight: 600, color: 'var(--om-ink)' }}>{c.order_no}</span>
                            <span className="ml-auto" style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-faint)' }}>
                                {formatDate(new Date(c.created_at), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </div>
                        <div style={{ fontFamily: MONO, fontSize: 10, color: 'var(--om-muted)', marginTop: 3 }}>{changeSummary(c, ctx.data.allLines)}</div>
                        <div className="flex items-center gap-2" style={{ marginTop: 6 }}>
                            {c.user && <span style={{ fontSize: 10, color: 'var(--om-faint)' }}>{c.user}</span>}
                            <span className="ml-auto">
                                {undone
                                    ? <span style={{ fontFamily: MONO, fontSize: 9, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{__('Undone')}</span>
                                    : (
                                        <button type="button" onClick={() => undo(c)} disabled={busy === c.id}
                                            style={{ fontSize: 11, fontWeight: 600, color: 'var(--om-accent)', background: 'var(--om-accent-bg)', borderRadius: 7, padding: '4px 10px', opacity: busy === c.id ? 0.5 : 1 }}>
                                            {__('Undo')}
                                        </button>
                                    )}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ── BACKLOG RAIL ─────────────────────────────────────────────────────────────
export function BacklogRail({ ctx }) {
    const { data } = ctx;
    // The importer is behind the `import` tab, so the shortcut has to be too —
    // otherwise it renders for everyone and 403s on click. Mirrors hrEnabled
    // in the header above.
    const canImport = (usePage().props?.auth?.user?.accessibleTabs ?? []).includes('import');
    const [q, setQ] = useState('');
    const [pf, setPf] = useState('all');
    const [tab, setTab] = useState('backlog');
    const [showNew, setShowNew] = useState(false);
    const [tierFilter, setTierFilter] = useState('');

    let items = data.backlog.filter((o) =>
        (q === '' || `${o.order_no} ${o.product_name || ''} ${o.customer_name || ''}`.toLowerCase().includes(q.toLowerCase()))
        && (pf === 'all' || priorityMeta(o.priority).label === pf)
        && (!tierFilter || o.customer_tier === tierFilter));
    items = items.slice().sort((a, b) => b.priority - a.priority);

    const groups = {};
    items.forEach((o) => { const l = priorityMeta(o.priority).label; (groups[l] = groups[l] || []).push(o); });
    const order = ['Urgent', 'High', 'Medium', 'Low', 'Lowest'];
    const filters = [['all', 'All'], ['Urgent', 'Urgent'], ['High', 'High'], ['Medium', 'Med']];

    return (
        <div className="w-full min-w-0 border-y border-om-line bg-om-panel mb-4">
            <div className="flex flex-wrap items-center gap-3 px-3 py-3 border-b border-om-line">
                <SegmentedControl label={__('Backlog')} className="w-full sm:w-[280px] shrink-0" value={tab} onChange={setTab}
                    options={[{ value: 'backlog', label: `${__('Backlog')} · ${data.backlog.length}` }, { value: 'changes', label: __('Changes') }]} />
            <div className="flex gap-2 sm:ml-auto">
                <Button size="sm" onClick={() => setShowNew(true)} className="whitespace-nowrap" leftIcon={<Icon name="plus" size={14} />}>{__('New order')}</Button>
                {canImport && (
                    <Link href="/admin/import/work-orders" className="whitespace-nowrap inline-flex items-center justify-center gap-2 hover:bg-om-chip" style={{ padding: '8px', borderRadius: 8, fontSize: 12, fontWeight: 500, background: 'var(--om-card)', color: 'var(--om-muted)', border: '1px solid var(--om-line)' }}><Icon name="upload" size={14} />{__('Import CSV')}</Link>
                )}
            </div>
                {tab === 'backlog' && (
                    <div className="flex w-full flex-wrap items-center gap-3">
                        <div className="flex items-center gap-2 w-full sm:w-[240px]" style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 8, padding: '8px 11px' }}>
                            <Icon name="search" size={15} className="shrink-0 text-om-faint" />
                            <input aria-label={__('Search backlog')} value={q} onChange={(e) => setQ(e.target.value)} placeholder={__('Search backlog')}
                                className="flex-1 min-w-0 outline-none" style={{ border: 'none', background: 'transparent', fontSize: 12.5, color: 'var(--om-ink)' }} />
                        </div>
                        <div className="flex gap-1.5 min-w-[250px]">
                            {filters.map(([k, label]) => (
                                <button type="button" aria-pressed={pf === k} key={k} onClick={() => setPf(k)} className="flex-1 text-center"
                                    style={{ fontSize: 11, fontWeight: 500, padding: 6, borderRadius: 7, cursor: 'pointer', ...(pf === k ? { background: 'var(--om-ink)', color: 'var(--om-on-ink)' } : { background: 'var(--om-card)', color: 'var(--om-muted)', border: '1px solid var(--om-line)' }) }}>{__(label)}</button>
                            ))}
                        </div>
                        {/* customer tier filter (ported from develop) */}
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => setTierFilter('')} aria-pressed={tierFilter === ''}
                                className={`px-2.5 py-1 text-xs font-medium rounded-full border border-om-line transition ${tierFilter === '' ? 'bg-om-ink text-om-on-ink' : 'bg-om-chip text-om-muted hover:bg-om-line2'}`}>{__('All tiers')}</button>
                            {TIER_VALUES.map((t) => (
                                <button key={t} type="button" aria-pressed={tierFilter === t} onClick={() => setTierFilter(tierFilter === t ? '' : t)}
                                    className={`rounded-full transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-om-ink ${tierFilter === t ? 'ring-2 ring-om-ink ring-offset-2 ring-offset-om-panel' : ''}`}>
                                    <CustomerTierBadge tier={t} />
                                </button>
                            ))}
                        </div>
                    </div>
                )}

            </div>
            {tab === 'changes' && (
                <div className="om-bl max-h-[240px] overflow-auto p-3">
                    <ChangesPanel ctx={ctx} />
                </div>
            )}
            {tab === 'backlog' && (
            <div className="om-bl flex items-start gap-5 overflow-x-auto p-3">
                {items.length === 0 && <div className="text-center" style={{ padding: '12px 16px', color: 'var(--om-faint)', fontSize: 12.5 }}>{__('Backlog clear — all orders scheduled.')}</div>}
                {order.filter((g) => groups[g]).map((g) => (
                    <div key={g} className="shrink-0">
                        <div className="flex items-center gap-2" style={{ margin: '6px 2px 9px' }}>
                            <span style={{ width: 7, height: 7, borderRadius: 2, background: priorityMeta(g === 'Urgent' ? 5 : g === 'High' ? 4 : g === 'Medium' ? 3 : g === 'Low' ? 2 : 1).color }} />
                            <span style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-muted)' }}>{__(g)}</span>
                            <span style={{ fontFamily: MONO, fontSize: 9.5, color: 'var(--om-faint)' }}>{groups[g].length}</span>
                            <div style={{ flex: 1, height: 1, background: 'var(--om-line2)' }} />
                        </div>
                        <div className="flex items-start gap-3">
                            {groups[g].map((wo) => (
                                <div key={wo.id} className="w-[250px] shrink-0"><DraggableOrder wo={wo}>
                                    <OrderCard wo={wo} variant="backlog" selected={ctx.selectedId === wo.id}
                                        onClick={(e) => { e.stopPropagation(); ctx.onSelectOrder(wo); }} />
                                </DraggableOrder></div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            )}
            {showNew && <NewOrderModal ctx={ctx} onClose={() => setShowNew(false)} />}
        </div>
    );
}
