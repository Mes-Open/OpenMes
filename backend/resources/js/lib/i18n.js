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
