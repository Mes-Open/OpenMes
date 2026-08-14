import { __ } from '../../../lib/i18n';

/**
 * Document types, keep in sync with StockDocument::TYPES.
 *
 * A function rather than a module constant: page modules are bundled eagerly
 * (`import.meta.glob(..., { eager: true })` in app.jsx), so anything evaluated at
 * module scope runs BEFORE the locale chunk is loaded and a `__()` there would
 * freeze the untranslated English key. Call this during render.
 */
export function documentTypes() {
    return [
        { value: 'material_issue', label: __('Material release') },
        { value: 'material_receipt', label: __('Material receipt') },
        { value: 'product_receipt', label: __('Product receipt') },
        { value: 'product_issue', label: __('Product release') },
    ];
}

/** value → translated label map, built during render. */
export function documentTypeLabels() {
    return Object.fromEntries(documentTypes().map((t) => [t.value, t.label]));
}
