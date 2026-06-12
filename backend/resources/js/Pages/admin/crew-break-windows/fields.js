// ISO weekdays: 1 = Mon … 7 = Sun (matches the backend days_of_week).
export const DAY_OPTIONS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const DAY_LABELS = Object.fromEntries(DAY_OPTIONS.map((d) => [d.value, d.label]));

/** Render a days_of_week array as e.g. "Mon, Tue, Wed". */
export function formatDays(days = []) {
    return [...days]
        .map(Number)
        .sort((a, b) => a - b)
        .map((d) => DAY_LABELS[d] ?? d)
        .join(', ');
}

export function crewBreakWindowFields(crews = []) {
    return [
        {
            name: 'crew_id',
            label: 'Crew',
            type: 'select',
            required: true,
            options: [
                { value: '', label: '— Select crew (required) —' },
                ...crews.map((c) => ({ value: String(c.id), label: c.name })),
            ],
        },
        { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'e.g. Lunch' },
        { name: 'start_time', label: 'Start time', type: 'time', required: true },
        { name: 'end_time', label: 'End time', type: 'time', required: true },
        {
            name: 'days_of_week',
            label: 'Days',
            type: 'checkbox-group',
            required: true,
            options: DAY_OPTIONS,
            help: 'Weekdays this break applies on.',
        },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
