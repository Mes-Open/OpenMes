import { __ } from '../../../lib/i18n';

// Built as a function (not a module constant) so labels are translated at render
// time, after the active locale chunk has loaded at bootstrap.
export function skillFields() {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
    ];
}

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function skillInitial(record) {
    if (!record) {
        return { code: '', name: '', description: '' };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        description: record.description ?? '',
    };
}
