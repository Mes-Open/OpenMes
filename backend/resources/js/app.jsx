import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { loadLocale, setTimezone } from './lib/i18n';
import './lib/echo'; // opens the single Reverb WebSocket

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name} (expected resources/js/Pages/${name}.jsx)`);
        }
        return page;
    },
    async setup({ el, App, props }) {
        // Load the active locale's translation chunk before the first render so
        // __() is ready and there's no flash of untranslated/wrong-language text.
        await loadLocale(props.initialPage.props.locale ?? 'en');
        // Plant timezone for the date/time format helpers (lib/i18n).
        setTimezone(props.initialPage.props.timezone);
        // Tenant key for Reverb channel names (null-safe → 'g'), mirrors TenantScope.
        window.__TENANT__ = props.initialPage.props.auth?.user?.tenant_id ?? 'g';
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#1e40af' },
});
