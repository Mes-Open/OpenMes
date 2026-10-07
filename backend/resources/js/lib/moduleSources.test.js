import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { moduleSourcesCss } from '@openmes/module';

// Tailwind skips modules/ because .gitignore lists it, and only reads an
// ignored directory that is named outright -- so the build writes one @source
// per module that ships pages. This is the part that decides which.
describe('module style sources', () => {
    let root;

    beforeEach(() => {
        root = mkdtempSync(join(tmpdir(), 'openmes-modules-'));
        mkdirSync(join(root, 'resources/css'), { recursive: true });
    });

    afterEach(() => rmSync(root, { recursive: true, force: true }));

    const css = () => moduleSourcesCss(join(root, 'modules'), join(root, 'resources/css'));
    const sources = () => css().split('\n').filter((line) => line.startsWith('@source'));

    it('names each module that ships pages, relative to the stylesheet', () => {
        mkdirSync(join(root, 'modules/Packaging/resources/js/Pages'), { recursive: true });
        mkdirSync(join(root, 'modules/Billing/resources/js/Pages'), { recursive: true });

        expect(sources()).toEqual([
            '@source "../../modules/Billing/resources/js";',
            '@source "../../modules/Packaging/resources/js";',
        ]);
    });

    it('leaves out a module with no frontend', () => {
        mkdirSync(join(root, 'modules/OrderPinger/Providers'), { recursive: true });
        writeFileSync(join(root, 'modules/README.md'), '');

        expect(sources()).toEqual([]);
    });

    it('is still a valid stylesheet when nothing is installed', () => {
        // app.css imports the file unconditionally, so it has to exist and
        // parse even on an installation without a modules directory at all.
        expect(sources()).toEqual([]);
        expect(css().trim().startsWith('/*')).toBe(true);
        expect(css().trim().endsWith('*/')).toBe(true);
    });
});
