// Production Planner staffing badges + the Planning insights drawer.
// The numbers and every sentence come from the server (PlanningInsights), so
// the board and the drawer always tell the same story; this file only lays
// them out and sends the planner's choice back.
import { useEffect, useRef } from 'react';
import { Icon } from '@openmes/ui';
import { __, formatTime } from '../../../../lib/i18n';
import { TONE_STYLE, staffingTone } from '../../../../lib/staffing';
import { FILTERS, filterCounts, groupInsights } from './insightsState';
import { MONO } from './helpers';

/** Tooltip text of a badge: the count, who is away, who is held elsewhere. */
export function badgeTitle(cell) {
    if (!cell) return '';
    if (!cell.tracked) return __('Staffing not tracked — scheduled on machine capacity only');
    const lines = [__(':available available, :needed needed', { available: cell.available, needed: cell.needed })];
    if (cell.absent?.length) lines.push(`${__('Absent')}: ${cell.absent.join(', ')}`);
    (cell.held_elsewhere ?? []).forEach((h) => lines.push(__(':names booked on :line', { names: h.names.join(', '), line: h.line_name })));
    return lines.join('\n');
}

/**
 * "present / needed" pill for one line × shift cell. A red or amber pill is a
 * button when `onOpen` is given: it opens the drawer on that cell's insight.
 */
export function StaffBadge({ cell, style, onOpen = null }) {
    if (!cell) return null;
    const tone = staffingTone(cell);
    const palette = tone === 'idle' ? TONE_STYLE.spare : TONE_STYLE[tone];
    const content = cell.tracked ? (
        <>
            <Icon name="user" size={10} />
            {cell.available}/{cell.needed}
        </>
    ) : __('not tracked');
    const look = { ...palette, fontFamily: MONO, fontSize: 9.5, fontWeight: 700, borderRadius: 999, padding: '1px 6px', lineHeight: '14px', ...style };
    if (onOpen && (tone === 'short' || tone === 'enough')) {
        const stop = (e) => e.stopPropagation();
        return (
            <button type="button" data-testid="staff-badge" data-tone={tone} title={`${badgeTitle(cell)}\n${__('Click for the explanation')}`}
                aria-label={`${badgeTitle(cell).replace(/\n/g, '. ')}. ${__('Open the explanation in planning insights')}`}
                onPointerDown={stop} onMouseDown={stop}
                onClick={(e) => { e.stopPropagation(); onOpen(); }}
                className="inline-flex items-center gap-1 whitespace-nowrap border-0 hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1"
                style={{ ...look, cursor: 'pointer', boxShadow: 'inset 0 0 0 1px currentColor' }}>
                {content}
            </button>
        );
    }
    return (
        <span data-testid="staff-badge" data-tone={tone} title={badgeTitle(cell)}
            className="inline-flex items-center gap-1 whitespace-nowrap" style={look}>
            {content}
        </span>
    );
}

/** The red "short of people" marker on a work-order card; a button that opens the order's insight. */
export function ShortMarker({ onOpen, children }) {
    const look = { color: '#fff', background: 'var(--om-blocked)', borderRadius: 3, padding: '0 3px', fontSize: 8, fontFamily: MONO, border: 0, lineHeight: 'inherit' };
    if (!onOpen) {
        return <span data-testid="short-staffed" title={__('Short of people in this shift')} className="inline-flex items-center" style={look}>{children}</span>;
    }
    const stop = (e) => e.stopPropagation();
    return (
        <button type="button" data-testid="short-staffed" title={`${__('Short of people in this shift')} — ${__('Click for the explanation')}`}
            aria-label={`${__('Short of people in this shift')}. ${__('Open the explanation in planning insights')}`}
            onPointerDown={stop} onMouseDown={stop} onClick={(e) => { e.stopPropagation(); onOpen(); }}
            className="inline-flex items-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1" style={{ ...look, cursor: 'pointer' }}>
            {children}
        </button>
    );
}

const HANDLE_TONE = {
    bad: { background: 'var(--om-blocked)', color: '#fff', borderColor: 'var(--om-blocked)' },
    warn: { background: 'var(--om-downtime)', color: '#fff', borderColor: 'var(--om-downtime)' },
    neutral: { background: 'var(--om-card)', color: 'var(--om-ink)', borderColor: 'var(--om-line)' },
};

/**
 * Tab on the right edge of the viewport — always visible, whatever the
 * scroll. Closed: at the edge; open: at the drawer's left edge, closes it.
 * Stacks above the board (its blocks go up to z 40) and below dialogs (z 45+).
 */
export function InsightsHandle({ tone = 'neutral', count = 0, open, onToggle }) {
    const palette = HANDLE_TONE[tone] ?? HANDLE_TONE.neutral;
    const edge = 'env(safe-area-inset-right, 0px)';
    return (
        <button type="button" onClick={onToggle} data-testid="insights-handle" data-tone={tone} data-open={open ? '1' : '0'}
            aria-expanded={open} aria-controls="planning-insights"
            aria-label={open ? __('Close planning insights') : __('Open planning insights (:count open)', { count })}
            title={__('Planning insights — press I to open or close')}
            className="om-insights-handle flex flex-col items-center gap-2 border shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
            style={{
                ...palette, position: 'fixed', zIndex: 41, top: '50%', transform: 'translateY(-50%)',
                right: open ? `calc(min(420px, 100vw - 44px) + ${edge})` : edge,
                padding: '12px 7px', borderRightWidth: 0, borderRadius: '10px 0 0 10px', cursor: 'pointer',
                transition: 'right .22s ease, background .2s ease',
            }}>
            <Icon name={open ? 'chevron-right' : 'chevron-left'} size={14} />
            {count > 0 && (
                <span data-testid="insights-handle-count" style={{
                    fontFamily: MONO, fontSize: 11, fontWeight: 700, minWidth: 20, height: 20, padding: '0 5px', borderRadius: 999,
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    background: tone === 'neutral' ? 'var(--om-ink)' : 'rgba(255,255,255,.95)',
                    color: tone === 'bad' ? 'var(--om-blocked)' : tone === 'warn' ? 'var(--om-downtime)' : 'var(--om-on-ink, #fff)',
                }}>{count}</span>
            )}
            <span style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)', fontSize: 12.5, fontWeight: 700, letterSpacing: '0.06em' }}>{__('Insights')}</span>
        </button>
    );
}

const SEVERITY = {
    bad: { color: 'var(--om-blocked)', bg: 'var(--om-blocked-bg)' },
    warn: { color: 'var(--om-downtime)', bg: 'var(--om-downtime-bg)' },
    info: { color: 'var(--om-accepted)', bg: 'var(--om-accepted-bg)' },
    ok: { color: 'var(--om-running)', bg: 'var(--om-running-bg)' },
};
const ICON = { short_shift: 'user-x', shared_operator: 'arrow-left-right', single_skill: 'star', untracked_line: 'flag', staffing_changed: 'user-cog' };
const GROUP_LABEL = { action: 'Needs action', watch: 'To watch', resolved: 'Resolved today' };
const FILTER_LABEL = { all: 'All', people: 'People', skills: 'Skills', plan: 'Plan' };

function Section({ title, children }) {
    return (
        <div>
            <div style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-faint)', marginBottom: 4 }}>{title}</div>
            {children}
        </div>
    );
}

function Bars({ numbers }) {
    if (!numbers || numbers.needed == null) return null;
    const bars = [
        [__('Need'), numbers.needed, 'var(--om-muted)'],
        [__('Have'), numbers.available, numbers.available < numbers.needed ? 'var(--om-blocked)' : 'var(--om-running)'],
    ];
    if (numbers.spare_elsewhere) bars.push([__('Free elsewhere'), numbers.spare_elsewhere, 'var(--om-running)']);
    const max = Math.max(1, ...bars.map((b) => b[1]));
    return (
        <div className="mt-2 flex items-end gap-4">
            {bars.map(([label, value, color]) => (
                <div key={label} className="flex flex-col items-center gap-1" style={{ fontFamily: MONO, fontSize: 10, color: 'var(--om-muted)' }}>
                    <div style={{ width: 26, height: 8 + 26 * value / max, background: color, borderRadius: 3 }} />
                    {label} {value}
                </div>
            ))}
        </div>
    );
}

function InsightItem({ insight, open, onToggle, onShow, onAction, busy, shown, focused }) {
    const sev = SEVERITY[insight.severity] ?? SEVERITY.info;
    return (
        <div data-testid="insight" data-type={insight.type} data-key={insight.key} data-focused={focused ? '1' : undefined}
            className="overflow-hidden rounded-om-sm border border-om-line bg-om-card"
            style={{ borderLeft: `3px solid ${sev.color}`, boxShadow: focused ? '0 0 0 2px var(--om-accent)' : 'none', transition: 'box-shadow .3s ease' }}>
            <button type="button" onClick={onToggle} aria-expanded={open} className="flex w-full items-start gap-2.5 px-3 py-2.5 text-left hover:bg-om-chip">
                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" style={{ background: sev.bg, color: sev.color }}>
                    <Icon name={ICON[insight.type] ?? 'info'} size={13} />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-[13px] font-semibold text-om-ink">{insight.title}</span>
                    <span className="block truncate text-[11.5px] text-om-muted">{insight.meta}</span>
                    <span className="mt-1 inline-block rounded-full px-2 py-px text-[10.5px] font-semibold" style={{ background: sev.bg, color: sev.color, fontFamily: MONO }}>{insight.impact}</span>
                </span>
                <span className="flex shrink-0 items-center gap-1 text-[10.5px] text-om-faint" style={{ fontFamily: MONO }}>
                    {insight.when}
                    <Icon name={open ? 'chevron-down' : 'chevron-right'} size={14} />
                </span>
            </button>
            {open && (
                <div className="space-y-3 border-t border-om-line px-3 py-3">
                    <Section title={__('Why')}>
                        <p className="m-0 text-[12.5px] leading-snug text-om-ink">{insight.why}</p>
                        <Bars numbers={insight.numbers} />
                    </Section>
                    {insight.affected?.length > 0 && (
                        <Section title={__('Affected orders')}>
                            <ul className="m-0 list-none space-y-1 p-0">
                                {insight.affected.map((a) => (
                                    <li key={a.work_order_id} className="flex items-baseline justify-between gap-2 text-[12px]">
                                        <span className="min-w-0 truncate">
                                            <b style={{ fontFamily: MONO }}>{a.order_no}</b>
                                            <span className="text-om-muted"> {a.product ? `${a.product} · ` : ''}{a.due_date ? __('due :date', { date: `${a.due_date.slice(8, 10)}.${a.due_date.slice(5, 7)}` }) : __('no due date')}</span>
                                        </span>
                                        <span className="shrink-0 text-[11px] font-semibold" style={{ color: a.late ? 'var(--om-blocked)' : 'var(--om-running)' }}>{a.note}</span>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}
                    <Section title={__('Suggested actions')}>
                        <div className="space-y-1.5">
                            {insight.actions.map((a, i) => (
                                <button key={i} type="button" disabled={busy} onClick={() => onAction(insight, a)} data-testid="insight-action" data-kind={a.kind}
                                    className="flex w-full items-start justify-between gap-2 rounded-om-sm border px-2.5 py-2 text-left hover:bg-om-chip disabled:opacity-50"
                                    style={{ borderColor: a.best ? 'var(--om-accent)' : 'var(--om-line)', background: a.best ? 'var(--om-accent-bg)' : 'transparent' }}>
                                    <span className="min-w-0">
                                        <span className="block text-[12.5px] font-semibold text-om-ink">{a.label}</span>
                                        <span className="block text-[11.5px] text-om-muted">{a.effect}</span>
                                    </span>
                                    {a.best && <span className="shrink-0 rounded px-1.5 py-px text-[9.5px] font-bold" style={{ background: 'var(--om-accent)', color: 'var(--om-on-ink, #fff)', fontFamily: MONO }}>{__('BEST')}</span>}
                                </button>
                            ))}
                        </div>
                    </Section>
                    {insight.cells?.length > 0 && (
                        <div className="flex justify-end">
                            <button type="button" onClick={() => onShow(insight)} className="text-[12px] font-semibold text-om-accent hover:underline" data-testid="show-on-plan">
                                {shown ? __('Shown on plan') : __('Show on plan')}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function ResolvedItem({ item, onUndo, busy }) {
    const at = item.at ? formatTime(item.at, { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
    return (
        <div data-testid="resolved" data-key={item.insight_key} className="rounded-om-sm border border-om-line bg-om-card px-3 py-2.5" style={{ borderLeft: `3px solid ${SEVERITY.ok.color}` }}>
            <div className="flex items-start gap-2.5">
                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" style={{ background: SEVERITY.ok.bg, color: SEVERITY.ok.color }}>
                    <Icon name="check" size={13} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="text-[13px] font-semibold text-om-ink">{item.title}</div>
                    <div className="text-[11.5px] text-om-muted">{item.summary}</div>
                    <div className="mt-1 text-[10.5px] text-om-faint" style={{ fontFamily: MONO }}>{item.by ?? '—'} · {at}</div>
                </div>
                <button type="button" disabled={busy} onClick={() => onUndo(item)} className="shrink-0 text-[12px] font-semibold text-om-accent hover:underline disabled:opacity-50" data-testid="undo">{__('Undo')}</button>
            </div>
        </div>
    );
}

export function InsightsDrawer({
    drawer, loading, filter, setFilter, openKey, onToggle, onShow, shownKey, onAction, onUndo, onClose, busy, updatedAt, rangeLabel,
    focusKey = null, onFocused = () => {}, autoOpen = true, onAutoOpenChange = () => {},
}) {
    // Opened from a badge or a card marker: bring that insight into view.
    const listRef = useRef(null);
    useEffect(() => {
        if (!focusKey) return undefined;
        const el = [...(listRef.current?.querySelectorAll('[data-testid="insight"]') ?? [])].find((n) => n.dataset.key === focusKey);
        if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const t = setTimeout(onFocused, 1800);
        return () => clearTimeout(t);
    }, [focusKey, drawer, onFocused]);
    const groups = groupInsights(drawer, filter);
    const counts = filterCounts(drawer);
    const summary = drawer?.summary ?? { needs_action: 0, to_watch: 0, resolved: 0 };
    const tiles = [
        [summary.needs_action, __('needs action'), SEVERITY.bad],
        [summary.to_watch, __('to watch'), SEVERITY.warn],
        [summary.resolved, __('resolved'), SEVERITY.ok],
    ];

    return (
        <aside aria-label={__('Planning insights')} data-testid="insights-drawer"
            className="om-drawer-in fixed bottom-0 right-0 top-0 z-40 flex w-full max-w-[420px] flex-col border-l border-om-line bg-om-panel shadow-xl">
            <div className="flex items-start justify-between gap-3 border-b border-om-line px-4 py-3">
                <div>
                    <h3 className="m-0 text-[16px] font-semibold text-om-ink">{__('Planning insights')}</h3>
                    <div className="text-[11.5px] text-om-muted">
                        {rangeLabel} · {updatedAt ? __('updated :time', { time: updatedAt }) : __('loading…')} · {__('live')}
                    </div>
                </div>
                <button type="button" onClick={onClose} aria-label={__('Close insights')} className="rounded p-1 text-om-muted hover:bg-om-chip"><Icon name="x" size={18} /></button>
            </div>
            <div className="grid grid-cols-3 gap-2 px-4 pt-3">
                {tiles.map(([value, label, sev]) => (
                    <div key={label} className="rounded-om-sm border border-om-line bg-om-card px-2.5 py-2">
                        <div style={{ fontFamily: MONO, fontSize: 20, fontWeight: 700, color: value > 0 ? sev.color : 'var(--om-faint)' }}>{value}</div>
                        <div className="text-[11px] text-om-muted">{label}</div>
                    </div>
                ))}
            </div>
            <div className="flex flex-wrap gap-1.5 px-4 py-3" role="group" aria-label={__('Filter insights')}>
                {FILTERS.map((f) => (
                    <button key={f} type="button" aria-pressed={filter === f} onClick={() => setFilter(f)}
                        className="rounded-full border px-3 py-1 text-[12px] font-medium"
                        style={filter === f ? { background: 'var(--om-ink)', color: 'var(--om-on-ink, #fff)', borderColor: 'var(--om-ink)' } : { borderColor: 'var(--om-line)', color: 'var(--om-ink)' }}>
                        {__(FILTER_LABEL[f])}{counts[f] > 0 ? ` ${counts[f]}` : ''}
                    </button>
                ))}
            </div>
            <div ref={listRef} className="flex-1 space-y-2 overflow-y-auto px-4 pb-6">
                {loading && !drawer && <p className="text-[12.5px] text-om-muted">{__('Loading…')}</p>}
                {drawer && groups.length === 0 && (
                    <p className="text-[12.5px] text-om-muted">{filter === 'all' ? __('Nothing to act on in this view.') : __('Nothing here.')}</p>
                )}
                {groups.map((g) => (
                    <div key={g.key} className="space-y-2">
                        <div className="pt-2" style={{ fontFamily: MONO, fontSize: 9.5, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--om-faint)' }}>{__(GROUP_LABEL[g.key])}</div>
                        {g.key === 'resolved'
                            ? g.items.map((r) => <ResolvedItem key={r.id} item={r} onUndo={onUndo} busy={busy} />)
                            : g.items.map((i) => (
                                <InsightItem key={i.key} insight={i} open={openKey === i.key} onToggle={() => onToggle(i)} onShow={onShow}
                                    shown={shownKey === i.key} focused={focusKey === i.key} onAction={onAction} busy={busy} />
                            ))}
                    </div>
                ))}
                {drawer && !drawer.provider?.skills && (
                    <p className="pt-3 text-[11px] text-om-faint">{__('Skill insights appear once qualifications are recorded.')}</p>
                )}
            </div>
            <div className="flex items-center justify-between gap-3 border-t border-om-line px-4 py-2.5" style={{ paddingBottom: 'calc(10px + env(safe-area-inset-bottom, 0px))' }}>
                <label className="flex cursor-pointer items-center gap-2 text-[12px] text-om-muted">
                    <input type="checkbox" data-testid="insights-auto-open-off" checked={!autoOpen} onChange={(e) => onAutoOpenChange(!e.target.checked)} />
                    {__("Don't open automatically")}
                </label>
                <span className="text-[11px] text-om-faint" style={{ fontFamily: MONO }} title={__('Keyboard shortcut')}>
                    <kbd className="rounded border border-om-line px-1">I</kbd> {__('open / close')}
                </span>
            </div>
        </aside>
    );
}
