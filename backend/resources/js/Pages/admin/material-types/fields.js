import { __ } from '../../../lib/i18n';

// Built as a function (not a module constant) so labels are translated at render
// time, after the active locale chunk has loaded at bootstrap.
export function materialTypeFields() {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
    ];
}

/** One builder for the drawer and both standalone pages, so a blank field and a loaded one can't drift apart. */
export function materialTypeInitial(record) {
    if (!record) {
        return { code: '', name: '' };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
    };
}
