/**
 * StatusBadge — a status chip: colour + Lucide icon + label.
 *
 * The rule the palette enforces is that **green means exactly one thing**:
 * successfully finished. A list where "in progress" and "done" are both green
 * reads, at a glance, as a list of finished work. Anything that isn't the happy
 * ending gives up its claim to green — running work is purple (active, with no
 * success/warning baggage), approved-but-not-started is blue (informational).
 *
 * Colour is never the only cue: every tone carries a distinct icon, which is
 * what keeps the set readable for the ~8% of men with a red/green deficiency.
 *
 * `ghost` exists because two states are semantically grey — not-started and
 * cancelled. Rather than hunt for a ninth hue, cancelled varies the *treatment*:
 * a hollow chip reads as "this row is inert".
 *
 * API is identical to the native twin (index.native.tsx).
 */
import { Icon } from '../Icon';

/**
 * Tone → token pair. Names are deliberately generic (a work order, an alert and
 * a quality task all have "the blocked one"); which token each resolves to is
 * an implementation detail kept in this one table.
 */
/**
 * The soft chips carry `border-current/30`: a hairline mixed from the chip's own
 * text colour, so every tone gets its edge for free and it holds up in dark mode
 * where a fixed border colour would not. A tinted fill alone reads as a smudge
 * against the row — the edge is what makes it a chip.
 */
const SOFT_EDGE = 'border border-current/30';

const TONES = {
    neutral: { soft: `bg-om-pending-bg text-om-pending ${SOFT_EDGE}`, solid: 'bg-om-pending text-white border border-transparent', dot: 'bg-om-pending' },
    info: { soft: `bg-om-accepted-bg text-om-accepted ${SOFT_EDGE}`, solid: 'bg-om-accepted text-white border border-transparent', dot: 'bg-om-accepted' },
    active: { soft: `bg-om-maint-bg text-om-maint ${SOFT_EDGE}`, solid: 'bg-om-maint text-white border border-transparent', dot: 'bg-om-maint' },
    warn: { soft: `bg-om-downtime-bg text-om-downtime ${SOFT_EDGE}`, solid: 'bg-om-downtime text-white border border-transparent', dot: 'bg-om-downtime' },
    danger: { soft: `bg-om-blocked-bg text-om-blocked ${SOFT_EDGE}`, solid: 'bg-om-blocked text-white border border-transparent', dot: 'bg-om-blocked' },
    success: { soft: `bg-om-running-bg text-om-running ${SOFT_EDGE}`, solid: 'bg-om-running text-white border border-transparent', dot: 'bg-om-running' },
    critical: { soft: `bg-om-rejected-bg text-om-rejected ${SOFT_EDGE}`, solid: 'bg-om-rejected text-white border border-transparent', dot: 'bg-om-rejected' },
    ghost: {
        soft: 'border border-om-faintest text-om-faint',
        solid: 'border border-om-faintest text-om-faint',
        dot: 'border-[1.5px] border-om-faintest',
    },
};

const SIZES = {
    sm: { chip: 'gap-1 px-2 py-[2px] text-[11px]', icon: 12 },
    md: { chip: 'gap-1.5 px-2.5 py-[4px] text-[12px]', icon: 14 },
    lg: { chip: 'gap-1.5 px-3.5 py-[6px] text-[13px]', icon: 16 },
};

export function StatusBadge({
    /** Visible text. Callers pass an already-translated label. */
    label,
    /** Lucide icon name, e.g. 'circle-check'. */
    icon,
    /** One of: neutral | info | active | warn | danger | success | critical | ghost */
    tone = 'neutral',
    /** 'soft' tints the background; 'solid' fills it — use solid sparingly, for
     *  the one status a screen is about. */
    variant = 'soft',
    size = 'md',
    showIcon = true,
    className = '',
    ...props
}) {
    const t = TONES[tone] ?? TONES.neutral;
    const s = SIZES[size] ?? SIZES.md;

    return (
        <span
            className={`inline-flex items-center rounded-full font-medium leading-[1.4] whitespace-nowrap ${s.chip} ${
                t[variant] ?? t.soft
            } ${className}`}
            {...props}
        >
            {showIcon && icon && <Icon name={icon} size={s.icon} className="shrink-0" />}
            {label}
        </span>
    );
}

/**
 * StatusDot — the quiet form: a coloured dot plus plain text.
 *
 * For lists where most rows share the same status. Eight rows of filled chips
 * is noise, and it buries the two rows that are actually blocked; dots for the
 * routine states keep badges meaningful for the exceptions.
 */
export function StatusDot({ label, tone = 'neutral', withLabel = true, className = '', ...props }) {
    const t = TONES[tone] ?? TONES.neutral;

    return (
        <span className={`inline-flex items-center gap-2 text-[13px] text-om-ink ${className}`} {...props}>
            <span aria-hidden="true" className={`size-2 shrink-0 rounded-full ${t.dot}`} />
            {withLabel && label}
        </span>
    );
}
