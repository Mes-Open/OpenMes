import { __ } from '../../../lib/i18n';

// 5M defect taxonomy (Ishikawa) — values must match ScrapReason::CATEGORIES on the backend.
export const SCRAP_CATEGORY_VALUES = ['material', 'machine', 'method', 'man', 'environment'];

// Built as functions (not module constants) so labels are translated at render
// time, after the active locale chunk has loaded at bootstrap.
export function scrapCategoryOptions() {
    return [
        { value: 'material', label: __('Material') },
        { value: 'machine', label: __('Machine') },
        { value: 'method', label: __('Method') },
        { value: 'man', label: __('Man') },
        { value: 'environment', label: __('Environment') },
    ];
}

export function scrapReasonFields() {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'category', label: __('Category'), type: 'select', required: true, options: [{ value: '', label: __('— Select category —') }, ...scrapCategoryOptions()] },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'sort_order', label: __('Sort order'), type: 'number' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
