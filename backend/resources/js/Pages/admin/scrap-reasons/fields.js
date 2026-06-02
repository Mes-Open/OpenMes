// 5M defect taxonomy (Ishikawa) — must match ScrapReason::CATEGORIES on the backend.
export const SCRAP_CATEGORIES = [
    { value: 'material', label: 'Material' },
    { value: 'machine', label: 'Machine' },
    { value: 'method', label: 'Method' },
    { value: 'man', label: 'Man' },
    { value: 'environment', label: 'Environment' },
];

export const SCRAP_REASON_FIELDS = [
    { name: 'code', label: 'Code', required: true },
    { name: 'name', label: 'Name', required: true },
    { name: 'category', label: 'Category', type: 'select', required: true, options: [{ value: '', label: '— Select category —' }, ...SCRAP_CATEGORIES] },
    { name: 'description', label: 'Description', type: 'textarea' },
    { name: 'sort_order', label: 'Sort order', type: 'number' },
    { name: 'is_active', label: 'Active', type: 'checkbox' },
];
