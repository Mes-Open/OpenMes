import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { formatDate, setTimezone } from './i18n';

// formatDate() keeps a calendar date ("2026-11-16") on the day it names. Wrapped
// in `new Date(…)` first, the same string becomes midnight UTC, and in a plant
// west of Greenwich that is the previous evening — the planner's range read
// "15/11 – 21/11" over columns Mon 16 … Sun 22. Pass the string itself.

const ROOT = new URL('..', import.meta.url).pathname;

function sourceFiles(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return sourceFiles(path);
        return /\.(jsx?|tsx?)$/.test(name) && !/\.test\./.test(name) ? [path] : [];
    });
}

describe('formatDate callers', () => {
    afterEach(() => setTimezone(undefined));

    it('shifts a wrapped calendar date west of UTC, which is why callers must not wrap', () => {
        setTimezone('America/New_York');
        const opts = { day: '2-digit', month: '2-digit' };

        expect(formatDate('2026-11-16', opts)).toBe('16/11');
        expect(formatDate(new Date('2026-11-16'), opts)).toBe('15/11');
    });

    it('never wrap a single value in new Date()', () => {
        // A one-argument `new Date(x)` — the multi-argument local constructor
        // (`new Date(y, m, d, 12)`) builds a day on purpose and is fine.
        const offenders = sourceFiles(ROOT).flatMap((file) => {
            const source = readFileSync(file, 'utf8');
            return [...source.matchAll(/formatDate\(\s*new Date\(([^(),]+)\)/g)]
                .map((m) => `${relative(ROOT, file)}:${source.slice(0, m.index).split('\n').length}`);
        });

        expect(offenders).toEqual([]);
    });
});
