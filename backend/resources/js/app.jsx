import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { UILabelsProvider } from '@openmes/ui';
import { __, loadLocale, setTimezone } from './lib/i18n';
import { resolvePage } from './lib/pageResolver';
import './lib/echo'; // opens the single Reverb WebSocket

// Pages come from two places: this app, and any module installed under the
// repository's modules/ directory. Both globs are resolved by Vite at build
// time; a glob that matches nothing yields {}, so a build with no modules
// installed simply has an empty second map. The lookup itself lives in
// lib/pageResolver.js, where it can be tested.
const corePages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
const modulePages = import.meta.glob('../../modules/*/resources/js/Pages/**/*.jsx', { eager: true });

// Screens that post with fetch() read the CSRF token from <meta name="csrf-token">.
// That tag is rendered once, with the first page; an Inertia visit replaces the
// page but not the head. Signing in regenerates the session token, so without
// this the first scan after logging in (landing on a station by an Inertia
// redirect) was refused with 419 until a reload. Every page carries the current
// token as a prop: keep the tag in step with it.
function syncCsrfToken(token) {
    if (!token || typeof document === 'undefined') return;
    let meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', token);
}
router.on('navigate', (event) => syncCsrfToken(event.detail.page.props?.csrf_token));

createInertiaApp({
    resolve: (name) => {
        const page = resolvePage(name, corePages, modulePages);
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
        syncCsrfToken(props.initialPage.props.csrf_token);
        // The design-system package ships no words of its own: the labels its
        // controls need (a search box placeholder, an empty-result note) come
        // from here, translated, once for the whole app.
        const uiLabels = { searchPlaceholder: __('Search…'), noResultsLabel: __('No matches'), clearLabel: __('Clear') };
        createRoot(el).render(<UILabelsProvider labels={uiLabels}><App {...props} /></UILabelsProvider>);
    },
    progress: { color: '#1e40af' },
});
