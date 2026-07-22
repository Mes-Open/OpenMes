import { __ } from '../../../lib/i18n';

// Lifecycle states — values must match App\Enums\RevisionLifecycle.
export const LIFECYCLE_BADGE_STYLES = {
    draft: 'bg-gray-200 text-gray-700',
    released: 'bg-green-100 text-green-800',
    obsolete: 'bg-amber-100 text-amber-800',
};

export function lifecycleLabel(status) {
    return {
        draft: __('Draft'),
        released: __('Released'),
        obsolete: __('Obsolete'),
    }[status] ?? status;
}

/** Label for a process-template option: "Name v3 (inactive)". */
function templateLabel(t) {
    const inactive = t.is_active ? '' : ` (${__('inactive')})`;
    return `${t.name} v${t.version}${inactive}`;
}

// Editable fields for create/edit. On edit, product type is fixed (not shown as
// editable) — pass `lockProductType` to render it read-only-ish via a single option.
export function productRevisionFields(productTypes = [], processTemplates = [], { lockProductType = false } = {}) {
    return [
        {
            name: 'product_type_id', label: __('Product Type'), type: 'select', required: true,
            options: productTypes.map((p) => ({ value: String(p.id), label: p.code ? `${p.code} — ${p.name}` : p.name })),
            disabled: lockProductType,
            help: lockProductType ? __('Product type cannot be changed after creation.') : undefined,
        },
        { name: 'revision_code', label: __('Revision Code'), required: true, help: __('Letters, digits, dot or hyphen — e.g. A, 01, C.2.') },
        { name: 'description', label: __('Description') },
        {
            name: 'process_template_id', label: __('Process Template (released config)'), type: 'select',
            options: [
                { value: '', label: __('— None —') },
                ...processTemplates.map((t) => ({ value: String(t.id), label: templateLabel(t) })),
            ],
            help: __('The process + BOM this revision releases. Required before the revision can be released.'),
        },
        { name: 'change_reason', label: __('Change Reason') },
        { name: 'external_ref', label: __('External Reference') },
    ];
}
