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
