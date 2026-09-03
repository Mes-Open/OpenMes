import { __ } from '../../lib/i18n';

export default function SampleFiles({ basePath, entities }) {
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
                            <svg className="w-4 h-4 text-om-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden>
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {__('Sample :entity file', { entity: e.label })}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}
