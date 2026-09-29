/**
 * Read and write query parameters of the current address without a visit, so
 * an in-page choice (the selected order, the focused step) survives a reload
 * and travels with a shared link. The history entry keeps Inertia's own state:
 * only the address changes.
 */
export function readParam(name) {
    if (typeof window === 'undefined') return null;
    try {
        return new URLSearchParams(window.location.search).get(name);
    } catch {
        return null;
    }
}

/** Set (string/number) or remove (null/undefined/'') the given parameters. */
export function writeParams(params) {
    if (typeof window === 'undefined') return;
    try {
        const url = new URL(window.location.href);
        Object.entries(params).forEach(([key, value]) => {
            if (value == null || value === '') url.searchParams.delete(key);
            else url.searchParams.set(key, String(value));
        });
        window.history.replaceState(window.history.state, '', url.toString());
    } catch {
        // Not critical: the page works without the address reflecting the choice.
    }
}
