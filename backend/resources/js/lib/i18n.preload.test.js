import { afterEach, describe, expect, it, vi } from 'vitest';

// i18n.js loads the page's locale in a top-level await, so a module that
// translates at import time (a field config, an option list) gets the active
// language rather than the English source. Each case imports a fresh copy.
function fakeDocument({ locale, htmlLang } = {}) {
    const payload = locale === undefined ? null : { textContent: JSON.stringify({ props: { locale } }) };

    return {
        querySelector: () => payload,
        getElementById: () => null,
        documentElement: { lang: htmlLang ?? '' },
    };
}

describe('locale preload', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.resetModules();
    });

    it('has the page locale loaded by the time the module is imported', async () => {
        vi.stubGlobal('document', fakeDocument({ locale: 'pl' }));
        const { __, locale } = await import('./i18n');

        expect(locale()).toBe('pl');
        expect(__('Every N minutes')).toBe('Co N minut');
    });

    it('falls back to <html lang> on a page without an Inertia payload', async () => {
        vi.stubGlobal('document', fakeDocument({ htmlLang: 'pl' }));
        const { __ } = await import('./i18n');

        expect(__('Every N minutes')).toBe('Co N minut');
    });

    it('stays on the source strings for an unknown locale instead of failing to load', async () => {
        vi.stubGlobal('document', fakeDocument({ locale: 'xx' }));
        const { __ } = await import('./i18n');

        expect(__('Every N minutes')).toBe('Every N minutes');
    });

    it('loads nothing without a document', async () => {
        const { __, locale } = await import('./i18n');

        expect(locale()).toBe('en');
        expect(__('Every N minutes')).toBe('Every N minutes');
    });
});
