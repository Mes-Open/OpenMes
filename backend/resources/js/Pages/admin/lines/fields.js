export function lineFields(areas) {
    return [
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
            name: 'area_id', label: 'Area', type: 'select',
            options: [{ value: '', label: '— None —' }, ...areas.map((a) => ({ value: String(a.id), label: a.name }))],
        },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
