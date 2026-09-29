/**
 * The contract between OpenMES core and an installed module's frontend.
 *
 * A module's pages are compiled long before — and somewhere else than — the
 * core bundle they end up running inside. They cannot bring their own React:
 * two React instances on one page break hooks. They also must not inline core's
 * components, or every module would ship its own stale copy of ResourceTable.
 *
 * So core exposes exactly these entries at runtime under `window.__OPENMES__`,
 * and a module's build marks the same entries as external. This file is the
 * single source of both lists — core's runtime is generated from it, and so is
 * the module build's globals map, which is the only way the two cannot drift.
 *
 * Adding an entry is cheap. Removing or changing the shape of one breaks every
 * module already built against it, which is what CONTRACT_VERSION is for.
 */

/**
 * Bumped whenever an entry is removed, renamed, or changes shape in a way a
 * module could notice. Core advertises it as `window.__OPENMES__.apiVersion`;
 * a module records the value it was built against in its manifest, and core
 * refuses to enable a module built for a version it does not provide.
 *
 * Adding a new entry does NOT require a bump: a module built against the older
 * version simply never reaches for it.
 */
export const CONTRACT_VERSION = 1;

/**
 * Import specifier → the global expression a module build resolves it to.
 *
 * The values are paths into `window.__OPENMES__`, which core's runtime entry
 * populates. Keys are exactly what a module writes in its `import` statements —
 * `@core/…` mirrors the alias core's own pages use, so a page moved from core
 * into a module needs no edit.
 *
 * Everything here is a module NAMESPACE object, not a bare default export. The
 * module build is configured with `interop: 'esModule'` so `import X from …`
 * reads `.default` off it — without that, a default import silently binds to
 * the namespace object and renders nothing.
 */
export const CONTRACT = {
    // ── Shared runtime: the packages a module must never bundle itself ──
    'react': '__OPENMES__.react',
    '@inertiajs/react': '__OPENMES__.inertia',
    '@openmes/ui': '__OPENMES__.ui',
    // Subpaths are separate specifiers with their own exports — matching on the
    // package root alone leaves them to be bundled into the module, which is how
    // a second copy of the table gets shipped.
    '@openmes/ui/table': '__OPENMES__.uiTable',

    // The automatic JSX transform emits imports from these, not from 'react'.
    // Leaving them out looks harmless — the build succeeds — and then the module
    // carries its own copy of the JSX runtime, which is a second React in all
    // the ways that matter.
    'react/jsx-runtime': '__OPENMES__.jsxRuntime',
    'react/jsx-dev-runtime': '__OPENMES__.jsxDevRuntime',

    // ── Layout ──
    '@core/layouts/AppLayout': '__OPENMES__.core.AppLayout',

    // ── The CRUD engine most module pages are built on ──
    '@core/components/ResourceForm': '__OPENMES__.core.ResourceForm',
    '@core/components/ResourceTable': '__OPENMES__.core.ResourceTable',
    '@core/components/ResourceFormDrawer': '__OPENMES__.core.ResourceFormDrawer',
    '@core/components/AppDataTable': '__OPENMES__.core.AppDataTable',
    '@core/components/RepeatableRows': '__OPENMES__.core.RepeatableRows',

    // ── Page chrome ──
    '@core/components/PageTitle': '__OPENMES__.core.PageTitle',
    '@core/components/PageTrail': '__OPENMES__.core.PageTrail',
    '@core/components/Tooltip': '__OPENMES__.core.Tooltip',
    '@core/components/CustomFieldsDisplay': '__OPENMES__.core.CustomFieldsDisplay',
    '@core/components/useConfirm': '__OPENMES__.core.useConfirm',

    // ── Helpers ──
    '@core/lib/i18n': '__OPENMES__.core.i18n',
    '@core/lib/syncedRow': '__OPENMES__.core.syncedRow',
    '@core/lib/fieldName': '__OPENMES__.core.fieldName',
};

/** Import specifiers a module may use, for the build-time contract gate. */
export const ALLOWED_IMPORTS = Object.keys(CONTRACT);

/**
 * Where core's runtime publishes the page registry a module writes into, and
 * where core reads it back when Inertia asks for a component by name.
 */
export const PAGES_GLOBAL = '__OPENMES_PAGES__';

/** The global holding the shared runtime itself. */
export const RUNTIME_GLOBAL = '__OPENMES__';
