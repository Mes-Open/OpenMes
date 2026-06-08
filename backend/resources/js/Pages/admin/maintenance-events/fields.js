const opt = (none, arr) => [
    { value: '', label: none },
    ...arr.map((x) => ({ value: String(x.id), label: x.name })),
];

export function maintenanceEventFields({ tools, lines, workstations, costSources, users }) {
    return [
        { name: 'title', label: 'Title', required: true },
        {
            name: 'event_type',
            label: 'Type',
            type: 'select',
            required: true,
            options: [
                { value: 'planned', label: 'Planned' },
                { value: 'corrective', label: 'Corrective' },
                { value: 'inspection', label: 'Inspection' },
            ],
        },
        { name: 'tool_id', label: 'Tool', type: 'select', options: opt('— None —', tools) },
        { name: 'line_id', label: 'Line', type: 'select', options: opt('— None —', lines) },
        { name: 'workstation_id', label: 'Workstation', type: 'select', options: opt('— None —', workstations) },
        { name: 'cost_source_id', label: 'Cost Source', type: 'select', options: opt('— None —', costSources) },
        { name: 'assigned_to_id', label: 'Assigned To', type: 'select', options: opt('— None —', users) },
        { name: 'scheduled_at', label: 'Scheduled At', type: 'datetime', required: true },
        { name: 'scheduled_end_at', label: 'Scheduled End', type: 'datetime' },
        { name: 'actual_cost', label: 'Actual Cost', type: 'number' },
        { name: 'currency', label: 'Currency' },
        { name: 'description', label: 'Description', type: 'textarea' },
    ];
}

export const EVENT_STATUS_STYLES = {
    pending: 'bg-blue-100 text-blue-800',
    planned: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    done: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-600',
};
