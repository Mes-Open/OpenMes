import { oeeColor } from '../OeeGauge';

/**
 * Shared vocabulary for the shift monitor.
 *
 * The timeline is drawn with inline colours rather than Tailwind classes: bar
 * widths and positions are percentages computed per segment, so these already
 * live in `style`, and pulling the colour out into a class would split one
 * visual decision across two mechanisms. The values are CSS custom properties,
 * so light/dark still resolve from the theme rather than being hardcoded here.
 */

/** Segment kind → the bar's fill. A stop is two colours: known cause vs not. */
export const SEGMENT_COLOR = {
    run: 'var(--om-running)',
    slow: 'var(--om-downtime)',
    down: 'var(--om-deep)',
    downUnclassified: 'var(--om-blocked)',
    plan: 'var(--om-planned)',
    idle: 'transparent',
    // Hatched, not a flat colour: minutes the machine reported nothing for are
    // *unknown*, and any solid fill would state something about them. Idle time
    // is bare track, so a hole in the record has to look different from it — a
    // dead collector reading as a quiet line is what stops anyone investigating.
    none: 'repeating-linear-gradient(45deg, var(--om-faintest) 0 2px, transparent 2px 7px)',
};

/** Event-pin type → its balloon colour and glyph. One per type eventPins emits. */
export const PIN_STYLE = {
    batch: { color: 'var(--om-accepted)', glyph: '⇄' },
    escalate: { color: 'var(--om-blocked)', glyph: '!' },
    qc: { color: 'var(--om-running)', glyph: '✓' },
    comment: { color: 'var(--om-downtime)', glyph: '✎' },
};

/** Downtime kind → the colour the cause picker groups it under. */
export const KIND_COLOR = {
    unplanned: 'var(--om-blocked)',
    planned: 'var(--om-accepted)',
    changeover: 'var(--om-maint)',
    unclassified: 'var(--om-blocked)',
};

/** Analysis waterfall / quality tone → colour. */
export const TONE_COLOR = {
    neutral: 'var(--om-faintest)',
    planned: 'var(--om-planned)',
    blocked: 'var(--om-blocked)',
    slow: 'var(--om-downtime)',
    running: 'var(--om-running)',
};

export function segmentColor(segment) {
    if (segment.kind === 'down') {
        return segment.needsCause ? SEGMENT_COLOR.downUnclassified : SEGMENT_COLOR.down;
    }
    return SEGMENT_COLOR[segment.kind] ?? SEGMENT_COLOR.idle;
}

/**
 * A percentage-of-the-hour position. Minutes are the only unit the backend
 * emits, so every horizontal placement on the timeline goes through here.
 */
export function pct(minutes, span = 60) {
    return `${(minutes / span) * 100}%`;
}

/** Colour a ratio against target: on pace, slipping, or badly behind. */
export function ratioColor(ratio) {
    if (ratio === null || ratio === undefined) return 'var(--om-muted)';
    if (ratio >= 0.9) return 'var(--om-running)';
    if (ratio >= 0.7) return 'var(--om-downtime)';
    return 'var(--om-blocked)';
}

/**
 * Colour an OEE percentage on the app's one banding — red < 65 ≤ amber < 85 ≤
 * green, from OeeBand.php via OeeGauge. A second banding here would show the
 * same station green on this screen and amber on /admin/oee, which reads as a
 * data bug to anyone comparing the two.
 */
const OEE_TONE = {
    green: 'var(--om-running)',
    yellow: 'var(--om-downtime)',
    red: 'var(--om-blocked)',
    gray: 'var(--om-muted)',
};

export function scoreColor(value) {
    return OEE_TONE[oeeColor(value)];
}

/** Text colour for an analysis tone; `neutral` reads as plain ink, not a hue. */
export function toneTextColor(tone) {
    return tone === 'neutral' ? 'var(--om-ink)' : TONE_COLOR[tone];
}
