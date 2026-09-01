import { describe, expect, it } from 'vitest';
import { customFieldValues } from './customFieldForm';

/**
 * The list drawer edits the row the synced collection holds, and that snapshot
 * comes from a `DB::table()` query with no Eloquent casts — so `custom_fields`
 * is the raw jsonb string, where the standalone edit page gets an object. The
 * failure mode is silent: spreading a string produces per-character keys that
 * the form then posts back over the record's real custom fields.
 */
describe('customFieldValues', () => {
    it('passes an already-cast object through', () => {
        const values = { colour: 'red', width: 3 };
        expect(customFieldValues(values)).toBe(values);
    });

    it('parses the JSON string a synced row carries', () => {
        expect(customFieldValues('{"colour":"red","width":3}')).toEqual({ colour: 'red', width: 3 });
    });

    it('treats a missing value as an empty map', () => {
        expect(customFieldValues(null)).toEqual({});
        expect(customFieldValues(undefined)).toEqual({});
    });

    it('never yields per-character keys', () => {
        // What a bare spread of the string would have produced.
        expect(customFieldValues('{"a":1}')).not.toHaveProperty('0');
    });

    it('refuses valid JSON that is not a value map', () => {
        expect(customFieldValues('"red"')).toEqual({});
        expect(customFieldValues('7')).toEqual({});
        expect(customFieldValues('null')).toEqual({});
    });

    it('falls back to empty on malformed JSON rather than throwing', () => {
        expect(customFieldValues('{not json')).toEqual({});
    });
});
