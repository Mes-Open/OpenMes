/**
 * Which component renders a given Inertia page name.
 *
 * Pages reach the browser from three places, in order of precedence:
 *
 *   1. this app, globbed at build time;
 *   2. a module that was present when the app was built, globbed the same way —
 *      `backend/modules/`, the directory PHP resolves as base_path('modules');
 *   3. a module installed afterwards, which registered itself at runtime.
 *
 * The first two maps are produced by `import.meta.glob` in app.jsx, so Vite
 * decides them while compiling; a glob that matches nothing yields {}. That is
 * why the third exists: a module installed through the panel is invisible to
 * both, and nothing rebuilds the bundle on a running system.
 *
 * Plain JS and dependency-free on purpose: the lookup is the part worth testing,
 * and app.jsx cannot be imported in a test (it boots the whole app on import).
 */

/** Core pages are keyed by their glob path, e.g. './Pages/admin/lines/Index.jsx'. */
export function coreKey(name) {
    return `./Pages/${name}.jsx`;
}

/**
 * Module pages are keyed by a path we only know the tail of, because the module
 * directory name is whatever the installer called it:
 *   '../../modules/<Anything>/resources/js/Pages/<name>.jsx'
 */
export function moduleKey(modulePages, name) {
    const suffix = `/resources/js/Pages/${name}.jsx`;

    return Object.keys(modulePages).find((path) => path.endsWith(suffix)) ?? null;
}

/**
 * The page component, or null when nothing provides it.
 *
 * Core wins over a module: a module must not be able to shadow a core screen by
 * naming a file after it. A compiled-in module wins over a runtime-registered
 * one for the same reason — if both are present, the one the operator built
 * into their image is the one they meant.
 */
export function resolvePage(name, corePages, modulePages = {}, runtimePages = {}) {
    const core = corePages[coreKey(name)];
    if (core) {
        return core;
    }

    const key = moduleKey(modulePages, name);
    if (key) {
        return modulePages[key];
    }

    // A module installed after this build: its pages are not in either glob, so
    // it registers them itself when its script runs (see lib/moduleLoader.js).
    return runtimePages[name] ?? null;
}
