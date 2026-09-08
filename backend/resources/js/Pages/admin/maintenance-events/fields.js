import { dateTimeLocal } from '../../../lib/syncedRow';
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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function maintenanceEventInitial(record, { scheduled_at, scheduled_end_at } = {}) {
    if (!record) {
        return {
            title: '',
            event_type: 'planned',
            tool_id: '',
            line_id: '',
            workstation_id: '',
            cost_source_id: '',
            assigned_to_id: '',
            scheduled_at: '',
            scheduled_end_at: '',
            actual_cost: '',
            currency: 'PLN',
            description: '',
        };
    }

    return {
        title: record.title ?? '',
        event_type: record.event_type ?? 'planned',
        tool_id: record.tool_id != null ? String(record.tool_id) : '',
        line_id: record.line_id != null ? String(record.line_id) : '',
        workstation_id: record.workstation_id != null ? String(record.workstation_id) : '',
        cost_source_id: record.cost_source_id != null ? String(record.cost_source_id) : '',
        assigned_to_id: record.assigned_to_id != null ? String(record.assigned_to_id) : '',
        // The edit page is handed these pre-formatted by the controller; a synced
        // row carries the raw column, which a datetime-local input won't accept.
        scheduled_at: scheduled_at ?? dateTimeLocal(record.scheduled_at),
        scheduled_end_at: scheduled_end_at ?? dateTimeLocal(record.scheduled_end_at),
        actual_cost: record.actual_cost ?? '',
        currency: record.currency ?? '',
        description: record.description ?? '',
    };
}
