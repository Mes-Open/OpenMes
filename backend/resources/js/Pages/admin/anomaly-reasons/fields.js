import { __ } from '../../../lib/i18n';

export const ANOMALY_REASON_FIELDS = [
    { name: 'code', label: __('Code'), required: true },
    { name: 'name', label: __('Name'), required: true },
    { name: 'category', label: __('Category') },
    { name: 'description', label: __('Description'), type: 'textarea' },
    { name: 'is_active', label: __('Active'), type: 'checkbox' },
];

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function anomalyReasonInitial(record) {
    if (!record) {
        return { code: '', name: '', category: '', description: '', is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        category: record.category ?? '',
        description: record.description ?? '',
        is_active: !!record.is_active,
    };
}
