// Work-order blocks + small atoms, following the OpenMES Schedule design:
// status-tinted surface, Geist-Mono order numbers, hover-✕ to unschedule.
import { __ } from '../../../../lib/i18n';
import { TIER_BADGE_STYLES, tierLabel } from '../../customers/fields';
import { statusOf, statusLabel, priorityMeta, fmtQty, shiftColor, MONO } from './helpers';

// Solid dot color per customer tier, for the compact Gantt cards.
const TIER_DOT = {
    bronze: 'bg-amber-500',
    silver: 'bg-gray-400',
    gold: 'bg-yellow-400',
    vip: 'bg-purple-500',
};

// Compact customer-tier marker: colored dot, tooltip carries name + tier.
export function TierDot({ wo }) {
    if (!wo.customer_tier) return null;
    return (
        <span className={`inline-block w-2 h-2 rounded-full shrink-0 ${TIER_DOT[wo.customer_tier] ?? ''}`}
            title={`${wo.customer_name ?? ''}${wo.customer_tier ? ` · ${tierLabel(wo.customer_tier)}` : ''}`} />
    );
}

// Chip marking a block that also runs on another line. Directional: '→ CODE'
// = the order continues on that line next, 'CODE →' = it came from that line,
// '⇄ CODE' = both placements run concurrently.
export function TwinChip({ code, dir = 'both' }) {
    const label = dir === 'to' ? `→ ${code}` : dir === 'from' ? `${code} →` : `⇄ ${code}`;
    const title = dir === 'to' ? __('Continues on') + ' ' + code
        : dir === 'from' ? __('Continued from') + ' ' + code
            : __('Also runs on') + ' ' + code;
    return (
        <span title={title} className="whitespace-nowrap"
            style={{ fontFamily: MONO, fontSize: 8, fontWeight: 600, color: 'var(--om-accent)', background: 'var(--om-accent-bg)', border: '1px solid color-mix(in srgb, var(--om-accent) 30%, transparent)', borderRadius: 3, padding: '0 3px' }}>
            {label}
        </span>
    );
}

// Mono status chip (uppercase).
export function StatusPill({ status }) {
    const s = statusOf(status);
    return (
        <span className="inline-flex items-center whitespace-nowrap uppercase"
            style={{ fontFamily: MONO, fontSize: 8.5, letterSpacing: '0.05em', color: s.solid, background: s.soft, borderRadius: 20, padding: '2px 7px' }}>
            {statusLabel(status)}
        </span>
    );
}

export function LoadBar({ pct, color, w = 90 }) {
    return (
        <div className="flex items-center gap-2">
            <div className="rounded-full overflow-hidden" style={{ width: w, height: 5, background: 'var(--om-card)', border: '1px solid var(--om-line2)' }}>
                <div style={{ width: Math.min(100, pct) + '%', height: '100%', background: color, borderRadius: 20 }} />
            </div>
            <span style={{ fontFamily: MONO, fontSize: 11, fontWeight: 600, color, width: 38, textAlign: 'right' }}>{pct}%</span>
        </div>
    );
}

// ── OrderCard ─────────────────────────────────────────────────────────────────
// variant: 'cell' (weekly) | 'day' (daily) | 'backlog' | 'overlay'
export function OrderCard({ wo, variant = 'cell', selected = false, conflict = false, twinMeta = null, onClick, onUnassign, unassignTitle, dragProps = {} }) {
    const s = statusOf(wo.status);
    const overdue = wo.is_overdue;
    const ring = selected ? `0 0 0 2px var(--om-accent)` : (overdue || conflict ? '0 0 0 1.5px var(--om-blocked)' : 'none');

    if (variant === 'backlog') {
        const pm = priorityMeta(wo.priority);
        return (
            <div {...dragProps} onClick={onClick}
                className="om-wo"
                style={{ background: 'var(--om-card)', border: '1px solid var(--om-line)', borderRadius: 9, padding: '11px 12px', cursor: 'grab', boxShadow: selected ? '0 0 0 2px var(--om-accent)' : 'none' }}>
                <div className="flex items-center justify-between mb-1.5">
                    <span className="flex items-center gap-1.5" style={{ fontFamily: MONO, fontSize: 11.5, fontWeight: 600, color: 'var(--om-ink)' }}><TierDot wo={wo} />{wo.order_no}</span>
                    <StatusPill status={wo.status} />
                </div>
                <div className="mb-1.5" style={{ fontSize: 12.5, color: 'var(--om-ink)' }}>{wo.product_name || '—'}</div>
                {wo.customer_name && (
                    <div className="mb-1.5 flex items-center gap-1">
                        <span className="truncate" style={{ fontSize: 10.5, color: 'var(--om-muted)', maxWidth: 130 }} title={wo.customer_name}>{wo.customer_name}</span>
                        {wo.customer_tier && (
                            <span className={`px-1 py-0.5 rounded text-[8px] font-medium ${TIER_BADGE_STYLES[wo.customer_tier] ?? ''}`}>{tierLabel(wo.customer_tier)}</span>
                        )}
                    </div>
                )}
                <div className="flex items-center gap-2" style={{ fontFamily: MONO, fontSize: 9.5, color: 'var(--om-faint)' }}>
                    <span>{fmtQty(wo.planned_qty)} {__('pcs')}</span><span>·</span>
                    <span style={{ color: pm.color }}>{__(pm.label)}</span>
                </div>
            </div>
        );
    }

    if (variant === 'day') {
        return (
            <div {...dragProps} onClick={onClick} className="om-wo relative"
                style={{ width: 168, background: s.soft, border: '1px solid var(--om-line2)', borderRadius: 8, padding: '9px 10px', cursor: 'grab', boxShadow: ring }}>
                {onUnassign && <UnassignX onUnassign={onUnassign} wo={wo} title={unassignTitle} />}
                <div className="flex items-center gap-1.5 mb-0.5">
                    <span style={{ width: 5, height: 5, borderRadius: 999, background: shiftColor() }} />
                    <TierDot wo={wo} />
                    <span style={{ fontFamily: MONO, fontSize: 11, fontWeight: 600, color: 'var(--om-ink)' }}>{wo.order_no}</span>
                    {twinMeta && <TwinChip code={twinMeta.code} dir={twinMeta.dir} />}
                </div>
                <div className="truncate" style={{ fontSize: 11, color: 'var(--om-muted)' }}>{wo.product_name || '—'}</div>
                <div className="mt-0.5" style={{ fontFamily: MONO, fontSize: 9, color: 'var(--om-faint)' }}>{fmtQty(wo.planned_qty)} {__('pcs')} · {statusLabel(wo.status)}</div>
            </div>
        );
    }

    // 'cell' (weekly grid) and 'overlay' (drag preview)
    return (
        <div {...dragProps} onClick={onClick} className="om-wo relative w-full"
            style={{ background: s.soft, border: '1px solid var(--om-line2)', borderRadius: 6, padding: '6px 8px', cursor: 'grab', boxShadow: ring }}>
            {onUnassign && <UnassignX onUnassign={onUnassign} wo={wo} />}
            <div className="flex items-center gap-1.5">
                <span className="whitespace-nowrap" style={{ fontFamily: MONO, fontSize: 10, fontWeight: 600, color: 'var(--om-ink)' }}>{wo.order_no}</span>
                {overdue && <span className="ml-auto" style={{ fontFamily: MONO, fontSize: 8, color: '#fff', background: 'var(--om-blocked)', borderRadius: 3, padding: '0 3px' }}>!</span>}
            </div>
            <div className="truncate" style={{ fontSize: 10, color: 'var(--om-muted)', marginTop: 2 }}>{wo.product_name || '—'} · {fmtQty(wo.planned_qty)}</div>
        </div>
    );
}

// Hover-reveal ✕ that returns the order to the backlog (or, for a two-line
// order's secondary copy, detaches just that line — see `title`).
function UnassignX({ onUnassign, wo, title }) {
    return (
        <span className="om-x" onClick={(e) => { e.stopPropagation(); onUnassign(wo); }}
            title={title ?? __('Send to backlog')}
            style={{ position: 'absolute', top: -6, right: -6, width: 15, height: 15, borderRadius: 999, background: 'var(--om-blocked)', color: '#fff', fontSize: 8, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0, transition: 'opacity .15s', zIndex: 3, cursor: 'pointer' }}>✕</span>
    );
}
