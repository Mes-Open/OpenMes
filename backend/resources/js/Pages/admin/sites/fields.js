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
