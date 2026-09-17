import { describe, expect, it } from 'vitest';

// Vitest resolves import.meta.glob like Vite does, so this exercises the real
// module-lang merge in i18n.js against whatever modules/ holds — which, in
// core's own checkout, is only the reference modules with no lang/ directory.
import { __, loadLocale } from './i18n';

describe('module translations', () => {
    it('loads a locale with or without module lang files', async () => {
        const messages = await loadLocale('pl');
        expect(typeof messages).toBe('object');
        expect(__('Dashboard')).toBeTypeOf('string');
    });

    it('falls back to the key for an unknown string', async () => {
        await loadLocale('en');
        expect(__('A string no file defines')).toBe('A string no file defines');
    });
});
