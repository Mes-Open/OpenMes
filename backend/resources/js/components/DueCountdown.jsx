import { __, countdown } from '../lib/i18n';

/**
 * How long is left until a deadline, in the tone that says how much it matters:
 * muted while there is time, amber inside the last day, red once it has passed.
 *
 * A printed date makes every reader do the same subtraction on every row to find
 * what needs attention today. This does it once, in the place they are already
 * looking. It renders the duration only — the caller decides whether the date
 * itself is shown beside or above it, because that varies by surface (a table
 * cell prints both, a planner card has room for one).
 *
 * `now` is optional: pass a ticking clock where one already exists (ResourceTable
 * hands `live: true` columns its shared 30s tick) and the value stays current.
 * Without one it is computed at render — right for a detail page or a card that
 * re-renders on interaction, and deliberately no new timer per instance.
 *
 * `settled` mutes an order that has already finished or been called off: its
 * deadline is a fact about the past, and painting it red would keep a closed
 * order looking like an open problem forever.
 */
export default function DueCountdown({ due, now = undefined, settled = false, className = '' }) {
    const left = countdown(due, now ?? Date.now());
    if (!left) return null;

    const tone = settled
        ? 'text-om-muted'
        : left.overdue
            ? 'text-om-blocked'
            : left.soon
                ? 'text-om-downtime'
                : 'text-om-muted';

    return (
        <span className={`tabular-nums ${tone} ${className}`.trim()}>
            {left.overdue ? __(':time overdue', { time: left.label }) : left.label}
        </span>
    );
}

/** Statuses whose deadline is history — see `settled` above. */
export const SETTLED_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];
