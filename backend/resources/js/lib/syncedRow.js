/**
 * Reading form values out of a synced-collection row.
 *
 * A record that reached the browser through Eloquent — a server prop on a
 * standalone edit page — arrives cast, formatted and with its accessors
 * applied. The same record read out of a synced collection does not: the
 * snapshot in `CollectionController` is a `DB::table()` query, which bypasses
 * casts, accessors and date formatting, so what you get is what Postgres
 * stored. The list drawer edits the second kind, the standalone page the first,
 * and both feed the same `xInitial(record)` builder.
 *
 * The mismatches are all silent rather than loud, which is why they belong in
 * one place:
 *   - a `json` column is a string, and `{ ...'{"a":1}' }` is a valid object of
 *     per-character keys while `'[1,2]'.map(…)` is a TypeError,
 *   - a `datetime` column is `2026-08-26 10:04:57`, which a `datetime-local`
 *     input rejects outright and renders empty,
 *   - a `time` column carries seconds a `time` input won't show.
 *
 * An accessor (a computed `image_url`, say) has no raw column at all — no
 * helper can recover it, so the list has to send it as its own prop.
 */

/** A `json` column as its decoded value, whichever way the record arrived. */
export function jsonColumn(raw, fallback = null) {
    if (raw == null) return fallback;
    if (typeof raw !== 'string') return raw;
    try {
        const parsed = JSON.parse(raw);
        // A bare string or number is valid JSON and still not the shape asked for.
        return parsed !== null && typeof parsed === 'object' ? parsed : fallback;
    } catch {
        return fallback;
    }
}

/** A `datetime` column as the `Y-m-dTH:i` an `<input type="datetime-local">` wants. */
export function dateTimeLocal(raw) {
    if (!raw) return '';
    return String(raw).replace(' ', 'T').slice(0, 16);
}

/** A `time` column as the `H:i` an `<input type="time">` wants. */
export function timeOfDay(raw) {
    if (!raw) return '';
    return String(raw).slice(0, 5);
}
