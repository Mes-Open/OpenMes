import { __ } from '../../lib/i18n';

/**
 * Shared vocabulary for the plant board.
 *
 * Colours are CSS custom properties rather than Tailwind classes because the
 * tile paints a border and a wash from the same decision, and both land in
 * `style`. Keeping them as variables leaves light/dark to the theme.
 *
 * The groupings mirror `WorkstationState::LOSS_STATES` and `PLANNED_STATES` on
 * the server. If they ever drift, the board colours a machine differently from
 * the way the OEE report counts it, and the two screens start contradicting
 * each other in front of the people who have to reconcile them.
 */

/** Machine state → the tile's accent and wash. */
export const STATE_TONE = {
    RUNNING: { color: 'var(--om-running)', background: 'color-mix(in srgb, var(--om-running) 10%, transparent)' },

    // Unplanned loss — the tiles this board exists to make findable.
    STOPPED: { color: 'var(--om-blocked)', background: 'color-mix(in srgb, var(--om-blocked) 14%, transparent)' },
    FAULT: { color: 'var(--om-blocked)', background: 'color-mix(in srgb, var(--om-blocked) 14%, transparent)' },
    // WAITING is lost time, not rest (#87), so it is coloured with the other
    // losses. Reading it as "quietly fine" is exactly the mistake that leaves a
    // starved machine unattended for a whole shift.
    WAITING: { color: 'var(--om-blocked)', background: 'color-mix(in srgb, var(--om-blocked) 14%, transparent)' },

    // Planned: it reduces the operating window but is not an availability loss.
    CLEANING: { color: 'var(--om-planned)', background: 'color-mix(in srgb, var(--om-planned) 12%, transparent)' },
    MAINTENANCE: { color: 'var(--om-planned)', background: 'color-mix(in srgb, var(--om-planned) 12%, transparent)' },
    SETUP: { color: 'var(--om-maint)', background: 'color-mix(in srgb, var(--om-maint) 12%, transparent)' },

    // Idle is a machine with nothing to do — distinct from one that stopped
    // while it had work, which is the difference the board is read for.
    IDLE: { color: 'var(--om-muted)', background: 'transparent' },

    // Nothing has ever been heard from this station. Hatched rather than
    // filled: any solid colour would state something about minutes nobody has
    // any information about, and a dead collector reading as a quiet machine is
    // what stops anyone investigating it.
    unknown: {
        color: 'var(--om-muted)',
        background: 'repeating-linear-gradient(45deg, var(--om-faintest) 0 2px, transparent 2px 7px)',
    },
};

/**
 * States that mean the machine is down — mirrors
 * `WorkstationState::DOWNTIME_STATES`, the set the state machine opens a
 * ProductionDowntime for.
 */
const DOWNTIME_STATES = ['STOPPED', 'FAULT', 'WAITING', 'CLEANING', 'MAINTENANCE'];

/**
 * Whether the tile should be running a clock.
 *
 * The clock measures downtime, not time-in-state. A running machine that has
 * been up for a day and a half does not need a 29-hour counter on it: the
 * number would be the largest thing on a healthy tile and would read as an
 * alarm. Only a stop is worth counting, because only a stop is worth
 * interrupting.
 *
 * Either signal is enough. The state covers a machine reporting a stop that
 * nobody has opened a downtime row for; the open stop covers one raised by a
 * human while the collector still calls the machine idle.
 */
export function isDowntime(state, hasOpenStop) {
    return Boolean(hasOpenStop) || DOWNTIME_STATES.includes(state);
}

/** Machine state → what the tile calls it. */
export function stateLabel(state) {
    switch (state) {
        case 'RUNNING': return __('Running');
        case 'IDLE': return __('Idle');
        case 'STOPPED': return __('Stopped');
        case 'FAULT': return __('Fault');
        case 'SETUP': return __('Changeover');
        case 'WAITING': return __('Waiting');
        case 'CLEANING': return __('Cleaning');
        case 'MAINTENANCE': return __('Maintenance');
        default: return __('No signal');
    }
}

/**
 * How long the station has been in its current state, as a running clock.
 *
 * Counts up from when the state began — not down to the end of the shift. The
 * board's question is "how long has this been going on", and a stop that
 * outlives the shift it started in is still the same stop.
 *
 * Returns null when there is nothing to count from, so the tile can leave the
 * slot empty instead of showing a zero that looks like a stop just started.
 *
 * Not `elapsed()` from lib/i18n: that one rounds to the largest unit ("5m",
 * "2h") for timestamps in lists, where the age is context. Here the number is
 * the subject and it is being watched change, so it needs seconds and a fixed
 * width that doesn't jump as the digits grow.
 *
 * @param {?string} sinceIso when the current state began
 * @param {number} nowMs epoch milliseconds
 * @returns {?string} `H:MM:SS` past an hour, `MM:SS` below it
 */
export function elapsedLabel(sinceIso, nowMs) {
    if (!sinceIso) return null;

    const since = Date.parse(sinceIso);
    if (Number.isNaN(since)) return null;

    // A browser clock behind the server's would otherwise count backwards.
    // Floor at zero rather than hiding the clock: the state is real, only the
    // elapsed time is not yet meaningful.
    const seconds = Math.max(0, Math.floor((nowMs - since) / 1000));

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    const pad = (n) => String(n).padStart(2, '0');

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(secs)}`
        : `${pad(minutes)}:${pad(secs)}`;
}
