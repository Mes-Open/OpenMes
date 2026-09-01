import { __ } from '../../../lib/i18n';

export function areaFields(sites) {
    return [
        {
            name: 'site_id',
            label: __('Site'),
            type: 'select',
            required: true,
            options: [
                { value: '', label: __('— Select site —') },
                ...sites.map((s) => ({ value: String(s.id), label: s.name })),
            ],
        },
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}

/**
 * A record (from the synced row, or the server prop on the standalone edit
 * page) as form values — and with no record, an empty form.
 *
 * One definition, shared by Create.jsx, Edit.jsx and the list's drawer, so the
 * three can't drift on what a blank field is or how a value is coerced. The
 * selects want strings; `useForm` would otherwise hand a number to a
 * <select> whose option values are strings and quietly show nothing selected.
 */
export function areaInitial(area) {
    return {
        site_id: area?.site_id != null ? String(area.site_id) : '',
        code: area?.code ?? '',
        name: area?.name ?? '',
        description: area?.description ?? '',
        is_active: area ? !!area.is_active : true,
        custom_fields: area?.custom_fields ?? {},
    };
}
