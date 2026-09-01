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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function absenceInitial(record) {
    if (!record) {
        return {
            worker_id: '',
            type: 'vacation',
            starts_on: '',
            ends_on: '',
            all_day: true,
            start_time: '',
            end_time: '',
            status: 'approved',
            reason: '',
        };
    }

    return {
        worker_id: record.worker_id ? String(record.worker_id) : '',
        type: record.type ?? 'vacation',
        starts_on: record.starts_on ?? '',
        ends_on: record.ends_on ?? '',
        all_day: !!record.all_day,
        start_time: record.start_time ?? '',
        end_time: record.end_time ?? '',
        status: record.status ?? 'approved',
        reason: record.reason ?? '',
    };
}
