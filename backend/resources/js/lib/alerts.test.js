import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { countAlerts, isOverdue, TERMINAL_WO_STATUSES } from './alerts';
import { todayInTimezone } from './i18n';

// The same rows AlertCountTimezoneTest feeds AlertController::totalCount, so the
// badge and the server are pinned to one expected number.
const fixture = JSON.parse(
    readFileSync(new URL('../../../tests/fixtures/alert-count-cases.json', import.meta.url), 'utf8'),
);

describe('isOverdue', () => {
    const today = '2026-06-15';

    it('is false for an order due today', () => {
        expect(isOverdue({ due_date: '2026-06-15', status: 'IN_PROGRESS' }, today)).toBe(false);
    });

    it('is true for an order due yesterday', () => {
        expect(isOverdue({ due_date: '2026-06-14', status: 'PENDING' }, today)).toBe(true);
    });

    it('ignores the time part of a timestamp', () => {
        expect(isOverdue({ due_date: '2026-06-15 00:00:00', status: 'PENDING' }, today)).toBe(false);
        expect(isOverdue({ due_date: '2026-06-14 23:59:59', status: 'PENDING' }, today)).toBe(true);
    });

    it('is false without a due date', () => {
        expect(isOverdue({ due_date: null, status: 'PENDING' }, today)).toBe(false);
    });

    it.each(TERMINAL_WO_STATUSES)('is false for a %s order', (status) => {
        expect(isOverdue({ due_date: '2026-06-01', status }, today)).toBe(false);
    });
});

describe('countAlerts matches the server fixture', () => {
    it.each(fixture.cases)('$name', (c) => {
        const today = todayInTimezone(new Date(c.now), c.timezone);
        expect(countAlerts({ issues: c.issues, orders: c.orders, today })).toEqual(c.expected);
    });
});
