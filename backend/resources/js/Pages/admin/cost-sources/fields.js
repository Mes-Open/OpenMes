import { __ } from '../../../lib/i18n';

export const COST_SOURCE_FIELDS = [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    { name: 'description', label: __('Description'), type: 'textarea' },
    { name: 'unit_cost', label: __('Unit Cost'), type: 'number' },
    { name: 'unit', label: __('Unit') },
    { name: 'currency', label: __('Currency') },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function costSourceInitial(record) {
    if (!record) {
        return { code: '', name: '', description: '', unit_cost: '', unit: '', currency: '', is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        description: record.description ?? '',
        unit_cost: record.unit_cost ?? '',
        unit: record.unit ?? '',
        currency: record.currency ?? '',
        is_active: !!record.is_active,
    };
}
