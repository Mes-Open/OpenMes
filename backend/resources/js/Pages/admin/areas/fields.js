export function areaFields(sites) {
    return [
        {
            name: 'site_id',
            label: 'Site',
            type: 'select',
            required: true,
            options: [
                { value: '', label: '— Select site —' },
                ...sites.map((s) => ({ value: String(s.id), label: s.name })),
            ],
        },
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
