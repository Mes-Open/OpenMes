import { useRef, useState } from 'react';
import { __ } from '../../lib/i18n';

/**
 * Drag-and-drop file picker for the importer. Controlled: hands the chosen
 * File to `onChange`; the parent keeps it in its Inertia form.
 */
export default function FileDropZone({ file, onChange, accept = '.csv,.txt,.xlsx,.xls', hint, error }) {
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef(null);

    const pick = (list) => {
        const chosen = list?.[0];
        if (chosen) onChange(chosen);
    };

    return (
        <div>
            <div
                role="button"
                tabIndex={0}
                aria-label={__('Choose a file to import')}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inputRef.current?.click(); } }}
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={(e) => { e.preventDefault(); setDragging(false); }}
                onDrop={(e) => { e.preventDefault(); setDragging(false); pick(e.dataTransfer.files); }}
                className={`border-2 border-dashed rounded-om p-8 text-center transition-colors cursor-pointer
                    ${dragging ? 'border-om-accent bg-om-chip' : error ? 'border-om-blocked' : 'border-om-line hover:border-om-faintest'}`}
            >
                <svg className="mx-auto h-10 w-10 text-om-faint mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden>
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p className="text-om-muted font-medium">
                    {__('Drop file here or')} <span className="text-om-accent">{__('browse')}</span>
                </p>
                {hint && <p className="text-xs text-om-faint mt-1">{hint}</p>}
                {file && <p className="mt-2 text-sm text-om-accent font-medium break-all">{file.name}</p>}
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    className="hidden"
                    tabIndex={-1}
                    // The programmatic click would bubble back to the zone's onClick
                    // and open a second chooser.
                    onClick={(e) => e.stopPropagation()}
                    onChange={(e) => { pick(e.target.files); e.target.value = ''; }}
                />
            </div>
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
