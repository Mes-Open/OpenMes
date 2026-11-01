import { describe, expect, it, vi } from 'vitest';

// Only the column configs are under test; the badge components never render.
vi.mock('@openmes/ui', () => ({ RANGE_PRESETS: [], SegmentedProgress: () => null, StatusBadge: () => null }));

import { buildColumnDefs } from '../../../components/resourceColumns';
import { woColumns } from './columns';
import { WO_STATUSES, woStatusLabel } from './fields';

// Search, sort and the column filters all run on a column's accessor, so it has
// to return the text the cell shows. A raw enum or boolean there means typing
// what the user reads finds nothing, and the filter offers codes instead.
const defs = buildColumnDefs(woColumns());
const def = (id) => defs.find((d) => d.id === id);

describe('work-order list columns', () => {
    it('searches and filters the status by its label, not the enum code', () => {
        const status = def('status');
        expect(status.accessorFn({ status: 'IN_PROGRESS' })).toBe(woStatusLabel('IN_PROGRESS'));
        // The select filter compares the accessor with each option value.
        expect(status.meta.options).toEqual(WO_STATUSES.map(woStatusLabel));
        expect(status.meta.optionLabel).toBeUndefined();
    });

    it('searches and filters "Counted" by Yes/No, not the boolean', () => {
        const counted = def('customer_totals_counted');
        expect(counted.accessorFn({ customer_totals_counted: true })).toBe('Yes');
        expect(counted.accessorFn({ customer_totals_counted: false })).toBe('No');
        expect(counted.meta.optionLabel).toBeUndefined();
    });
});
