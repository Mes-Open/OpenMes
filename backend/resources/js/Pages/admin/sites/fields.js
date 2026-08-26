import { __ } from '../../../lib/i18n';

export function siteFields(companies) {
    return [
        {
            name: 'company_id',
            label: __('Company'),
            type: 'select',
            options: [
                { value: '', label: __('— None —') },
                ...companies.map((c) => ({ value: String(c.id), label: c.name })),
            ],
        },
        { name: 'code', label: __('Code'), required: true },
        { name: 'name', label: __('Name'), required: true },
        { name: 'description', label: __('Description'), type: 'textarea' },
        { name: 'address', label: __('Address'), type: 'textarea' },
        { name: 'city', label: __('City') },
        { name: 'country', label: __('Country (2-letter)') },
        { name: 'timezone', label: __('Timezone') },
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
export function siteInitial(record) {
    if (!record) {
        return { company_id: '', code: '', name: '', description: '', address: '', city: '', country: '', timezone: '', is_active: true };
    }

    return {
        company_id: record.company_id != null ? String(record.company_id) : '',
        code: record.code ?? '',
        name: record.name ?? '',
        description: record.description ?? '',
        address: record.address ?? '',
        city: record.city ?? '',
        country: record.country ?? '',
        timezone: record.timezone ?? '',
        is_active: !!record.is_active,
        custom_fields: record.custom_fields ?? {},
    };
}
