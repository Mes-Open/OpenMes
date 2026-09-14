import { __ } from '../../../lib/i18n';

/**
 * Options for the `kind` picker, built from what the server sent.
 *
 * The kind is not cosmetic: planned downtime shortens the operating window
 * without counting against availability, while unplanned and changeover count
 * as loss. The label says so, because an admin picking from a bare list has no
 * way to know which choice moves the OEE figures.
 */
export function downtimeKindOptions(kinds = []) {
    return kinds.map((k) => ({
        value: k.value,
        label: k.counts_as_loss
            ? __(':kind — counts against availability', { kind: __(k.label) })
            : __(':kind — does not count against availability', { kind: __(k.label) }),
    }));
}

export function downtimeReasonFields(kinds = []) {
    return [
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        {
            name: 'kind',
            label: __('Kind'),
            type: 'select',
            required: true,
            options: [{ value: '', label: __('— Select kind —') }, ...downtimeKindOptions(kinds)],
        },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/**
 * A record as form values, and with no record an empty form.
 *
 * One definition shared by Create.jsx, Edit.jsx and the list's drawer, so the
 * three cannot drift on what a blank field is.
 */
export function downtimeReasonInitial(record) {
    if (!record) {
        return { code: '', name: '', kind: '', is_active: true };
    }

    return {
        code: record.code ?? '',
        name: record.name ?? '',
        kind: record.kind ?? '',
        is_active: !!record.is_active,
    };
}
