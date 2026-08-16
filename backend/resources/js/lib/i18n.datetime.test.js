import { describe, expect, it } from 'vitest';
import { formatDateTime, setTimezone } from './i18n';

/**
 * Timestamps reach the frontend two ways: ISO strings with a zone (Inertia props)
 * and raw DB timestamps with no zone marker (live-sync rows). Both must render as
 * the same instant in the configured plant timezone.
 */
describe('formatDateTime timezone handling', () => {
    it('treats a naive backend timestamp as UTC', () => {
        setTimezone('UTC');
        expect(formatDateTime('2026-08-03 18:53:16')).toBe('03/08/2026, 18:53');
    });

    it('renders a naive timestamp and its ISO equivalent identically', () => {
        setTimezone('UTC');
        expect(formatDateTime('2026-08-03 18:53:16')).toBe(formatDateTime('2026-08-03T18:53:16Z'));
    });

    it('shifts a naive timestamp into a non-UTC plant timezone', () => {
        setTimezone('Europe/Warsaw');
        expect(formatDateTime('2026-08-03 18:53:16')).toBe('03/08/2026, 20:53');
    });

    it('leaves date-only values alone', () => {
        setTimezone('UTC');
        expect(formatDateTime('2026-08-03')).toBe('03/08/2026, 00:00');
    });

    it('returns an empty string for empty input', () => {
        expect(formatDateTime(null)).toBe('');
        expect(formatDateTime('')).toBe('');
    });
});
