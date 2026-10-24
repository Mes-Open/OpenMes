import { describe, expect, it } from 'vitest';
import { isOverviewReload } from './useOverviewRefresh';

describe('isOverviewReload', () => {
    it('recognises the hook\'s own background reload', () => {
        expect(isOverviewReload({ async: true, only: ['overview'] })).toBe(true);
    });

    it('treats every other visit as the user\'s', () => {
        expect(isOverviewReload({ async: false, only: ['overview'] })).toBe(false);
        expect(isOverviewReload({ async: true, only: [] })).toBe(false);
        expect(isOverviewReload({ async: true, only: ['overview', 'scrapStats'] })).toBe(false);
        expect(isOverviewReload({ async: true })).toBe(false);
        expect(isOverviewReload(undefined)).toBe(false);
    });
});
