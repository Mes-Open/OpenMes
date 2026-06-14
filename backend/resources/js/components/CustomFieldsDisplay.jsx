import { __ } from '../lib/i18n';

/**
 * Read-only display of custom-field values on a detail (Show) page. Driven by
 * the same clientConfig definitions as the form. Renders nothing when no values
 * are set. File/Image values are stored-file metadata ({ path, name, … }) and
 * render as a download link / thumbnail via the authenticated file route.
 */
export default function CustomFieldsDisplay({ definitions = [], values = {}, title }) {
    const present = definitions.filter((d) => {
        const v = values?.[d.key];
        return v != null && v !== '' && !(Array.isArray(v) && v.length === 0);
    });

    if (!present.length) return null;

    return (
        <div className="bg-white rounded-lg shadow-sm p-6">
            <h2 className="text-lg font-semibold text-gray-800 mb-4">{title ?? __('Custom fields')}</h2>
            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {present.map((d) => (
                    <div key={d.key}>
                        <dt className="text-sm text-gray-500">{d.label}</dt>
                        <dd className="text-sm font-medium text-gray-900">{renderValue(d, values[d.key])}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function fileUrl(meta) {
    return meta?.path ? `/admin/custom-field-files/${meta.path.split('/').pop()}` : null;
}

function renderValue(def, value) {
    const options = def.config?.options ?? [];

    if (def.type === 'image') {
        const url = fileUrl(value);
        return url ? (
            <a href={url} target="_blank" rel="noreferrer">
                <img src={url} alt={value?.name ?? ''} className="h-24 w-24 rounded border border-gray-200 object-cover" />
            </a>
        ) : '—';
    }

    if (def.type === 'file') {
        const url = fileUrl(value);
        return url ? (
            <a href={url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">
                {value?.name ?? __('Download')}
            </a>
        ) : '—';
    }

    if (def.type === 'boolean') return value ? __('Yes') : __('No');

    if (def.type === 'multiselect') {
        const set = Array.isArray(value) ? value : [];
        return options.filter((o) => set.includes(o.value)).map((o) => o.label).join(', ') || '—';
    }

    if (def.type === 'select') {
        return options.find((o) => o.value === value)?.label ?? String(value);
    }

    return String(value);
}
