export function crewFields(divisions, users) {
    return [
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
            name: 'division_id',
            label: 'Division',
            type: 'select',
            options: [{ value: '', label: '— None —' }, ...divisions.map((d) => ({ value: String(d.id), label: d.name }))],
        },
        {
            name: 'leader_id',
            label: 'Leader',
            type: 'select',
            options: [{ value: '', label: '— None —' }, ...users.map((u) => ({ value: String(u.id), label: u.name }))],
        },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
