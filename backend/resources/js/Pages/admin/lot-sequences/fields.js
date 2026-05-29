export function lotSequenceFields(productTypes) {
    return [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'product_type_id',
            label: 'Product Type',
            type: 'select',
            options: [
                { value: '', label: '— None —' },
                ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
            ],
        },
        { name: 'prefix', label: 'Prefix', required: true },
        { name: 'suffix', label: 'Suffix' },
        { name: 'pad_size', label: 'Pad Size', type: 'number' },
        { name: 'year_prefix', label: 'Year Prefix', type: 'checkbox' },
    ];
}
