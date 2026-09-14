import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { loadLocale, setTimezone } from './lib/i18n';
import { resolvePage } from './lib/pageResolver';
import './lib/echo'; // opens the single Reverb WebSocket

// Pages come from two places: this app, and any module installed under the
// repository's modules/ directory. Both globs are resolved by Vite at build
// time; a glob that matches nothing yields {}, so a build with no modules
// installed simply has an empty second map. The lookup itself lives in
// lib/pageResolver.js, where it can be tested.
const corePages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
const modulePages = import.meta.glob('../../modules/*/resources/js/Pages/**/*.jsx', { eager: true });

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
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#1e40af' },
});
