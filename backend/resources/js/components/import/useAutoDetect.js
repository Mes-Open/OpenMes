/**
 * Header → field auto-detection for the mapping screen. Aliases come from the
 * backend (each importer's field list), so the web and mobile screens can't
 * drift; this only normalises both sides the same way before comparing.
 */
export function normaliseHeader(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** @returns {Object<string,string>} header → field key, only for matched headers */
export function autoDetect(headers, fields) {
    const index = new Map();
    for (const field of fields) {
        for (const alias of [field.key, ...(field.aliases ?? [])]) {
            const norm = normaliseHeader(alias);
            if (norm && !index.has(norm)) index.set(norm, field.key);
        }
    }
    const out = {};
    const used = new Set();
    for (const h of headers) {
        const key = index.get(normaliseHeader(h));
        if (key && !used.has(key)) {
            out[h] = key;
            used.add(key);
        }
    }
    return out;
}
