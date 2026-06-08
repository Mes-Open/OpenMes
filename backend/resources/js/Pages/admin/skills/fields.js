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
