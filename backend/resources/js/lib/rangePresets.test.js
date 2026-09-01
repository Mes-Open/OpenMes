import { describe, expect, it } from 'vitest';

// Deep import rather than the `@openmes/ui` barrel: the barrel is twin-platform
// source (index.web.jsx / index.native.tsx) that needs Vite's extension order
// and the React plugin, neither of which the standalone vitest config carries.
// This module is plain JS on purpose — that is why the arithmetic lives there.
import {
    isoWeekEnd, isoWeekStart, matchPreset, RANGE_PRESETS, shiftDays, shiftMonths, withYearMonth,
} from '@openmes/ui/src/lib/rangePresets.js';

/**
 * The date-range filter's preset chips.
 *
 * These resolve against whatever day the reader happens to click on, so the
 * cases that matter are the ones a manual test never lands on: the first of a
 * month, the last of a quarter, a Monday, a Sunday, a leap day, New Year's Eve.
 * Every case below pins a real anchor date and both ends of the range.
 */

const resolve = (key, anchor) => RANGE_PRESETS.find((p) => p.key === key).resolve(anchor);
/** month is 1-based here so the cases read like the dates they describe. */
const on = (y, m, d) => new Date(y, m - 1, d);

describe('range presets', () => {
    it('has a unique key and a label for every chip', () => {
        const keys = RANGE_PRESETS.map((p) => p.key);
        expect(new Set(keys).size).toBe(keys.length);
        expect(RANGE_PRESETS.every((p) => typeof p.label === 'string' && p.label.length > 0)).toBe(true);
    });

    it('never returns a range whose end precedes its start', () => {
        // Across a year of anchors, so a bad offset can't hide in one month.
        for (let day = 1; day <= 365; day++) {
            const anchor = new Date(2026, 0, day);
            for (const preset of RANGE_PRESETS) {
                const { from, to } = preset.resolve(anchor);
                // Named so a failure says which chip and which anchor, not just "false".
                expect(`${preset.key} @ ${anchor.toDateString()}: ${from} <= ${to}`)
                    .toBe(`${preset.key} @ ${anchor.toDateString()}: ${from} <= ${from <= to ? to : 'BROKEN'}`);
            }
        }
    });

    describe('days', () => {
        it('today and yesterday are single days', () => {
            expect(resolve('today', on(2026, 8, 21))).toEqual({ from: '2026-08-21', to: '2026-08-21' });
            expect(resolve('yesterday', on(2026, 8, 21))).toEqual({ from: '2026-08-20', to: '2026-08-20' });
        });

        it('yesterday crosses a month boundary', () => {
            expect(resolve('yesterday', on(2026, 3, 1))).toEqual({ from: '2026-02-28', to: '2026-02-28' });
        });

        it('yesterday crosses a year boundary', () => {
            expect(resolve('yesterday', on(2026, 1, 1))).toEqual({ from: '2025-12-31', to: '2025-12-31' });
        });

        it('last 7 days is the anchor and the six before it', () => {
            expect(resolve('last_7_days', on(2026, 8, 21))).toEqual({ from: '2026-08-15', to: '2026-08-21' });
        });

        it('last 30 days is the anchor and the 29 before it', () => {
            expect(resolve('last_30_days', on(2026, 8, 21))).toEqual({ from: '2026-07-23', to: '2026-08-21' });
        });
    });

    describe('weeks — Monday-first, matching the grid', () => {
        // 2026-08-21 is a Friday; its week runs Mon 17 → Sun 23.
        it('this week from midweek', () => {
            expect(resolve('this_week', on(2026, 8, 21))).toEqual({ from: '2026-08-17', to: '2026-08-23' });
        });

        it('a Monday anchor starts its own week', () => {
            expect(resolve('this_week', on(2026, 8, 17))).toEqual({ from: '2026-08-17', to: '2026-08-23' });
        });

        // The case a Sunday-first implementation gets wrong: Sunday belongs to
        // the week that started six days ago, not the one starting tomorrow.
        it('a Sunday anchor closes the week it is in', () => {
            expect(resolve('this_week', on(2026, 8, 23))).toEqual({ from: '2026-08-17', to: '2026-08-23' });
        });

        it('last week is the seven days before this one', () => {
            expect(resolve('last_week', on(2026, 8, 21))).toEqual({ from: '2026-08-10', to: '2026-08-16' });
        });

        it('last week reaches back across the new year', () => {
            // Fri 2026-01-02 sits in the week of Mon 2025-12-29.
            expect(resolve('last_week', on(2026, 1, 2))).toEqual({ from: '2025-12-22', to: '2025-12-28' });
        });
    });

    describe('months — whole months, not month-to-date', () => {
        it('this month spans the first to the last', () => {
            expect(resolve('this_month', on(2026, 8, 21))).toEqual({ from: '2026-08-01', to: '2026-08-31' });
        });

        it('a 30-day month ends on the 30th', () => {
            expect(resolve('this_month', on(2026, 4, 15))).toEqual({ from: '2026-04-01', to: '2026-04-30' });
        });

        it('February is 28 days in a common year', () => {
            expect(resolve('this_month', on(2026, 2, 15))).toEqual({ from: '2026-02-01', to: '2026-02-28' });
        });

        it('February is 29 days in a leap year', () => {
            expect(resolve('this_month', on(2028, 2, 15))).toEqual({ from: '2028-02-01', to: '2028-02-29' });
        });

        it('last month from March lands on February, not day 31 of it', () => {
            expect(resolve('last_month', on(2026, 3, 31))).toEqual({ from: '2026-02-01', to: '2026-02-28' });
        });

        it('last month from January is the previous December', () => {
            expect(resolve('last_month', on(2026, 1, 10))).toEqual({ from: '2025-12-01', to: '2025-12-31' });
        });
    });

    describe('quarters', () => {
        it.each([
            [on(2026, 1, 1), '2026-01-01', '2026-03-31'],
            [on(2026, 5, 15), '2026-04-01', '2026-06-30'],
            [on(2026, 8, 21), '2026-07-01', '2026-09-30'],
            [on(2026, 12, 31), '2026-10-01', '2026-12-31'],
        ])('this quarter for %s', (anchor, from, to) => {
            expect(resolve('this_quarter', anchor)).toEqual({ from, to });
        });

        it('last quarter from Q1 crosses into the previous year', () => {
            expect(resolve('last_quarter', on(2026, 2, 10))).toEqual({ from: '2025-10-01', to: '2025-12-31' });
        });

        it('last quarter from Q3 is Q2', () => {
            expect(resolve('last_quarter', on(2026, 8, 21))).toEqual({ from: '2026-04-01', to: '2026-06-30' });
        });

        it('last 4 quarters ends with the current one', () => {
            // Q3 2026 back through Q4 2025 — four quarters including this one.
            expect(resolve('last_4_quarters', on(2026, 8, 21))).toEqual({ from: '2025-10-01', to: '2026-09-30' });
        });
    });

    describe('years', () => {
        it('this year is the whole calendar year', () => {
            expect(resolve('this_year', on(2026, 8, 21))).toEqual({ from: '2026-01-01', to: '2026-12-31' });
        });

        it('last year is the whole previous one', () => {
            expect(resolve('last_year', on(2026, 1, 1))).toEqual({ from: '2025-01-01', to: '2025-12-31' });
        });

        it('last 12 months ends with the current month, not today', () => {
            expect(resolve('last_12_months', on(2026, 8, 21))).toEqual({ from: '2025-09-01', to: '2026-08-31' });
        });

        it('last 12 months from January reaches back 11 months', () => {
            expect(resolve('last_12_months', on(2026, 1, 15))).toEqual({ from: '2025-02-01', to: '2026-01-31' });
        });
    });
});

describe('matchPreset', () => {
    const today = '2026-08-21';

    it('names the preset a range came from', () => {
        expect(matchPreset({ from: '2026-08-01', to: '2026-08-31' }, today)).toBe('this_month');
        expect(matchPreset({ from: '2026-08-21', to: '2026-08-21' }, today)).toBe('today');
        expect(matchPreset({ from: '2026-07-01', to: '2026-09-30' }, today)).toBe('this_quarter');
    });

    it('returns null for a range no chip describes', () => {
        expect(matchPreset({ from: '2026-08-03', to: '2026-08-19' }, today)).toBeNull();
    });

    it('returns null while only one end is picked', () => {
        expect(matchPreset({ from: '2026-08-12' }, today)).toBeNull();
        expect(matchPreset({ to: '2026-08-12' }, today)).toBeNull();
        expect(matchPreset(undefined, today)).toBeNull();
    });

    it('every preset round-trips through its own match', () => {
        for (const preset of RANGE_PRESETS) {
            expect(matchPreset(preset.resolve(new Date(2026, 7, 21)), today)).toBe(preset.key);
        }
    });
});

/**
 * The arithmetic behind the calendar grid's keyboard navigation.
 *
 * Arrow keys and PageUp/PageDown run this on every press, so the cases worth
 * pinning are the ones a hand-test never reaches: stepping off the end of a
 * month, off the end of a year, into February from a 31-day month, and into
 * February of a leap year.
 */
describe('grid navigation helpers', () => {
    describe('shiftDays', () => {
        it('steps within a month', () => {
            expect(shiftDays('2026-08-12', 1)).toBe('2026-08-13');
            expect(shiftDays('2026-08-12', -1)).toBe('2026-08-11');
            expect(shiftDays('2026-08-12', 7)).toBe('2026-08-19');
            expect(shiftDays('2026-08-12', -7)).toBe('2026-08-05');
        });

        it('rolls over month and year boundaries', () => {
            expect(shiftDays('2026-08-31', 1)).toBe('2026-09-01');
            expect(shiftDays('2026-09-01', -1)).toBe('2026-08-31');
            expect(shiftDays('2026-12-31', 1)).toBe('2027-01-01');
            expect(shiftDays('2026-01-01', -1)).toBe('2025-12-31');
        });

        it('crosses February correctly on and off a leap year', () => {
            expect(shiftDays('2024-02-28', 1)).toBe('2024-02-29');
            expect(shiftDays('2024-02-29', 1)).toBe('2024-03-01');
            expect(shiftDays('2026-02-28', 1)).toBe('2026-03-01');
        });

        it('returns null for a value the grid could not parse', () => {
            expect(shiftDays(null, 1)).toBeNull();
            expect(shiftDays('', 1)).toBeNull();
        });
    });

    describe('isoWeekStart / isoWeekEnd', () => {
        // The grid is Monday-first, so Home/End have to land on Mon and Sun —
        // not the Sunday/Saturday a US-default week would give.
        it('bracket the Monday-first week', () => {
            // 2026-08-12 is a Wednesday.
            expect(isoWeekStart('2026-08-12')).toBe('2026-08-10');
            expect(isoWeekEnd('2026-08-12')).toBe('2026-08-16');
        });

        it('treat Monday and Sunday as the ends of the same week', () => {
            expect(isoWeekStart('2026-08-10')).toBe('2026-08-10');
            expect(isoWeekEnd('2026-08-10')).toBe('2026-08-16');
            expect(isoWeekStart('2026-08-16')).toBe('2026-08-10');
            expect(isoWeekEnd('2026-08-16')).toBe('2026-08-16');
        });

        it('reach back into the previous month and year', () => {
            // 2026-01-01 is a Thursday.
            expect(isoWeekStart('2026-01-01')).toBe('2025-12-29');
            expect(isoWeekEnd('2026-01-01')).toBe('2026-01-04');
        });
    });

    describe('shiftMonths', () => {
        it('keeps the day number where the target month is long enough', () => {
            expect(shiftMonths('2026-08-12', 1)).toBe('2026-09-12');
            expect(shiftMonths('2026-08-12', -1)).toBe('2026-07-12');
            expect(shiftMonths('2026-08-12', 12)).toBe('2027-08-12');
            expect(shiftMonths('2026-08-12', -12)).toBe('2025-08-12');
        });

        it('clamps rather than overflowing into the next month', () => {
            // The bug this guards: new Date(2026, 1, 31) is 3 March.
            expect(shiftMonths('2026-03-31', -1)).toBe('2026-02-28');
            expect(shiftMonths('2026-01-31', 1)).toBe('2026-02-28');
            expect(shiftMonths('2026-08-31', 1)).toBe('2026-09-30');
            expect(shiftMonths('2024-01-31', 1)).toBe('2024-02-29');
        });

        it('rolls the year over', () => {
            expect(shiftMonths('2026-12-15', 1)).toBe('2027-01-15');
            expect(shiftMonths('2026-01-15', -1)).toBe('2025-12-15');
        });

        it('is reversible except where it clamped', () => {
            expect(shiftMonths(shiftMonths('2026-08-12', 1), -1)).toBe('2026-08-12');
            expect(shiftMonths(shiftMonths('2026-03-31', -1), 1)).toBe('2026-03-28');
        });
    });

    describe('withYearMonth', () => {
        it('moves to an arbitrary month, clamping the day', () => {
            expect(withYearMonth('2026-08-12', 2027, 0)).toBe('2027-01-12');
            expect(withYearMonth('2026-08-31', 2026, 1)).toBe('2026-02-28');
            expect(withYearMonth('2026-08-31', 2024, 1)).toBe('2024-02-29');
        });

        it('returns null for an unparseable day', () => {
            expect(withYearMonth(null, 2026, 0)).toBeNull();
        });
    });
});
