import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Link } from '@inertiajs/react';
import { useAnchoredPopover } from '@openmes/ui/src/lib/anchorPopover';
import { __ } from '../lib/i18n';

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
    pallet: 'pallet',
};

export default function LabelPrintMenu({ kind, id, templates = [], label = 'Print Label' }) {
    const [open, setOpen] = useState(false);
    // Portaled to <body> at fixed coordinates: rendered inline, the menu was
    // clipped by the scrolling table it sits in (the workstation view's rows).
    const { anchorRef, popRef, style } = useAnchoredPopover(open, { estHeight: 260, estWidth: 256 });
    const wantType = KIND_TO_TYPE[kind];
    const applicable = templates.filter((t) => t.type === wantType);

    useEffect(() => {
        if (!open) return undefined;
        // The menu no longer lives inside the trigger's subtree — check both.
        const onPointer = (e) => {
            if (anchorRef.current?.contains(e.target) || popRef.current?.contains(e.target)) return;
            setOpen(false);
        };
        const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, anchorRef, popRef]);

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
               className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-sm bg-om-chip hover:bg-om-line2 text-om-muted"
               title={__('Configure label templates first')}>
                {printIcon} {__('Set up labels…')}
            </Link>
        );
    }

    const url = (tid, fmt) => `/packaging/labels/${kind}/${id}/${fmt}?template=${tid}`;

    return (
        <div className="inline-block">
            <button
                ref={anchorRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={(e) => { e.stopPropagation(); setOpen((o) => !o); }}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-om-sm text-sm bg-om-chip hover:bg-om-line2 text-om-muted"
            >
                {printIcon}
                {label}
                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            {open && style && createPortal(
                <div
                    ref={popRef}
                    style={style}
                    // A row behind it may open something on click (the workstation table does).
                    onClick={(e) => e.stopPropagation()}
                    className="w-64 bg-om-card rounded-om-sm shadow-lg border border-om-line2"
                >
                    <div className="p-2">
                        <p className="px-2 py-1 text-xs text-om-muted uppercase tracking-wide">{__('Choose template')}</p>
                        {applicable.map((t) => (
                            <div key={t.id} className="px-2 py-1.5">
                                <p className="text-sm font-medium text-om-muted truncate">
                                    {t.name}
                                    {t.is_default && <span className="text-[10px] text-om-accent ml-1">{__('default')}</span>}
                                </p>
                                <p className="text-xs text-om-muted">
                                    {t.size} mm · {String(t.barcode_format ?? '').toUpperCase()}
                                </p>
                                <div className="flex gap-2 mt-1">
                                    <a href={url(t.id, 'pdf')} target="_blank" rel="noreferrer"
                                       className="flex-1 text-center text-xs px-2 py-1 rounded bg-om-ink text-om-on-ink hover:bg-om-ink-hover">PDF</a>
                                    <a href={url(t.id, 'zpl')}
                                       className="flex-1 text-center text-xs px-2 py-1 rounded bg-om-ink text-om-on-ink hover:bg-om-ink-hover">ZPL</a>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
