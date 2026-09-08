/**
 * Lightweight i18n that mirrors Laravel's __().
 *
 * The active locale's lang JSON (source-string keyed, e.g. lang/pl.json) is
 * loaded once at bootstrap as its own Vite chunk — code-split per locale and
 * browser-cached, so there's no per-request translation payload. A missing key
 * falls back to the key itself (the English source), exactly like Laravel.
 *
 * Usage (relative import — the project has no '@' alias):
 *   import { __ } from '../../../lib/i18n';   // depth depends on the page
 *   __('Tags & Signals')
 *   __('Delete tag ":name"?', { name: tag.name })
 *
 * Locale changes happen via a full reload (the /locale/{locale} route), so the
 * bootstrap re-runs and reloads the right chunk — no in-SPA reactivity needed.
 */

// Lazy glob → one dynamic-import chunk per locale file.
const localeFiles = import.meta.glob('../../../lang/*.json');

let messages = {};
let activeLocale = 'en';
// Plant timezone, set from the Inertia `timezone` prop at bootstrap. Undefined
// means "use the browser's zone" (Intl default) until configured.
let activeTimezone;

/** Load (and activate) a locale's messages. Call once before the first render. */
export async function loadLocale(locale) {
    const loader = localeFiles[`../../../lang/${locale}.json`];
    messages = loader ? (await loader()).default ?? {} : {};
    activeLocale = locale;
    return messages;
}

export function locale() {
    return activeLocale;
}

/** Set the plant timezone used by the format* helpers (from the `timezone` prop). */
export function setTimezone(tz) {
    activeTimezone = tz || undefined;
}

// Map app locale codes to BCP-47 tags for Intl. English uses en-GB (day-first,
// 24h) to match this app's European convention. Unmapped codes fall through to
// the code itself, so adding a locale to config/app.php just works.
const BCP47 = {
    en: 'en-GB',
    pl: 'pl-PL',
    tr: 'tr-TR',
    vi: 'vi-VN',
};

/** The active locale as a BCP-47 tag, for `Intl` and for locale-free packages. */
export function localeTag() {
    return BCP47[activeLocale] ?? activeLocale;
}

// Matches a backend timestamp with no zone marker, e.g. "2026-08-03 18:53:16"
// (also the "T" form, with optional seconds/fraction). Date-only strings are
// deliberately excluded — the spec already parses those as UTC.
const NAIVE_TIMESTAMP = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?$/;

function toDate(value) {
    if (value instanceof Date) return value;

    // Rows that arrive over live sync carry the raw DB timestamp, which has no
    // zone marker — `new Date()` would read it as browser-local and the format*
    // helpers would then shift it again into the plant timezone (a viewer in
    // UTC+2 saw a posted-at two hours early). The backend always stores UTC, so
    // pin it explicitly.
    if (typeof value === 'string' && NAIVE_TIMESTAMP.test(value)) {
        return new Date(`${value.replace(' ', 'T')}Z`);
    }

    return new Date(value);
}

/**
 * Locale- and timezone-aware formatting. These replace scattered hardcoded
 * toLocaleDateString('en-GB' | 'pl-PL', …) calls so date/time follows the
 * user's chosen UI language and the plant timezone (APP_TIMEZONE), not the
 * viewer's browser settings.
 *
 *   formatDate(value, opts?)      → date only
 *   formatTime(value, opts?)      → time only
 *   formatDateTime(value, opts?)  → date + time
 *   formatNumber(value, opts?)    → number (no timezone)
 *
 * `opts` is a standard Intl.DateTimeFormat / NumberFormat options object.
 * Invalid/empty input returns '' so callers don't render "Invalid Date".
 */
export function formatDate(value, opts = { day: '2-digit', month: '2-digit', year: 'numeric' }) {
    if (value == null || value === '') return '';
    const d = toDate(value);
    if (isNaN(d)) return '';
    return d.toLocaleDateString(localeTag(), { timeZone: activeTimezone, ...opts });
}

export function formatTime(value, opts = { hour: '2-digit', minute: '2-digit' }) {
    if (value == null || value === '') return '';
    const d = toDate(value);
    if (isNaN(d)) return '';
    return d.toLocaleTimeString(localeTag(), { timeZone: activeTimezone, ...opts });
}

export function formatDateTime(value, opts = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) {
    if (value == null || value === '') return '';
    const d = toDate(value);
    if (isNaN(d)) return '';
    return d.toLocaleString(localeTag(), { timeZone: activeTimezone, ...opts });
}

export function formatNumber(value, opts = {}) {
    if (value == null || value === '' || isNaN(value)) return '';
    return Number(value).toLocaleString(localeTag(), opts);
}

export function timeAgo(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return '';
    const sec = Math.round((Date.now() - dt.getTime()) / 1000);
    const abs = Math.abs(sec);
    const past = sec >= 0;
    const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]];
    for (const [name, s] of units) {
        if (abs >= s) {
            const n = Math.floor(abs / s);
            if (past) {
                return __(':count :unit ago', { count: n, unit: __(name + (n > 1 ? 's' : '')) });
            } else {
                return __('in :count :unit', { count: n, unit: __(name + (n > 1 ? 's' : '')) });
            }
        }
    }
    return past ? __('just now') : __('soon');
}

/**
 * Human-readable elapsed duration between `from` and `now` (default: the current
 * time), as a compact single unit: "just now", "5m", "3h", "2d", "1y". Unit
 * suffixes are language-neutral (matching the machine-monitor "time in state"
 * style); only "just now" is translated.
 *
 * `now` is injectable so a caller can drive a live tick (pass a ticking clock)
 * and so the formatting stays deterministic under test. Empty/invalid `from`
 * returns ''. A `from` in the future is clamped to zero ("just now").
 */
export function elapsed(from, now = Date.now()) {
    if (!from) return '';
    const start = from instanceof Date ? from.getTime() : new Date(from).getTime();
    if (Number.isNaN(start)) return '';

    const sec = Math.max(0, Math.floor((now - start) / 1000));
    if (sec < 60) return __('just now');

    const min = Math.floor(sec / 60);
    if (min < 60) return `${min}m`;

    const hr = Math.floor(min / 60);
    if (hr < 24) return `${hr}h`;

    const day = Math.floor(hr / 24);
    if (day < 365) return `${day}d`;

    return `${Math.floor(day / 365)}y`;
}

/**
 * Time left until a deadline, as the two units that matter at that distance:
 * days + hours while it is more than a day out, hours + minutes once it is
 * inside the last day — which is what a deadline falling today looks like.
 * Unit suffixes follow `elapsed`'s language-neutral style; only "overdue" is
 * translated.
 *
 * A deadline stored at exactly midnight is a *date*, not an instant — the form
 * that sets it only offers a day — so it falls due at the END of that day.
 * Counting to 00:00 instead would report an order due today as already late,
 * every time. A value that carries a real time of day is used as it stands.
 *
 * Day-only deadlines are read in the same frame the cell above them prints
 * (the timestamp's own date part), so the countdown and the date can't disagree
 * about which day is meant.
 *
 * `now` is injectable for a live tick and for deterministic tests.
 *
 * @returns {{label: string, overdue: boolean, soon: boolean}|null} null when
 *          there is no deadline or the value can't be read.
 */
export function countdown(due, now = Date.now()) {
    if (!due) return null;

    const iso = due instanceof Date ? due.toISOString() : String(due);
    const dayOnly = iso.length <= 10 || iso.slice(11, 16) === '00:00';
    const at = dayOnly
        ? new Date(`${iso.slice(0, 10)}T23:59:59.999Z`)
        : new Date(due);
    if (Number.isNaN(at.getTime())) return null;

    const ms = at.getTime() - now;
    const overdue = ms < 0;

    const min = Math.floor(Math.abs(ms) / 60_000);
    const days = Math.floor(min / 1440);
    const hours = Math.floor((min % 1440) / 60);
    const mins = min % 60;

    // The smaller unit is dropped when it is zero rather than printed as "0h":
    // "3d" says the same thing as "3d 0h" in less space.
    const label = days > 0
        ? (hours > 0 ? `${days}d ${hours}h` : `${days}d`)
        : (hours > 0 ? (mins > 0 ? `${hours}h ${mins}m` : `${hours}h`) : `${mins}m`);

    return { label, overdue, soon: !overdue && days === 0 };
}

function capitalize(s) {
    s = String(s);
    return s.charAt(0).toUpperCase() + s.slice(1);
}

/**
 * Translate a source string with optional Laravel-style :placeholder
 * replacement, including the :Capitalized and :UPPER case variants.
 * Replacements are applied longest-key-first so a key isn't clobbered by a
 * shorter key that is a prefix of it.
 */
export function __(key, replacements = {}) {
    let line = messages[key] ?? key;

    const names = Object.keys(replacements).sort((a, b) => b.length - a.length);
    for (const name of names) {
        const v = String(replacements[name]);
        line = line
            .replaceAll(`:${name.toUpperCase()}`, v.toUpperCase())
            .replaceAll(`:${capitalize(name)}`, capitalize(v))
            .replaceAll(`:${name}`, v);
    }

    return line;
}
