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

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's create/edit
 * drawer, so the three can't drift on what a blank field is or how a stored
 * value is coerced for the input that shows it.
 */
export function productRevisionInitial(record) {
    if (!record) {
        return { product_type_id: '', revision_code: '', description: '', process_template_id: '', change_reason: '', external_ref: '' };
    }

    return {
        product_type_id: record.product_type_id != null ? String(record.product_type_id) : '',
        revision_code: record.revision_code ?? '',
        description: record.description ?? '',
        process_template_id: record.process_template_id != null ? String(record.process_template_id) : '',
        change_reason: record.change_reason ?? '',
        external_ref: record.external_ref ?? '',
    };
}
