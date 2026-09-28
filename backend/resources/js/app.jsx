import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { loadLocale, setTimezone } from './lib/i18n';
import { loadModules } from './lib/moduleLoader';
import { resolvePage } from './lib/pageResolver';
import './lib/echo'; // opens the single Reverb WebSocket

// Pages come from two places: this app, and any module installed under the
// repository's modules/ directory. Both globs are resolved by Vite at build
// time; a glob that matches nothing yields {}, so a build with no modules
// installed simply has an empty second map. The lookup itself lives in
// lib/pageResolver.js, where it can be tested.
const corePages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
const modulePages = import.meta.glob('../../modules/*/resources/js/Pages/**/*.jsx', { eager: true });

/**
 * Start fetching the frontends of modules installed after this build, at import
 * time.
 *
 * Not in setup(): Inertia resolves the *initial* page's component before it ever
 * calls setup, so a module loaded there would always be one render too late —
 * a hard refresh onto a module page would fail while client-side visits worked.
 *
 * The list is read straight off the Inertia payload, the same one Inertia boots
 * from, so no extra request is needed to find out what to load.
 */
const modulesReady = loadModules(initialProps()?.moduleAssets);

function initialProps() {
    try {
        // Inertia renders the initial payload either as a JSON <script> or in the
        // root element's data-page attribute, depending on version. Read whichever
        // is there: getting this wrong is silent — module loading simply never
        // starts, and every module page falls through to the missing-page screen.
        const json = document.querySelector('script[type="application/json"][data-page]');
        const raw = json ? json.textContent : document.getElementById('app')?.dataset.page;

        return JSON.parse(raw ?? '{}').props ?? null;
    } catch {
        // A malformed payload is Inertia's problem to report, not ours; skip
        // module loading rather than breaking boot with a parse error.
        return null;
    }
}

createInertiaApp({
    resolve: async (name) => {
        // Awaiting here rather than before createInertiaApp keeps the resolver
        // honest: whatever a module registered is visible by the time the first
        // lookup happens, however Inertia orders its own startup.
        await modulesReady;

        const page = resolvePage(name, corePages, modulePages, window.__OPENMES_PAGES__ ?? {});
        if (page) {
            return page;
        }

        // A route outliving its page — a module that isn't installed, or a stale
        // link. This used to throw, which meant a white screen and the reason
        // only in the console. Render something the user can act on instead.
        console.warn(`Inertia page not found: ${name}`);

        return import('./Pages/_MissingPage.jsx').then((module) => ({
            ...module,
            default: (props) => module.default({ ...props, __pageName: name }),
        }));
    },
    async setup({ el, App, props }) {
        // Load the active locale's translation chunk before the first render so
        // __() is ready and there's no flash of untranslated/wrong-language text.
        await loadLocale(props.initialPage.props.locale ?? 'en');
        // Set the active timezone from the Inertia prop.
        setTimezone(props.initialPage.props.timezone);
        // Tenant key for Reverb channel names (null-safe → 'g'), mirrors TenantScope.
        window.__TENANT__ = props.initialPage.props.auth?.user?.tenant_id ?? 'g';
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#1e40af' },
});
