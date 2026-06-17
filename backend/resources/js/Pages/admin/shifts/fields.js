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
