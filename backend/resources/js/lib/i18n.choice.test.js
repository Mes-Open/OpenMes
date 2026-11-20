import { describe, expect, it } from 'vitest';
import { loadLocale, pluralIndex, transChoice } from './i18n';

describe('transChoice', () => {
    it('picks the English singular and plural', async () => {
        await loadLocale('en');
        expect(transChoice(':count operator assigned|:count operators assigned', 1)).toBe('1 operator assigned');
        expect(transChoice(':count operator assigned|:count operators assigned', 3)).toBe('3 operators assigned');
        expect(transChoice(':count operator assigned|:count operators assigned', 0)).toBe('0 operators assigned');
    });

    it('uses the three Polish forms', async () => {
        await loadLocale('pl');
        expect(transChoice(':count order|:count orders', 1)).toBe('1 zlecenie');
        expect(transChoice(':count order|:count orders', 3)).toBe('3 zlecenia');
        expect(transChoice(':count order|:count orders', 5)).toBe('5 zleceń');
        expect(transChoice(':count order|:count orders', 22)).toBe('22 zlecenia');
        expect(transChoice(':count order|:count orders', 12)).toBe('12 zleceń');
        await loadLocale('en');
    });

    it('falls back to the key for an unknown string and replaces other placeholders', async () => {
        await loadLocale('en');
        expect(transChoice(':count cell on :line|:count cells on :line', 2, { line: 'CNC' })).toBe('2 cells on CNC');
    });

    it('matches Laravel’s plural rule for Polish', () => {
        expect([1, 2, 4, 5, 11, 12, 14, 21, 22, 25, 102].map((n) => pluralIndex('pl', n))).toEqual([0, 1, 1, 2, 2, 2, 2, 2, 1, 2, 1]);
        expect([0, 1, 2].map((n) => pluralIndex('en', n))).toEqual([1, 0, 1]);
    });
});
