import { __ } from '../../../lib/i18n';

/**
 * Shared vocabulary for line state on the plant overview — the one place every
 * section reads it from, so a line is never "Down" in one card and "Idle" in
 * the next. The mapping from machine states lives on the server
 * (App\Services\Dashboard\PlantOverviewService); this only names and paints it.
 *
 * Colours are CSS custom properties, as in shift-board/tokens.js, so light and
 * dark come from the theme.
 */

/**
 * @typedef {'running'|'idle'|'blocked'|'down'|'unknown'} LineState
 *
 * @typedef {object} OverviewLine  one row of `overview.lines`
 * @property {number} id
 * @property {string} name
 * @property {LineState} state
 * @property {?string} since         ISO timestamp; for `unknown`, the last signal
 * @property {?string} reason
 * @property {boolean} needs_reason  an open stop still has no cause
 * @property {'machine'|'manual'} source
 * @property {boolean} has_collector
 */

/** Legend order: the mockup's, plus "No data" last. */
export const LINE_STATES = ['running', 'idle', 'blocked', 'down', 'unknown'];

// Hatched rather than filled, like the shift board's "no signal": any solid
// colour would claim something about minutes nobody has information about.
const HATCH = 'repeating-linear-gradient(45deg, var(--om-faintest) 0 2px, transparent 2px 7px)';

/**
 * State → `swatch` (legend square / bar fill) and `pill` (text + wash).
 * Down is red and blocked amber, as in the mockup.
 */
export const LINE_STATE_TONE = {
    running: { swatch: 'var(--om-running)', pill: { color: 'var(--om-running)', background: 'var(--om-running-bg)' } },
    idle: { swatch: 'var(--om-faintest)', pill: { color: 'var(--om-muted)', background: 'color-mix(in srgb, var(--om-faintest) 35%, transparent)' } },
    blocked: { swatch: 'var(--om-downtime)', pill: { color: 'var(--om-downtime)', background: 'var(--om-downtime-bg)' } },
    down: { swatch: 'var(--om-blocked)', pill: { color: 'var(--om-blocked)', background: 'var(--om-blocked-bg)' } },
    unknown: { swatch: HATCH, pill: { color: 'var(--om-muted)', background: HATCH } },
};

/** Tone for a state; anything unrecognised is painted as "No data". */
export function lineStateTone(state) {
    return LINE_STATE_TONE[state] ?? LINE_STATE_TONE.unknown;
}

/** Translated label for a state; anything unrecognised reads "No data". */
export function lineStateLabel(state) {
    switch (state) {
        case 'running': return __('Running');
        case 'idle': return __('Idle');
        case 'blocked': return __('Blocked');
        case 'down': return __('Down');
        default: return __('No data');
    }
}

/**
 * Whether the line's state can be believed. `unknown` means its collector went
 * silent: sections must show that as missing data (an empty state), never as
 * idle, zero output or a gap in a timeline painted as a state.
 *
 * @param {?OverviewLine} line
 */
export const hasLiveData = (line) => !!line && LINE_STATES.includes(line.state) && line.state !== 'unknown';
