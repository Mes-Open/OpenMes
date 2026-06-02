const EVENT_TYPES = [
    { value: 'planned', label: 'Planned' },
    { value: 'corrective', label: 'Corrective' },
    { value: 'inspection', label: 'Inspection' },
];

const opt = (none, arr) => [
    { value: '', label: none },
    ...arr.map((x) => ({ value: String(x.id), label: x.name })),
];

export function maintenanceScheduleFields({ tools = [], lines = [], workstations = [], costSources = [], users = [], frequencies = [] }) {
    return [
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'event_type', label: 'Event Type', type: 'select', required: true, options: EVENT_TYPES },
        { name: 'tool_id', label: 'Tool', type: 'select', options: opt('— None —', tools) },
        { name: 'line_id', label: 'Line', type: 'select', options: opt('— None —', lines) },
        { name: 'workstation_id', label: 'Workstation', type: 'select', options: opt('— None —', workstations) },
        { name: 'assigned_to_id', label: 'Assigned To', type: 'select', options: opt('— None —', users) },
        { name: 'cost_source_id', label: 'Cost Source', type: 'select', options: opt('— None —', costSources) },
        { name: 'frequency', label: 'Frequency', type: 'select', required: true, options: frequencies.map((f) => ({ value: f, label: f })) },
        { name: 'interval_value', label: 'Interval Value', type: 'number', required: true },
        { name: 'preferred_time', label: 'Preferred Time', type: 'time' },
        { name: 'lead_time_days', label: 'Lead Time (days)', type: 'number' },
        { name: 'next_due_at', label: 'Next Due At', type: 'datetime', required: true },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
