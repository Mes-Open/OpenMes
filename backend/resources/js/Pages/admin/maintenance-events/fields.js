import { __ } from '../../../lib/i18n';

const opt = (none, arr) => [
    { value: '', label: none },
    ...arr.map((x) => ({ value: String(x.id), label: x.name })),
];

export function maintenanceEventFields({ tools, lines, workstations, costSources, users }) {
    return [
        { name: 'title', label: __('Title'), required: true },
        {
            name: 'event_type',
            label: __('Type'),
            type: 'select',
            required: true,
            options: [
                { value: 'planned', label: __('Planned') },
                { value: 'corrective', label: __('Corrective') },
                { value: 'inspection', label: __('Inspection') },
            ],
        },
        { name: 'tool_id', label: __('Tool'), type: 'select', options: opt(__('— None —'), tools) },
        { name: 'line_id', label: __('Line'), type: 'select', options: opt(__('— None —'), lines) },
        { name: 'workstation_id', label: __('Workstation'), type: 'select', options: opt(__('— None —'), workstations) },
        { name: 'cost_source_id', label: __('Cost Source'), type: 'select', options: opt(__('— None —'), costSources) },
        { name: 'assigned_to_id', label: __('Assigned To'), type: 'select', options: opt(__('— None —'), users) },
        { name: 'scheduled_at', label: __('Scheduled At'), type: 'datetime', required: true },
        { name: 'scheduled_end_at', label: __('Scheduled End'), type: 'datetime' },
        { name: 'actual_cost', label: __('Actual Cost'), type: 'number' },
        { name: 'currency', label: __('Currency') },
        { name: 'description', label: __('Description'), type: 'textarea' },
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
