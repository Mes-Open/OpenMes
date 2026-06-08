export function shiftFields(lines) {
    return [
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
            name: 'line_id',
            label: 'Line',
            type: 'select',
            options: [
                { value: '', label: '— Global (all lines) —' },
                ...lines.map((l) => ({ value: String(l.id), label: l.name })),
            ],
        },
        { name: 'start_time', label: 'Start Time', type: 'time', required: true },
        { name: 'end_time', label: 'End Time', type: 'time', required: true },
        { name: 'sort_order', label: 'Sort Order', type: 'number' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
