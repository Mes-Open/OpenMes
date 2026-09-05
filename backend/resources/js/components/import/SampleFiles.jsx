import { __ } from '../../lib/i18n';

const DownloadIcon = () => (
    <svg className="w-4 h-4 text-om-faint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden>
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
    </svg>
);

/**
 * `inline` renders the same links as one wrapped row, for the space beside the
 * entity picker; the stacked list is for a sidebar column.
 */
export default function SampleFiles({ basePath, entities, inline = false }) {
    if (inline) {
        return (
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span className="text-om-muted">{__('Sample files:')}</span>
                {entities.map((e) => (
                    <a
                        key={e.slug}
                        href={`${basePath}/samples/${e.slug}`}
                        className="inline-flex items-center gap-1 text-om-ink hover:text-om-accent"
                    >
                        <DownloadIcon />
                        {e.label}
                    </a>
                ))}
            </div>
        );
    }

    return (
        <div className="px-5 py-4">
            <h2 className="text-sm font-bold text-om-ink mb-2">{__('Download sample CSV files')}</h2>
            <ul className="divide-y divide-om-line2">
                {entities.map((e) => (
                    <li key={e.slug}>
                        <a
                            href={`${basePath}/samples/${e.slug}`}
                            className="flex items-center gap-2 py-2 text-sm text-om-ink hover:text-om-accent"
                        >
                            <DownloadIcon />
                            {__('Sample :entity file', { entity: e.label })}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}
