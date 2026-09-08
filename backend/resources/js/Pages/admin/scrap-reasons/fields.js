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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function scrapReasonInitial(record) {
    if (!record) {
        return { code: '', name: '', category: '', description: '', sort_order: 0, is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        category: record.category ?? '',
        description: record.description ?? '',
        sort_order: record.sort_order ?? 0,
        is_active: !!record.is_active,
    };
}
