export const ABSENCE_TYPE_LABELS = {
    vacation: 'Vacation',
    sick: 'Sick',
    personal: 'Personal',
    training: 'Training',
    other: 'Other',
};

export const ABSENCE_STATUS_STYLES = {
    approved: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    rejected: 'bg-red-100 text-red-700',
};

export function absenceFields(workers = [], types = [], statuses = []) {
    return [
        {
            name: 'worker_id',
            label: 'Worker',
            type: 'select',
            required: true,
            options: [
                { value: '', label: '— Select worker (required) —' },
                ...workers.map((w) => ({ value: String(w.id), label: w.name })),
            ],
        },
        {
            name: 'type',
            label: 'Type',
            type: 'select',
            required: true,
            options: types.map((t) => ({ value: t, label: ABSENCE_TYPE_LABELS[t] ?? t })),
        },
        { name: 'starts_on', label: 'Starts on', type: 'date', required: true },
        { name: 'ends_on', label: 'Ends on', type: 'date', required: true },
        { name: 'all_day', label: 'All day', type: 'checkbox' },
        { name: 'start_time', label: 'Start time', type: 'time', help: 'Only used when "All day" is off.' },
        { name: 'end_time', label: 'End time', type: 'time', help: 'Only used when "All day" is off.' },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            options: statuses.map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) })),
        },
        { name: 'reason', label: 'Reason', type: 'textarea' },
    ];
}
