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
};

function localeTag() {
    return BCP47[activeLocale] ?? activeLocale;
}

function toDate(value) {
    return value instanceof Date ? value : new Date(value);
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
