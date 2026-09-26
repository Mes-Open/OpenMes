import { describe, it, expect } from 'vitest';
import { moduleFieldsFor, moduleFieldInitial, moduleFieldValue } from './moduleFields';

const HOOK = 'display.admin.users.form.fields';
const field = { name: 'module_example_code', type: 'text', label: 'Example field' };

describe('moduleFieldsFor', () => {
    it('answers empty on an install with no modules', () => {
        // The community path: the prop is {} and the form renders nothing extra.
        expect(moduleFieldsFor({}, HOOK)).toEqual([]);
        expect(moduleFieldsFor(undefined, HOOK)).toEqual([]);
    });

    it('answers empty for a hook point nobody contributed to', () => {
        expect(moduleFieldsFor({ [HOOK]: [field] }, 'display.admin.workers.form.fields')).toEqual([]);
    });

    it('drops a contribution with no name', () => {
        // Nothing to bind to and no error key to show under, so rendering it
        // would put an input on the page that cannot be saved.
        expect(moduleFieldsFor({ [HOOK]: [{ label: 'Nameless' }, field] }, HOOK)).toEqual([field]);
    });
});

describe('moduleFieldInitial', () => {
    it('seeds the form with the value the record holds', () => {
        // Without this an untouched field is never submitted, and the module
        // cannot tell "unchanged" from "cleared" on an edit.
        expect(moduleFieldInitial({ [HOOK]: [{ ...field, value: 'ABC123' }] }, HOOK))
            .toEqual({ module_example_code: 'ABC123' });
    });

    it('seeds a field with no value as empty, not undefined', () => {
        // undefined would make React treat the input as uncontrolled and warn
        // the moment somebody types into it.
        expect(moduleFieldInitial({ [HOOK]: [field] }, HOOK)).toEqual({ module_example_code: '' });
    });

    it('seeds a checkbox as false', () => {
        expect(moduleFieldInitial({ [HOOK]: [{ name: 'module_example_flag', type: 'checkbox' }] }, HOOK))
            .toEqual({ module_example_flag: false });
    });

    it('adds nothing on an install with no modules', () => {
        expect(moduleFieldInitial({}, HOOK)).toEqual({});
    });
});

describe('moduleFieldValue', () => {
    it('prefers what the form holds', () => {
        expect(moduleFieldValue({ ...field, value: 'OLD' }, { module_example_code: 'TYPED' })).toBe('TYPED');
    });

    it('keeps an emptied field empty', () => {
        // Clearing the box must not fall back to the stored value, or the field
        // would refill itself as the operator deletes it.
        expect(moduleFieldValue({ ...field, value: 'OLD' }, { module_example_code: '' })).toBe('');
    });

    it('falls back to the contributed value before the form holds anything', () => {
        expect(moduleFieldValue({ ...field, value: 'OLD' }, {})).toBe('OLD');
        expect(moduleFieldValue({ ...field, value: 'OLD' }, undefined)).toBe('OLD');
    });
});
