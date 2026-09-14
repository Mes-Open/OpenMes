/**
 * Which component renders a given Inertia page name.
 *
 * Pages come from two places: this app, and any module installed under
 * `backend/modules/` — the same directory PHP resolves as base_path('modules'),
 * which is where ModuleManager looks and what the dev overlay bind-mounts. Both
 * maps are produced by `import.meta.glob` in app.jsx; a glob that matches
 * nothing yields {}, so an install with no modules passes an empty second map.
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
 * The page component, or null when no build contains it.
 *
 * Core wins over a module: a module must not be able to shadow a core screen by
 * naming a file after it.
 */
export function resolvePage(name, corePages, modulePages = {}) {
    const core = corePages[coreKey(name)];
    if (core) {
        return core;
    }

    const key = moduleKey(modulePages, name);

    return key ? modulePages[key] : null;
}
