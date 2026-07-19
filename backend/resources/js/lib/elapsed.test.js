import { describe, expect, it } from 'vitest';
import { elapsed } from './i18n';

// Fixed reference "now" so every case is deterministic (elapsed takes `now` as
// its second argument precisely so the live tick can be driven and tests pinned).
const NOW = Date.UTC(2026, 6, 18, 12, 0, 0); // 2026-07-18 12:00:00 UTC
const ago = (seconds) => new Date(NOW - seconds * 1000).toISOString();

const MIN = 60;
const HOUR = 60 * MIN;
const DAY = 24 * HOUR;

describe('elapsed', () => {
    it('returns empty string for missing or invalid input', () => {
        expect(elapsed(null, NOW)).toBe('');
        expect(elapsed(undefined, NOW)).toBe('');
        expect(elapsed('', NOW)).toBe('');
        expect(elapsed('not-a-date', NOW)).toBe('');
    });

    it('shows "just now" under a minute', () => {
        expect(elapsed(ago(0), NOW)).toBe('just now');
        expect(elapsed(ago(1), NOW)).toBe('just now');
        expect(elapsed(ago(59), NOW)).toBe('just now');
    });

    it('shows whole minutes up to an hour', () => {
        expect(elapsed(ago(MIN), NOW)).toBe('1m');
        expect(elapsed(ago(90), NOW)).toBe('1m'); // floors
        expect(elapsed(ago(59 * MIN), NOW)).toBe('59m');
    });

    it('shows whole hours up to a day', () => {
        expect(elapsed(ago(HOUR), NOW)).toBe('1h');
        expect(elapsed(ago(HOUR + 59 * MIN), NOW)).toBe('1h'); // floors
        expect(elapsed(ago(23 * HOUR), NOW)).toBe('23h');
    });

    it('shows whole days up to a year', () => {
        expect(elapsed(ago(DAY), NOW)).toBe('1d');
        expect(elapsed(ago(100 * DAY), NOW)).toBe('100d');
        expect(elapsed(ago(364 * DAY), NOW)).toBe('364d');
    });

    it('shows whole years from 365 days on', () => {
        expect(elapsed(ago(365 * DAY), NOW)).toBe('1y');
        expect(elapsed(ago(2 * 365 * DAY), NOW)).toBe('2y');
    });

    it('clamps a future timestamp to "just now"', () => {
        expect(elapsed(new Date(NOW + 5000).toISOString(), NOW)).toBe('just now');
    });

    it('accepts a Date instance as well as an ISO string', () => {
        expect(elapsed(new Date(NOW - 3 * HOUR * 1000), NOW)).toBe('3h');
    });

    it('defaults `now` to the current time when omitted', () => {
        // Created ~2 hours before the real now: value is stable within the test window.
        const twoHoursAgo = new Date(Date.now() - 2 * HOUR * 1000).toISOString();
        expect(elapsed(twoHoursAgo)).toBe('2h');
    });
});
