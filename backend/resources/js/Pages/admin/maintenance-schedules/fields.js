import { dateTimeLocal, timeOfDay } from '../../../lib/syncedRow';
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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function maintenanceScheduleInitial(record, { preferred_time, next_due_at } = {}) {
    if (!record) {
        return {
            name: '',
            description: '',
            event_type: 'planned',
            tool_id: '',
            line_id: '',
            workstation_id: '',
            assigned_to_id: '',
            cost_source_id: '',
            frequency: 'monthly',
            interval_value: 1,
            preferred_time: '',
            lead_time_days: '',
            next_due_at: '',
            is_active: true,
        };
    }

    return {
        name: record.name ?? '',
        description: record.description ?? '',
        event_type: record.event_type ?? 'planned',
        tool_id: record.tool_id != null ? String(record.tool_id) : '',
        line_id: record.line_id != null ? String(record.line_id) : '',
        workstation_id: record.workstation_id != null ? String(record.workstation_id) : '',
        assigned_to_id: record.assigned_to_id != null ? String(record.assigned_to_id) : '',
        cost_source_id: record.cost_source_id != null ? String(record.cost_source_id) : '',
        frequency: record.frequency ?? 'monthly',
        interval_value: record.interval_value ?? 1,
        // Pre-formatted by the controller for the edit page; raw on a synced row.
        preferred_time: preferred_time ?? timeOfDay(record.preferred_time),
        lead_time_days: record.lead_time_days ?? '',
        next_due_at: next_due_at ?? dateTimeLocal(record.next_due_at),
        is_active: !!record.is_active,
    };
}
