import { describe, expect, it } from 'vitest';
import { durationLabel } from './i18n';

describe('durationLabel', () => {
    it('formats minutes as hours and minutes', () => {
        expect(durationLabel(0)).toBe('0 min');
        expect(durationLabel(45)).toBe('45 min');
        expect(durationLabel(300)).toBe('5 h');
        expect(durationLabel(333)).toBe('5 h 33 min');
    });

    it('never prints NaN or negatives', () => {
        expect(durationLabel(null)).toBe('0 min');
        expect(durationLabel(-5)).toBe('0 min');
    });
});
