import { describe, expect, it } from 'vitest';
import { formatDate, setTimezone } from './i18n';

/**
 * DB `date` columns (expiry_date, record_date, …) reach the frontend as
 * `YYYY-MM-DD`, from the API and from live sync alike. They name a day, so
 * the plant timezone must not move them.
 */
describe('formatDate with a calendar date', () => {
    it.each(['UTC', 'Europe/Warsaw', 'America/Chicago', 'Pacific/Auckland', 'Pacific/Honolulu'])(
        'shows the named day in %s',
        (tz) => {
            setTimezone(tz);
            expect(formatDate('2026-03-10')).toBe('10/03/2026');
        },
    );

    it('still shifts a full timestamp into the plant zone', () => {
        setTimezone('America/Chicago');
        // 03:00 UTC on 10 Mar is the evening of the 9th in Chicago.
        expect(formatDate('2026-03-10T03:00:00Z')).toBe('09/03/2026');
    });

    it('passes custom options through', () => {
        setTimezone('America/Chicago');
        expect(formatDate('2026-03-10', { year: 'numeric', month: '2-digit' })).toBe('03/2026');
    });

    it('returns an empty string for an impossible date', () => {
        expect(formatDate('2026-13-45')).toBe('');
    });
});
