import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { elapsed, loadLocale, parseTimestamp, timeAgo } from './i18n';

/**
 * Live-sync rows carry raw DB timestamps with no zone marker ("2026-11-20
 * 09:36:22"), stored in UTC. Read as browser-local time they came out an hour
 * old in Warsaw: a panel that had just sent a heartbeat showed as inactive,
 * "1 hour ago", until the next live update brought an ISO value.
 */
const NOW = Date.UTC(2026, 10, 20, 9, 38, 0); // 2026-11-20 09:38:00 UTC
const naive = (msAgo) => new Date(NOW - msAgo).toISOString().slice(0, 19).replace('T', ' ');

describe('naive timestamps from live sync', () => {
    const originalTz = process.env.TZ;

    beforeEach(async () => {
        process.env.TZ = 'Europe/Warsaw'; // browser one hour east of UTC
        vi.useFakeTimers();
        vi.setSystemTime(NOW);
        await loadLocale('en');
    });

    afterEach(() => {
        vi.useRealTimers();
        process.env.TZ = originalTz;
    });

    it('parses a naive timestamp as UTC, not browser-local time', () => {
        expect(parseTimestamp('2026-11-20 09:36:22').toISOString()).toBe('2026-11-20T09:36:22.000Z');
    });

    it('timeAgo of a two-minute-old row says two minutes, not an hour', () => {
        expect(timeAgo(naive(2 * 60_000))).toBe('2 minutes ago');
    });

    it('timeAgo agrees for the naive and ISO form of the same instant', () => {
        const iso = new Date(NOW - 5 * 60_000).toISOString();
        expect(timeAgo(naive(5 * 60_000))).toBe(timeAgo(iso));
    });

    it('elapsed reads a naive timestamp as UTC too', () => {
        expect(elapsed(naive(3 * 60_000), NOW)).toBe('3m');
    });

    it('uses the language plural forms', async () => {
        await loadLocale('pl');
        expect(timeAgo(naive(60 * 60_000))).toBe('1 godzinę temu');
        expect(timeAgo(naive(5 * 60 * 60_000))).toBe('5 godzin temu');
        expect(timeAgo(naive(2 * 60_000))).toBe('2 minuty temu');
    });
});
