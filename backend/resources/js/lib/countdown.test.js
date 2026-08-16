import { describe, expect, it } from 'vitest';
import { countdown } from './i18n';

// Fixed reference "now" so every case is deterministic (countdown takes `now` as
// its second argument precisely so the live tick can be driven and tests pinned).
const NOW = Date.UTC(2026, 6, 18, 12, 0, 0); // Saturday 2026-07-18 12:00:00 UTC

const MIN = 60_000;
const HOUR = 60 * MIN;

describe('countdown', () => {
    it('returns null for missing or unreadable input', () => {
        expect(countdown(null, NOW)).toBeNull();
        expect(countdown(undefined, NOW)).toBeNull();
        expect(countdown('', NOW)).toBeNull();
        expect(countdown('not-a-date', NOW)).toBeNull();
    });

    // A date-only deadline falls due at the END of its day — the form that sets
    // it only offers a day, so counting to 00:00 would call today's orders late.
    it('treats a midnight timestamp as the end of that day', () => {
        const today = countdown('2026-07-18T00:00:00.000000Z', NOW);
        expect(today.overdue).toBe(false);
        expect(today.label).toBe('11h 59m');
    });

    it('accepts a bare date string', () => {
        expect(countdown('2026-07-18', NOW).label).toBe('11h 59m');
    });

    it('shows hours and minutes inside the last day', () => {
        expect(countdown(new Date(NOW + 5 * HOUR + 20 * MIN).toISOString(), NOW).label).toBe('5h 20m');
        expect(countdown(new Date(NOW + 45 * MIN).toISOString(), NOW).label).toBe('45m');
    });

    it('shows days and hours further out', () => {
        expect(countdown(new Date(NOW + 26 * HOUR).toISOString(), NOW).label).toBe('1d 2h');
        // 2026-07-21 23:59:59.999Z is 3 days and just under 12 hours away.
        expect(countdown('2026-07-21T00:00:00.000000Z', NOW).label).toBe('3d 11h');
    });

    it('drops a zero smaller unit rather than printing "0h" or "0m"', () => {
        expect(countdown(new Date(NOW + 72 * HOUR).toISOString(), NOW).label).toBe('3d');
        expect(countdown(new Date(NOW + 3 * HOUR).toISOString(), NOW).label).toBe('3h');
    });

    it('flags a deadline inside the last day as soon, and nothing further out', () => {
        expect(countdown(new Date(NOW + 5 * HOUR).toISOString(), NOW).soon).toBe(true);
        expect(countdown(new Date(NOW + 26 * HOUR).toISOString(), NOW).soon).toBe(false);
    });

    it('reports a passed deadline as overdue, measured the same way', () => {
        const late = countdown('2026-07-15T00:00:00.000000Z', NOW);
        expect(late.overdue).toBe(true);
        expect(late.soon).toBe(false);
        // Due end of the 15th, so 2 days and 12 hours have passed.
        expect(late.label).toBe('2d 12h');
    });

    it('a deadline with a real time of day is counted to that instant', () => {
        // 14:30 today — not midnight, so it is not stretched to end of day.
        expect(countdown('2026-07-18T14:30:00.000000Z', NOW).label).toBe('2h 30m');
    });

    it('accepts a Date instance as well as a string', () => {
        expect(countdown(new Date(NOW + 3 * HOUR), NOW).label).toBe('3h');
    });
});
