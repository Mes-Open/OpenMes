import { afterEach, describe, expect, it } from 'vitest';
import { setTimezone, timezone, todayInTimezone } from './i18n';

/**
 * "Today" must be the plant's date, not the UTC date `toISOString()` gives —
 * otherwise the overdue count flips hours away from the plant's midnight.
 */
describe('todayInTimezone', () => {
    afterEach(() => setTimezone(undefined));

    it('is already the next day in Warsaw at 23:30 UTC', () => {
        const now = new Date('2026-06-14T23:30:00Z');
        expect(todayInTimezone(now, 'Europe/Warsaw')).toBe('2026-06-15');
        expect(todayInTimezone(now, 'UTC')).toBe('2026-06-14');
    });

    it('is the local date at 00:30 Warsaw summer time (22:30 UTC)', () => {
        expect(todayInTimezone(new Date('2026-06-14T22:30:00Z'), 'Europe/Warsaw')).toBe('2026-06-15');
    });

    it('follows the winter offset too', () => {
        expect(todayInTimezone(new Date('2026-01-14T23:30:00Z'), 'Europe/Warsaw')).toBe('2026-01-15');
        expect(todayInTimezone(new Date('2026-01-14T22:30:00Z'), 'Europe/Warsaw')).toBe('2026-01-14');
    });

    it('stays on the previous day west of UTC', () => {
        expect(todayInTimezone(new Date('2026-06-15T02:00:00Z'), 'America/New_York')).toBe('2026-06-14');
    });

    it('defaults to the zone set by setTimezone', () => {
        setTimezone('Europe/Warsaw');
        expect(timezone()).toBe('Europe/Warsaw');
        expect(todayInTimezone(new Date('2026-06-14T23:30:00Z'))).toBe('2026-06-15');
    });

    it('zero-pads month and day', () => {
        expect(todayInTimezone(new Date('2026-03-05T12:00:00Z'), 'UTC')).toBe('2026-03-05');
    });

    it('accepts an ISO string', () => {
        expect(todayInTimezone('2026-06-14T23:30:00Z', 'Europe/Warsaw')).toBe('2026-06-15');
    });
});
