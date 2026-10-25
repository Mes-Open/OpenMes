import { __, formatDate, formatTime, todayInPlant } from '../../../lib/i18n';
import { lineStateLabel } from './lineStates';

/**
 * Pure formatting for the plant-overview "Lines" cards and summary strip.
 * Kept out of the components so it is unit-testable (lineCardFormat.test.js).
 * Times are always printed in the plant timezone (lib/i18n format helpers).
 */

/**
 * Time since `since` as "34 min" / "1h 16m" / "3h" (the mockup's style; unit
 * letters follow `elapsed`/`countdown`). '' when there is no usable timestamp.
 */
export function formatDuration(since, nowMs = Date.now()) {
    if (!since) return '';
    const start = new Date(since).getTime();
    if (Number.isNaN(start)) return '';

    const min = Math.max(0, Math.floor((nowMs - start) / 60_000));
    if (min < 60) return __(':count min', { count: min });

    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

/**
 * A plant-time clock reading, with the date in front when it isn't today —
 * "13:05" or "25.10.2026 08:40".
 */
export function clockLabel(iso, nowMs = Date.now()) {
    if (!iso) return '';
    const at = new Date(iso);
    if (Number.isNaN(at.getTime())) return '';
    const time = formatTime(at);

    return todayInPlant(at) === todayInPlant(new Date(nowMs)) ? time : `${formatDate(at)} ${time}`;
}

/**
 * The ETA row of a card: "On track · ETA 13:05", "Late · ETA 14:40",
 * "Late · due 12:00" (no rate and the planned end has passed) or "No ETA".
 *
 * @returns {{text: string, tone: 'ok'|'warn'|'mute'}}
 */
export function etaRow(lineOrder, nowMs = Date.now()) {
    if (!lineOrder?.eta) return { text: __('No ETA'), tone: 'mute' };

    const time = clockLabel(lineOrder.eta, nowMs);
    if (!lineOrder.late) return { text: __('On track · ETA :time', { time }), tone: 'ok' };

    return lineOrder.eta_source === 'plan'
        ? { text: __('Late · due :time', { time }), tone: 'warn' }
        : { text: __('Late · ETA :time', { time }), tone: 'warn' };
}

/** "Maintenance · 1h 16m" — the state's reason with how long it has lasted. */
function reasonRow(line, nowMs) {
    const reason = line.reason ?? lineStateLabel(line.state);
    const duration = formatDuration(line.since, nowMs);

    return duration ? `${reason} · ${duration}` : reason;
}

/**
 * The bottom row of a line card.
 *
 *  - no current order        → "No active order"
 *  - unknown (silent)        → "No data since …"
 *  - down / blocked          → reason + duration
 *  - running                 → the ETA row
 *  - idle with an order      → the reason if there is one, else the ETA row, muted
 *
 * @param {{state: string, reason: ?string, since: ?string}} line  overview.lines row
 * @param {?object} lineOrder  overview.line_orders row for the same line
 * @returns {{text: string, tone: 'ok'|'warn'|'bad'|'mute'}}
 */
export function bottomRow(line, lineOrder, nowMs = Date.now()) {
    // Its collector went silent: say since when, never a guess at progress.
    if (line.state === 'unknown') {
        return { text: line.since ? __('No data since :time', { time: formatTime(line.since) }) : __('No data'), tone: 'mute' };
    }
    if (!lineOrder?.order) return { text: __('No active order'), tone: 'mute' };

    switch (line.state) {
        case 'down':
            return { text: reasonRow(line, nowMs), tone: 'bad' };
        case 'blocked':
            return { text: reasonRow(line, nowMs), tone: 'warn' };
        case 'running':
            return etaRow(lineOrder, nowMs);
        default:
            return line.reason
                ? { text: reasonRow(line, nowMs), tone: 'mute' }
                : { ...etaRow(lineOrder, nowMs), tone: 'mute' };
    }
}

/** Whole percent done, floored and clamped; null when nothing is planned. */
export function percentDone(produced, planned) {
    const p = Number(planned);
    const d = Number(produced);
    if (!Number.isFinite(p) || p <= 0 || !Number.isFinite(d)) return null;

    return Math.min(100, Math.max(0, Math.floor((d / p) * 100)));
}

/** "13:10 · L1 CNC · AL-220" for the summary strip's next order. */
export function nextOrderLabel(next, nowMs = Date.now()) {
    if (!next) return '';

    return [clockLabel(next.planned_start_at, nowMs), next.line_name, next.product_code ?? next.order_no]
        .filter(Boolean)
        .join(' · ');
}
