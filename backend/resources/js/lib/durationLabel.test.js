import { describe, expect, it } from 'vitest';
import { durationLabel } from './i18n';

describe('durationLabel', () => {
    it('formats minutes as hours and minutes', () => {
        expect(durationLabel(0)).toBe('0m');
        expect(durationLabel(45)).toBe('45m');
        expect(durationLabel(300)).toBe('5h');
        expect(durationLabel(333)).toBe('5h 33m');
    });

    it('never prints NaN or negatives', () => {
        expect(durationLabel(null)).toBe('0m');
        expect(durationLabel(-5)).toBe('0m');
    });
});
