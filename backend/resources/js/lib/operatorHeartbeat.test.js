import { describe, expect, it } from 'vitest';
import { randomId } from './operatorHeartbeat';

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
