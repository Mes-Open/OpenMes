import { __ } from '../../../lib/i18n';

/**
 * Time left in the shift, for the overview header.
 *
 *   < 60 min      → "45 min left"
 *   60–179 min    → "2 h 59 min left" ("2 h left" on the hour)
 *   ≥ 180 min     → whole hours, rounded UP: 181 → "4 h left"
 *
 * Under three hours the minutes matter, so they're shown. Above, the hour is
 * rounded up rather than down: truncating made 2 h 59 min read "2 h left", an
 * hour sooner than the truth (the mockup shows "3h" there). `minutes` is the
 * server's ceil'd minute count; null (no shift running) or negative → null.
 */
export function shiftTimeLeft(minutes) {
    if (minutes == null || minutes < 0) return null;
    if (minutes < 60) return __(':count min left', { count: minutes });
    if (minutes >= 180) return __(':count h left', { count: Math.ceil(minutes / 60) });

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest === 0
        ? __(':count h left', { count: hours })
        : __(':hours h :minutes min left', { hours, minutes: rest });
}
