import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { setTimezone } from '../../../lib/i18n';
import { bottomRow, clockLabel, etaRow, formatDuration, nextOrderLabel, percentDone } from './lineCardFormat';

// 2026-05-26 10:00 in Warsaw (UTC+2).
const NOW = Date.UTC(2026, 4, 26, 8, 0, 0);
const MIN = 60_000;

const withOrder = (extra = {}) => ({
    order: { id: 1, order_no: 'WO-1', product_code: 'AL-220', produced: 312, planned: 450, percent: 69 },
    eta: '2026-05-26T11:05:00Z', // 13:05 plant time
    eta_source: 'rate',
    late: false,
    more_count: 0,
    ...extra,
});

describe('lineCardFormat', () => {
    beforeEach(() => setTimezone('Europe/Warsaw'));
    afterEach(() => setTimezone(null));

    it('formats durations the mockup way', () => {
        expect(formatDuration(new Date(NOW - 34 * MIN).toISOString(), NOW)).toBe('34 min');
        expect(formatDuration(new Date(NOW - 76 * MIN).toISOString(), NOW)).toBe('1h 16m');
        expect(formatDuration(new Date(NOW - 180 * MIN).toISOString(), NOW)).toBe('3h');
        expect(formatDuration(null, NOW)).toBe('');
        expect(formatDuration('nope', NOW)).toBe('');
    });

    it('prints plant-time clock readings, with the date when not today', () => {
        expect(clockLabel('2026-05-26T11:05:00Z', NOW)).toBe('13:05');
        // 23:30 UTC on the 26th is already the 27th in Warsaw.
        expect(clockLabel('2026-05-26T23:30:00Z', NOW)).toContain('27');
        expect(clockLabel(null, NOW)).toBe('');
    });

    it('builds the ETA row', () => {
        expect(etaRow(withOrder(), NOW)).toEqual({ text: 'On track · ETA 13:05', tone: 'ok' });
        expect(etaRow(withOrder({ late: true }), NOW)).toEqual({ text: 'Late · ETA 13:05', tone: 'warn' });
        expect(etaRow(withOrder({ late: true, eta_source: 'plan' }), NOW)).toEqual({ text: 'Late · due 13:05', tone: 'warn' });
        expect(etaRow(withOrder({ eta: null, eta_source: null }), NOW)).toEqual({ text: 'No ETA', tone: 'mute' });
    });

    it('picks the bottom row by line state', () => {
        const since = new Date(NOW - 76 * MIN).toISOString();

        expect(bottomRow({ state: 'running' }, withOrder(), NOW).text).toBe('On track · ETA 13:05');
        expect(bottomRow({ state: 'down', reason: 'Maintenance', since }, withOrder(), NOW))
            .toEqual({ text: 'Maintenance · 1h 16m', tone: 'bad' });
        expect(bottomRow({ state: 'blocked', reason: 'Waiting for material', since: null }, withOrder(), NOW))
            .toEqual({ text: 'Waiting for material', tone: 'warn' });
        expect(bottomRow({ state: 'down', reason: null, since: null }, withOrder(), NOW).text).toBe('Down');
        expect(bottomRow({ state: 'idle', reason: null }, withOrder(), NOW)).toEqual({ text: 'On track · ETA 13:05', tone: 'mute' });
        expect(bottomRow({ state: 'running' }, { order: null }, NOW)).toEqual({ text: 'No active order', tone: 'mute' });
        expect(bottomRow({ state: 'idle' }, undefined, NOW).text).toBe('No active order');
    });

    it('computes percent without NaN', () => {
        expect(percentDone(312, 450)).toBe(69);
        expect(percentDone('312.00', '450.00')).toBe(69);
        expect(percentDone(10, 0)).toBeNull();
        expect(percentDone(null, null)).toBeNull();
        expect(percentDone(600, 450)).toBe(100);
    });

    it('labels the next order', () => {
        expect(nextOrderLabel({ planned_start_at: '2026-05-26T11:10:00Z', line_name: 'L1', product_code: 'AL-220', order_no: 'WO-9' }, NOW))
            .toBe('13:10 · L1 · AL-220');
        expect(nextOrderLabel({ planned_start_at: '2026-05-26T11:10:00Z', line_name: null, product_code: null, order_no: 'WO-9' }, NOW))
            .toBe('13:10 · WO-9');
    });
});
