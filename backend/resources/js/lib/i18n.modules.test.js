import { describe, expect, it } from 'vitest';

// Vitest resolves import.meta.glob like Vite does, so this exercises the real
// module-lang merge in i18n.js against whatever modules/ holds — which, in
// core's own checkout, is only the reference modules with no lang/ directory.
import { __, loadLocale, mergeMessages } from './i18n';

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

    it('adds a module-only string but never overrides a core one', () => {
        const core = { 'Weekly view': 'Tygodniowy' };
        const merged = mergeMessages(core, [{ 'Weekly view': 'Z modułu', 'Waste register': 'Rejestr strat' }]);

        expect(merged['Waste register']).toBe('Rejestr strat');
        expect(merged['Weekly view']).toBe('Tygodniowy');
    });

    it('lets the core pl.json win over a module that repeats its key', async () => {
        const core = await loadLocale('pl');
        const merged = mergeMessages(core, [{ Dashboard: 'overridden by a module' }]);

        expect(merged.Dashboard).toBe(core.Dashboard);
        expect(merged.Dashboard).not.toBe('overridden by a module');
    });
});

