// Pure helpers behind the dashboard's "Needs action" list
// (components/dashboard/overview/NeedsAction.jsx), kept here so they can be
// tested without React.
import { __ } from './i18n';

/** Rows shown before "Show all". */
export const NEEDS_ACTION_COLLAPSED = 6;

/**
 * How long something has been wrong: "<1 min", "12 min", "1 h 12 min",
 * "3 d". A duration only — no clock time, so no timezone question. Empty for
 * a missing or unreadable `since`; a `since` in the future reads "<1 min".
 */
export function formatAge(since, now = Date.now()) {
    if (!since) return '';
    const start = new Date(since).getTime();
    if (Number.isNaN(start)) return '';

    const min = Math.max(0, Math.floor((now - start) / 60_000));
    if (min < 1) return __('<1 min');
    if (min < 60) return __(':n min', { n: min });

    const hours = Math.floor(min / 60);
    if (hours < 24) {
        const rest = min % 60;
        return rest > 0 ? __(':h h :m min', { h: hours, m: rest }) : __(':h h', { h: hours });
    }

    return __(':d d', { d: Math.floor(hours / 24) });
}

/** The rows to draw: the first NEEDS_ACTION_COLLAPSED unless expanded. */
export function visibleItems(items, expanded) {
    const list = Array.isArray(items) ? items : [];
    return expanded ? list : list.slice(0, NEEDS_ACTION_COLLAPSED);
}
