import { __ } from '../../../lib/i18n';

// ISO weekdays: 1 = Mon … 7 = Sun (matches the backend days_of_week).
export function getDayOptions() {
    return [
        { value: 1, label: __('Mon') },
        { value: 2, label: __('Tue') },
        { value: 3, label: __('Wed') },
        { value: 4, label: __('Thu') },
        { value: 5, label: __('Fri') },
        { value: 6, label: __('Sat') },
        { value: 7, label: __('Sun') },
    ];
}

/** Render a days_of_week array as e.g. "Mon, Tue, Wed". */
export function formatDays(days = []) {
    const dayLabels = Object.fromEntries(getDayOptions().map((d) => [d.value, d.label]));
    return [...days]
        .map(Number)
        .sort((a, b) => a - b)
        .map((d) => dayLabels[d] ?? d)
        .join(', ');
}

export function crewBreakWindowFields(crews = []) {
    return [
        {
            name: 'crew_id',
            label: __('Crew'),
            type: 'select',
            required: true,
            options: [
                { value: '', label: __('— Select crew (required) —') },
                ...crews.map((c) => ({ value: String(c.id), label: c.name })),
            ],
        },
        { name: 'name', label: __('Name'), type: 'text', required: true, placeholder: __('e.g. Lunch') },
        { name: 'start_time', label: __('Start time'), type: 'time', required: true },
        { name: 'end_time', label: __('End time'), type: 'time', required: true },
        {
            name: 'days_of_week',
            label: __('Days'),
            type: 'checkbox-group',
            required: true,
            options: getDayOptions(),
            help: __('Weekdays this break applies on.'),
        },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
