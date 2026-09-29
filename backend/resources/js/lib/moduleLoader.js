/**
 * Loads the frontend of modules installed after this app was built.
 *
 * Core's own pages are compiled into the bundle; a module installed through
 * Admin → Modules → Install is not, and nothing rebuilds the bundle on a
 * running system — the production image deletes node_modules right after
 * building. So the module ships its own compiled file and core fetches it here.
 *
 * This runs before the first render (app.jsx awaits it next to the locale load)
 * because Inertia asks for the current page's component immediately. Registering
 * a moment too late means the very first screen after a hard refresh still
 * renders the missing-page fallback, and only works after a client-side visit —
 * the kind of bug that looks like a caching problem for a day.
 */

/** Scripts already requested, so a second call cannot load a module twice. */
const loaded = new Map();

function loadScript(url) {
    if (loaded.has(url)) {
        return loaded.get(url);
    }

    const pending = new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = url;
        script.async = false;

        // A missing or broken module file must not take the whole app down with
        // it: its pages fall back to the missing-page screen, every other screen
        // keeps working, and the reason is in the console.
        script.onerror = () => {
            console.error(`[openmes] Module script failed to load: ${url}`);
            resolve();
        };
        script.onload = () => resolve();

        document.head.appendChild(script);
    });

    loaded.set(url, pending);

    return pending;
}

/**
 * @param {Array<{name: string, url: string, requires_runtime?: number}>} modules
 *        As shared by HandleInertiaRequests; absent on pages that predate it.
 */
export async function loadModules(modules) {
    if (! Array.isArray(modules) || modules.length === 0) {
        return;
    }

    const apiVersion = window.__OPENMES__?.apiVersion;

    const runnable = modules.filter((module) => {
        const required = module.requires_runtime;

        // Core also checks this when enabling a module, so a mismatch here means
        // the app was upgraded under an already-enabled module. Skipping it
        // beats letting it bind to a contract that no longer holds.
        if (typeof required === 'number' && required !== apiVersion) {
            console.error(
                `[openmes] Module "${module.name}" was built for runtime v${required}, ` +
                `this OpenMES provides v${apiVersion}. Skipping it — update the module.`,
            );

            return false;
        }

        return true;
    });

    await Promise.all(runnable.map((module) => loadScript(module.url)));
}
