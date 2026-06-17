import { __ } from '../lib/i18n';

/**
 * Read-only display of custom-field values on a detail (Show) page. Driven by
 * the same clientConfig definitions as the form. Renders nothing when no values
 * are set. File/Image values are stored-file metadata ({ path, name, … }) and
 * render as a download link / thumbnail via the authenticated file route.
 * Geist White (light-only v1 — no dark: variants).
 */
export default function CustomFieldsDisplay({ definitions = [], values = {}, title }) {
    const present = definitions.filter((d) => {
        const v = values?.[d.key];
        return v != null && v !== '' && !(Array.isArray(v) && v.length === 0);
    });

    if (!present.length) return null;

    return (
        <div className="bg-om-card border border-om-line rounded-om p-6">
            <h2 className="text-[15px] font-semibold text-om-ink pb-3 mb-4 border-b border-om-line2">{title ?? __('Custom fields')}</h2>
            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {present.map((d) => (
                    <div key={d.key}>
                        <dt className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[3px]">{d.label}</dt>
                        <dd className="text-[13px] font-medium text-om-ink">{renderValue(d, values[d.key])}</dd>
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
                <img src={url} alt={value?.name ?? ''} className="h-24 w-24 rounded-om-sm border border-om-line object-cover" />
            </a>
        ) : '—';
    }

    if (def.type === 'file') {
        const url = fileUrl(value);
        return url ? (
            <a href={url} target="_blank" rel="noreferrer" className="text-om-accent hover:underline">
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
