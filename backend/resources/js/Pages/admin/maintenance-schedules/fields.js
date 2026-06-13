import { __ } from '../../../lib/i18n';

const EVENT_TYPES = [
    { value: 'planned', label: __('Planned') },
    { value: 'corrective', label: __('Corrective') },
    { value: 'inspection', label: __('Inspection') },
];

const opt = (none, arr) => [
    { value: '', label: none },
    ...arr.map((x) => ({ value: String(x.id), label: x.name })),
];

export function maintenanceScheduleFields({ tools = [], lines = [], workstations = [], costSources = [], users = [], frequencies = [] }) {
    return [
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'event_type', label: __('Event Type'), type: 'select', required: true, options: EVENT_TYPES },
        { name: 'tool_id', label: __('Tool'), type: 'select', options: opt(__('— None —'), tools) },
        { name: 'line_id', label: __('Line'), type: 'select', options: opt(__('— None —'), lines) },
        { name: 'workstation_id', label: __('Workstation'), type: 'select', options: opt(__('— None —'), workstations) },
        { name: 'assigned_to_id', label: __('Assigned To'), type: 'select', options: opt(__('— None —'), users) },
        { name: 'cost_source_id', label: __('Cost Source'), type: 'select', options: opt(__('— None —'), costSources) },
        { name: 'frequency', label: __('Frequency'), type: 'select', required: true, options: frequencies.map((f) => ({ value: f, label: f })) },
        { name: 'interval_value', label: __('Interval Value'), type: 'number', required: true },
        { name: 'preferred_time', label: __('Preferred Time'), type: 'time' },
        { name: 'lead_time_days', label: __('Lead Time (days)'), type: 'number' },
        { name: 'next_due_at', label: __('Next Due At'), type: 'datetime', required: true },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
