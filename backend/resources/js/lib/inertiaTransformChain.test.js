import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

// Inertia's useForm().transform() stores the callback and returns nothing, so
// `form.transform(fn).post(url)` throws "Cannot read properties of undefined
// (reading 'post')" and the form is never sent. That is how the two-factor
// challenge stopped submitting: every account with 2FA on was locked out of
// the web UI. Call transform() and then submit as a separate statement.

const ROOT = new URL('..', import.meta.url).pathname;

function sourceFiles(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return sourceFiles(path);
        return /\.(jsx?|tsx?)$/.test(name) && !/\.test\./.test(name) ? [path] : [];
    });
}

/** Offsets just past each `.transform(...)` call's closing parenthesis. */
function transformCallEnds(source) {
    const ends = [];
    const re = /\.transform\(/g;
    let match;
    while ((match = re.exec(source)) !== null) {
        let depth = 1;
        let i = match.index + match[0].length;
        while (i < source.length && depth > 0) {
            if (source[i] === '(') depth += 1;
            else if (source[i] === ')') depth -= 1;
            i += 1;
        }
        ends.push(i);
    }
    return ends;
}

describe('useForm().transform()', () => {
    it('is never chained, because it returns undefined', () => {
        const offenders = sourceFiles(ROOT).flatMap((file) => {
            const source = readFileSync(file, 'utf8');
            return transformCallEnds(source)
                .filter((end) => /^\s*\.\s*(post|put|patch|delete|get|submit)\s*\(/.test(source.slice(end)))
                .map((end) => `${relative(ROOT, file)}:${source.slice(0, end).split('\n').length}`);
        });

        expect(offenders).toEqual([]);
    });
});
