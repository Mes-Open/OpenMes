import { describe, expect, it } from 'vitest';
import { downtimeRows } from './DowntimeSection';

const reason = (id, minutes, kind = 'unplanned') => ({ id, name: `R${id}`, kind, minutes, lines: [], count: 1, open: false });

describe('downtimeRows', () => {
    it('ranks Unclassified among the reasons by its minutes', () => {
        const rows = downtimeRows({
            reasons: [reason(1, 40), reason(2, 0, 'changeover')],
            unclassified: { minutes: 191, lines: ['BAKE'], count: 4, open: true, workstation_id: 7 },
        });

        expect(rows.map((r) => r.key)).toEqual(['unclassified', 'reason-1', 'reason-2']);
        expect(rows[0].kind).toBe('unclassified');
    });

    it('keeps the server order among equal minutes, Unclassified last', () => {
        const rows = downtimeRows({
            reasons: [reason(1, 30), reason(2, 30)],
            unclassified: { minutes: 30, lines: [], count: 1, open: false, workstation_id: null },
        });

        expect(rows.map((r) => r.key)).toEqual(['reason-1', 'reason-2', 'unclassified']);
    });

    it('is empty without data', () => {
        expect(downtimeRows(null)).toEqual([]);
        expect(downtimeRows({ reasons: [], unclassified: null })).toEqual([]);
    });
});
