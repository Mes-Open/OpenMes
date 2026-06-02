import { useState, useRef, useEffect } from 'react';
import { Link } from '@inertiajs/react';

/**
 * Label-print dropdown — React replacement for the old <x-label-print-dropdown>
 * Blade component. Lists the active label templates of the matching type and
 * links to the PDF/ZPL endpoints under /packaging/labels/{kind}/{id}.
 *
 * Props:
 *   kind      — 'work-order' | 'finished-goods' | 'workstation-step'
 *   id        — entity id (workOrder / batch / batchStep)
 *   templates — all active label templates [{id,name,type,size,barcode_format,is_default}]
 *   label     — button text (default 'Print Label')
 */
const KIND_TO_TYPE = {
    'work-order': 'work_order',
    'finished-goods': 'finished_goods',
    'workstation-step': 'workstation_step',
};

export default function LabelPrintMenu({ kind, id, templates = [], label = 'Print Label' }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const wantType = KIND_TO_TYPE[kind];
    const applicable = templates.filter((t) => t.type === wantType);

    useEffect(() => {
        if (!open) return undefined;
        const onClick = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, [open]);

    if (!id) return null;

    const printIcon = (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
    );

    // No template configured for this type → point at the templates admin page.
    if (applicable.length === 0) {
        return (
            <Link href="/packaging/label-templates"
               className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-gray-100 hover:bg-gray-200 text-gray-700"
               title="Configure label templates first">
                {printIcon} Set up labels…
            </Link>
        );
    }

    const url = (tid, fmt) => `/packaging/labels/${kind}/${id}/${fmt}?template=${tid}`;

    return (
        <div className="relative inline-block" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-gray-100 hover:bg-gray-200 text-gray-700"
            >
                {printIcon}
                {label}
                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            {open && (
                <div className="absolute right-0 mt-1 w-64 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 z-50">
                    <div className="p-2">
                        <p className="px-2 py-1 text-xs text-gray-500 uppercase tracking-wide">Choose template</p>
                        {applicable.map((t) => (
                            <div key={t.id} className="px-2 py-1.5">
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                                    {t.name}
                                    {t.is_default && <span className="text-[10px] text-blue-600 ml-1">default</span>}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {t.size} mm · {String(t.barcode_format ?? '').toUpperCase()}
                                </p>
                                <div className="flex gap-2 mt-1">
                                    <a href={url(t.id, 'pdf')} target="_blank" rel="noreferrer"
                                       className="flex-1 text-center text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">PDF</a>
                                    <a href={url(t.id, 'zpl')}
                                       className="flex-1 text-center text-xs px-2 py-1 rounded bg-gray-700 text-white hover:bg-gray-800">ZPL</a>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
