import { describe, expect, it } from 'vitest';
import { formatAge, visibleItems, NEEDS_ACTION_COLLAPSED } from './needsAction';

const NOW = Date.UTC(2026, 6, 18, 12, 0, 0);
const ago = (minutes) => new Date(NOW - minutes * 60_000).toISOString();

describe('formatAge', () => {
    it('is empty for missing or invalid input', () => {
        expect(formatAge(null, NOW)).toBe('');
        expect(formatAge('', NOW)).toBe('');
        expect(formatAge('not-a-date', NOW)).toBe('');
    });

    it('says <1 min under a minute and for a future since', () => {
        expect(formatAge(new Date(NOW - 30_000).toISOString(), NOW)).toBe('<1 min');
        expect(formatAge(ago(-5), NOW)).toBe('<1 min');
    });

    it('counts minutes, then hours and minutes, then days', () => {
        expect(formatAge(ago(12), NOW)).toBe('12 min');
        expect(formatAge(ago(72), NOW)).toBe('1 h 12 min');
        expect(formatAge(ago(120), NOW)).toBe('2 h');
        expect(formatAge(ago(3 * 24 * 60 + 5), NOW)).toBe('3 d');
    });
});

describe('visibleItems', () => {
    const items = Array.from({ length: 9 }, (_, i) => ({ key: `k${i}` }));

    it('caps the collapsed list', () => {
        expect(visibleItems(items, false)).toHaveLength(NEEDS_ACTION_COLLAPSED);
        expect(visibleItems(items, false)[0].key).toBe('k0');
    });

    it('shows everything when expanded', () => {
        expect(visibleItems(items, true)).toHaveLength(9);
    });

    it('tolerates a missing list', () => {
        expect(visibleItems(undefined, false)).toEqual([]);
    });
});
