export function siteFields(companies) {
    return [
        {
            name: 'company_id',
            label: 'Company',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...companies.map((c) => ({ value: String(c.id), label: c.name })),
            ],
        },
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'address', label: 'Address', type: 'textarea' },
        { name: 'city', label: 'City' },
        { name: 'country', label: 'Country (2-letter)' },
        { name: 'timezone', label: 'Timezone' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
