export function divisionFields(factories) {
    return [
        {
            name: 'factory_id',
            label: 'Factory',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...factories.map((f) => ({ value: String(f.id), label: f.name })),
            ],
        },
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
