import { describe, expect, it } from 'vitest';
import { otherTabVisible, randomId, TAB_STALE_MS } from './operatorHeartbeat';

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

describe('operator panel device id', () => {
    it('is a v4 uuid', () => {
        expect(randomId()).toMatch(UUID);
    });

    it('falls back to getRandomValues without randomUUID (plain http on the LAN)', () => {
        const original = crypto.randomUUID;
        crypto.randomUUID = undefined;
        try {
            const a = randomId();
            const b = randomId();
            expect(a).toMatch(UUID);
            expect(a).not.toBe(b);
        } finally {
            crypto.randomUUID = original;
        }
    });
});

describe('several tabs of one browser', () => {
    const NOW = 1_000_000;

    it('a hidden tab stays quiet while another tab shows the panel', () => {
        expect(otherTabVisible({ a: NOW - 1000, b: NOW - 15000 }, 'a', NOW)).toBe(true);
    });

    it('the last tab to go reports the panel inactive', () => {
        expect(otherTabVisible({ a: NOW }, 'a', NOW)).toBe(false);
        expect(otherTabVisible({}, 'a', NOW)).toBe(false);
        expect(otherTabVisible(null, 'a', NOW)).toBe(false);
    });

    it('ignores a tab that died without cleaning up', () => {
        expect(otherTabVisible({ b: NOW - TAB_STALE_MS }, 'a', NOW)).toBe(false);
    });
});
