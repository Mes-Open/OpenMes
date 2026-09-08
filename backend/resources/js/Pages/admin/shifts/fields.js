import { __ } from '../../../lib/i18n';

export function shiftFields(lines) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'line_id',
            label: __('Line'),
            type: 'select',
            options: [
                { value: '', label: __('— Global (all lines) —') },
                ...lines.map((l) => ({ value: String(l.id), label: l.name })),
            ],
        },
        { name: 'start_time', label: __('Start Time'), type: 'time', required: true },
        { name: 'end_time', label: __('End Time'), type: 'time', required: true },
        { name: 'sort_order', label: __('Sort Order'), type: 'number' },
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
export function shiftInitial(record) {
    if (!record) {
        return { code: '', name: '', line_id: '', start_time: '', end_time: '', sort_order: 0, is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        line_id: record.line_id != null ? String(record.line_id) : '',
        start_time: (record.start_time ?? '').slice(0, 5),
        end_time: (record.end_time ?? '').slice(0, 5),
        sort_order: record.sort_order ?? 0,
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
