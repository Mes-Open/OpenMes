export function subassemblyFields(productTypes) {
    return [
        {
            name: 'product_type_id',
            label: 'Product Type',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
            ],
        },
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'is_active', label: 'Active', type: 'checkbox' },
    ];
}
