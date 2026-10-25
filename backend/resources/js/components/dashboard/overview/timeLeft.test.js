import { afterAll, describe, expect, it } from 'vitest';
import { loadLocale } from '../../../lib/i18n';
import { shiftTimeLeft } from './timeLeft';

describe('shiftTimeLeft', () => {
    it.each([
        [179, '2 h 59 min left'],
        [180, '3 h left'],
        [181, '4 h left'],
        [480, '8 h left'],
        [120, '2 h left'],
        [61, '1 h 1 min left'],
        [60, '1 h left'],
        [59, '59 min left'],
        [0, '0 min left'],
    ])('%i min → %s', (minutes, expected) => {
        expect(shiftTimeLeft(minutes)).toBe(expected);
    });

    it('shows nothing without a running shift', () => {
        expect(shiftTimeLeft(null)).toBeNull();
        expect(shiftTimeLeft(undefined)).toBeNull();
        expect(shiftTimeLeft(-5)).toBeNull();
    });

    describe('in Polish', () => {
        afterAll(() => loadLocale('en'));

        it('rounds the same way', async () => {
            await loadLocale('pl');
            expect(shiftTimeLeft(179)).toBe('zostało 2 h 59 min');
            expect(shiftTimeLeft(180)).toBe('zostało 3 h');
            expect(shiftTimeLeft(45)).toBe('zostało 45 min');
        });
    });
});
