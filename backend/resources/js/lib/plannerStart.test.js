import { describe, expect, it } from 'vitest';
import { hourlyLanes, placementsOf, onMonthlyDay, weeklySlot, weeklyPlacements } from '../../../../packages/ui/src/lib/planner';

const order = {
    id: 1, line_id: 1, due_date: '2026-09-30', planned_start_at: '2026-09-18T07:30:00+02:00',
    planned_end_at: null, shift_number: null, end_date: null, placements: [],
};

describe('planned start and deadline', () => {
    it('uses start in coarse placement and leaves the deadline unchanged', () => {
        expect(placementsOf(order)[0].due_date).toBe('2026-09-18');
        expect(weeklySlot(order, 3)).toEqual({ date: '2026-09-18', shift: 1 });
        expect(onMonthlyDay(order, '2026-09-18')).toBe(true);
        expect(onMonthlyDay(order, '2026-09-30')).toBe(false);
        expect(order.due_date).toBe('2026-09-30');
    });
    it('places a start-only order at its actual hour', () => {
        const result = hourlyLanes([order], 1, '2026-09-18');
        expect(result.items[0].start).toBe(450);
        expect(result.items[0].placeholder).toBe(true);
        expect(hourlyLanes([order], 1, '2026-09-30').items).toHaveLength(0);
    });
    it('does not report fictional conflicts against start-only placeholder widths', () => {
        const planned = { ...order, id: 2, planned_end_at: '2026-09-18T09:00:00+02:00' };
        expect(hourlyLanes([order, planned], 1, '2026-09-18').items.every(item => !item.conflict)).toBe(true);
    });
    it('keeps legacy placement for orders without a start', () => {
        const legacy = { ...order, planned_start_at: null };
        expect(placementsOf(legacy)[0].due_date).toBe('2026-09-30');
        expect(weeklySlot(legacy, 3).date).toBe('2026-09-30');
    });
    it('keeps additional segments independent', () => {
        const multi = { ...order, placements: [{ id: 2, line_id: 2, due_date: '2026-09-19' }] };
        expect(placementsOf(multi)[1].due_date).toBe('2026-09-19');
        expect(onMonthlyDay(multi, '2026-09-19')).toBe(true);
    });
});

describe('midnight boundary', () => {
    const midnight = { ...order, planned_start_at: '2026-09-18T16:00:00+02:00', planned_end_at: '2026-09-19T00:00:00+02:00' };
    it('keeps a block ending at midnight draggable on its starting day', () => {
        expect(hourlyLanes([midnight], 1, '2026-09-18').items[0]).toMatchObject({ start: 960, end: 1440, spansOutside: false });
    });
    it('does not draw an empty continuation on the following day', () => {
        expect(hourlyLanes([midnight], 1, '2026-09-19').items).toHaveLength(0);
    });
    it('still protects real cross-day intervals', () => {
        const longer = { ...midnight, planned_end_at: '2026-09-19T01:00:00+02:00' };
        expect(hourlyLanes([longer], 1, '2026-09-18').items[0].spansOutside).toBe(true);
        expect(hourlyLanes([longer], 1, '2026-09-19').items[0]).toMatchObject({ start: 0, end: 60, spansOutside: true });
    });
});

describe('shift-based ranges in daily planning', () => {
    const spanning = { ...order, planned_start_at: '2026-09-16T06:00:00', planned_end_at: null, end_date: '2026-09-17', end_shift_number: 1 };
    const shifts = [{ start_time: '06:00:00', end_time: '14:00:00' }];
    it('covers both dates and shows the full shift-based range', () => {
        const first = hourlyLanes([spanning], 1, '2026-09-16', shifts).items[0];
        const second = hourlyLanes([spanning], 1, '2026-09-17', shifts).items[0];
        expect(first).toMatchObject({ start: 360, end: 1440, placeholder: false, rangeStart: '2026-09-16T06:00:00', rangeEnd: '2026-09-17T14:00:00' });
        expect(second).toMatchObject({ start: 0, end: 840, spansOutside: true });
        expect(hourlyLanes([spanning], 1, '2026-09-18', shifts).items).toHaveLength(0);
    });
    it('covers the full inclusive end day when no shifts are configured', () => {
        expect(hourlyLanes([spanning], 1, '2026-09-17').items[0]).toMatchObject({ start: 0, end: 1440 });
    });
    it('handles overnight end shifts and extra placements', () => {
        const extra = { ...order, placements: [{ id: 9, line_id: 2, due_date: '2026-09-16', end_date: '2026-09-17', shift_number: 1, end_shift_number: 1 }] };
        expect(hourlyLanes([extra], 2, '2026-09-18', [{ start_time: '22:00:00', end_time: '06:00:00' }]).items[0]).toMatchObject({ start: 0, end: 360, placementKey: 9 });
    });
});

describe('weekly conflicts use the same time ranges as daily', () => {
    const days = [{ date: '2026-09-16' }, { date: '2026-09-17' }];
    const first = { ...order, planned_start_at: '2026-09-16T08:00:00', planned_end_at: '2026-09-16T10:00:00' };
    it('marks overlapping orders but not back-to-back orders sharing a day', () => {
        const second = { ...first, id: 2, planned_start_at: '2026-09-16T09:00:00', planned_end_at: '2026-09-16T11:00:00' };
        expect(weeklyPlacements([first, second], days, 1, 1).items.every(i => i.conflict)).toBe(true);
        second.planned_start_at = '2026-09-16T10:00:00';
        expect(weeklyPlacements([first, second], days, 1, 1).items.some(i => i.conflict)).toBe(false);
    });
    it('detects a timed order inside a two-day shift-based order', () => {
        const span = { ...first, id: 2, planned_end_at: null, end_date: '2026-09-17', end_shift_number: 1 };
        expect(weeklyPlacements([first, span], days, 1, 1).items.every(i => i.conflict)).toBe(true);
    });
    it('does not report conflicts on different lines', () => {
        const other = { ...first, id: 2, line_id: 2 };
        expect(weeklyPlacements([first, other], days, 1, 1).items.some(i => i.conflict)).toBe(false);
    });
});

it('projects a single weekly slot through the end of its day', () => {
    const scheduled = { ...order, planned_start_at: '2026-09-17T00:00:00', shift_number: 1 };
    expect(hourlyLanes([scheduled], 1, '2026-09-17', [{ start_time: '00:00:00', end_time: '00:00:00' }]).items[0])
        .toMatchObject({ start: 0, end: 1440, placeholder: false, rangeEnd: '2026-09-18T00:00:00' });
});
