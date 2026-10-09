import { describe, expect, it } from 'vitest';
import english from '../../../lang/en.json';
import chinese from '../../../lang/zh.json';
import { __, loadLocale } from './i18n';

describe('Simplified Chinese', () => {
    it('loads the catalog and falls back to English for missing strings', async () => {
        await loadLocale('zh');
        expect(__('Dashboard')).toBe('仪表盘');
        const missing = Object.keys(english).find((key) => !(key in chinese));
        expect(missing).toBeDefined();
        expect(__(missing)).toBe(missing);
    });

    it('contains only current English keys and preserves replacement tokens', () => {
        const tokens = (s) => (s.match(/(?<![\w]):[A-Za-z_][A-Za-z0-9_]*/g) ?? []).sort();
        for (const [key, value] of Object.entries(chinese)) {
            expect(Object.hasOwn(english, key), key).toBe(true);
            expect(typeof value, key).toBe('string');
            expect(value.length, key).toBeGreaterThan(0);
            expect(tokens(value), key).toEqual(tokens(key));
        }
    });
});
