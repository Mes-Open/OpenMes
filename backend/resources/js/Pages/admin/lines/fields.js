import { __ } from '../../../lib/i18n';

export function lineFields(areas) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'area_id', label: __('Area'), type: 'select',
            options: [{ value: '', label: __('— None —') }, ...areas.map((a) => ({ value: String(a.id), label: a.name }))],
        },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function lineInitial(record) {
    if (!record) {
        return { code: '', name: '', area_id: '', description: '', is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        area_id: record.area_id != null ? String(record.area_id) : '',
        description: record.description ?? '',
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
