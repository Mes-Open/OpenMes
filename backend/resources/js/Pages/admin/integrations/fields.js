import { __ } from '../../../lib/i18n';

export const INTEGRATION_FIELDS = [
    {
        name: 'system_type',
        label: __('System Type'),
        required: true,
        type: 'select',
        options: [
            { value: '', label: __('Select...') },
            { value: 'subiekt_gt', label: 'Subiekt GT' },
            { value: 'subiekt_nexo', label: 'Subiekt nexo' },
            { value: 'wms', label: 'WMS' },
            { value: 'erp_custom', label: __('Custom ERP') },
        ],
    },
    { name: 'system_name', label: __('System Name'), required: true },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function integrationInitial(record) {
    if (!record) {
        return { system_type: '', system_name: '', is_active: true };
    }

    return {
        system_type: record.system_type ?? '',
        system_name: record.system_name ?? '',
        is_active: !!record.is_active,
    };
}
