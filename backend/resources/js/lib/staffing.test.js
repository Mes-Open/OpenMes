import { describe, expect, it } from 'vitest';
import { shortName, staffingGrid, staffingTone, weekOf } from './staffing';

describe('staffingTone', () => {
    it('reads available against needed', () => {
        expect(staffingTone({ tracked: true, available: 4, needed: 3 })).toBe('spare');
        expect(staffingTone({ tracked: true, available: 3, needed: 3 })).toBe('enough');
        expect(staffingTone({ tracked: true, available: 2, needed: 3 })).toBe('short');
    });
    it('is idle where nothing is planned, untracked where nobody is assigned', () => {
        expect(staffingTone({ tracked: true, available: 2, needed: 0 })).toBe('idle');
        expect(staffingTone({ tracked: false, available: null, needed: 2 })).toBe('untracked');
        expect(staffingTone(null)).toBe('untracked');
    });
});

describe('shortName and weekOf', () => {
    it('abbreviates the last name', () => {
        expect(shortName('Jan Kowalski')).toBe('Jan K.');
        expect(shortName('Anna Maria Zielińska')).toBe('Anna Z.');
        expect(shortName('Cher')).toBe('Cher');
        expect(shortName(null)).toBe('');
    });
    it('finds the Monday of a date', () => {
        expect(weekOf('2026-11-25')).toBe('2026-11-23');
        expect(weekOf('2026-11-23')).toBe('2026-11-23');
        expect(weekOf('2026-11-29')).toBe('2026-11-23');
    });
});

describe('staffingGrid', () => {
    const row = (line, date, start, extra = {}) => ({
        line_id: line, line_name: line === 1 ? 'CNC Turning' : 'Assembly A', tracked: true, date, shift_name: 'Day',
        start: `${date}T${start}:00+00:00`, end: `${date}T14:00:00+00:00`, available: 3, needed: 2, ...extra,
    });

    it('makes one row per line and one column per day and shift, by week', () => {
        const grid = staffingGrid([
            row(2, '2026-11-23', '06'), row(1, '2026-11-23', '06'),
            row(1, '2026-11-24', '06'), row(1, '2026-11-30', '06'),
        ]);
        expect(grid.lines.map((l) => l.name)).toEqual(['Assembly A', 'CNC Turning']);
        expect(grid.weeks).toEqual(['2026-11-23', '2026-11-30']);
        expect(grid.columns['2026-11-23'].map((c) => c.date)).toEqual(['2026-11-23', '2026-11-24']);
        const col = grid.columns['2026-11-23'][0].key;
        expect(grid.cell(1, col).available).toBe(3);
        expect(grid.cell(2, grid.columns['2026-11-23'][1].key)).toBeUndefined();
    });

    it('keeps an untracked line as one row', () => {
        const grid = staffingGrid([row(1, '2026-11-23', '06', { tracked: false, available: null })]);
        expect(grid.lines[0].tracked).toBe(false);
    });
});
