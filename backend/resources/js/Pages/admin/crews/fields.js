import { __ } from '../../../lib/i18n';

export function crewFields(divisions, users, lines = []) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'line_ids',
            label: __('Lines'),
            type: 'checkbox-group',
            options: lines.map((l) => ({ value: l.id, label: l.name })),
            help: __('Lines this crew staffs (drives its labor demand on the capacity view).'),
        },
        {
            name: 'division_id',
            label: __('Division'),
            type: 'select',
            options: [{ value: '', label: __('— None —') }, ...divisions.map((d) => ({ value: String(d.id), label: d.name }))],
        },
        {
            name: 'leader_id',
            label: __('Leader'),
            type: 'select',
            options: [{ value: '', label: __('— None —') }, ...users.map((u) => ({ value: String(u.id), label: u.name }))],
        },
        { name: 'description', label: __('Description'), type: 'textarea' },
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
export function crewInitial(record, { crewLines } = {}) {
    if (!record) {
        return { code: '', name: '', division_id: '', leader_id: '', description: '', is_active: true, line_ids: [] };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        division_id: record.division_id != null ? String(record.division_id) : '',
        leader_id: record.leader_id != null ? String(record.leader_id) : '',
        description: record.description ?? '',
        is_active: !!record.is_active,
        // A pivot, so the synced row can't carry it — the list hands it over
        // separately. `record.line_ids` is the standalone edit page, which gets
        // it merged into the record by the controller.
        line_ids: record.line_ids ?? crewLines?.[record.id] ?? [],
    };
}
