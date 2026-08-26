import { describe, expect, it } from 'vitest';
import { dateTimeLocal, jsonColumn, timeOfDay } from './syncedRow';

/**
 * The list drawer edits the row the synced collection holds, and that snapshot
 * comes from a `DB::table()` query with no casts, accessors or date formatting
 * behind it. Every failure here is silent rather than loud, which is what makes
 * it worth pinning: a JSON string spreads into per-character keys, and a raw
 * datetime renders as an empty input rather than an error.
 */
describe('jsonColumn', () => {
    it('passes an already-cast value through', () => {
        const events = ['work_order.created'];
        expect(jsonColumn(events, [])).toBe(events);
    });

    it('parses the JSON string a synced row carries', () => {
        expect(jsonColumn('[1,2,3,4,5]', [])).toEqual([1, 2, 3, 4, 5]);
        expect(jsonColumn('{"colour":"red"}', {})).toEqual({ colour: 'red' });
    });

    it('keeps arrays mappable — the crew break-window crash', () => {
        expect(() => jsonColumn('[1,2]', []).map(Number)).not.toThrow();
        expect(jsonColumn('[1,2]', []).map(Number)).toEqual([1, 2]);
    });

    it('never yields per-character keys', () => {
        expect(jsonColumn('{"a":1}', {})).not.toHaveProperty('0');
    });

    it('falls back for missing, scalar and malformed values', () => {
        expect(jsonColumn(null, [])).toEqual([]);
        expect(jsonColumn(undefined, {})).toEqual({});
        expect(jsonColumn('"red"', [])).toEqual([]);
        expect(jsonColumn('7', {})).toEqual({});
        expect(jsonColumn('{not json', [])).toEqual([]);
    });
});

describe('dateTimeLocal', () => {
    it('converts a raw Postgres datetime to what the input accepts', () => {
        expect(dateTimeLocal('2026-08-26 10:04:57')).toBe('2026-08-26T10:04');
    });

    it('leaves an already-formatted value alone', () => {
        expect(dateTimeLocal('2026-08-26T10:04')).toBe('2026-08-26T10:04');
    });

    it('trims a serialized ISO timestamp to the minute', () => {
        expect(dateTimeLocal('2026-08-26T10:04:57.000000Z')).toBe('2026-08-26T10:04');
    });

    it('treats missing as empty', () => {
        expect(dateTimeLocal(null)).toBe('');
        expect(dateTimeLocal('')).toBe('');
    });
});

describe('timeOfDay', () => {
    it('drops the seconds a time input will not show', () => {
        expect(timeOfDay('06:30:00')).toBe('06:30');
        expect(timeOfDay('06:30')).toBe('06:30');
        expect(timeOfDay(null)).toBe('');
    });
});
