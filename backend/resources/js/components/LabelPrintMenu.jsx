import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Button, Dropdown, Icon } from '@openmes/ui';
import LabelPreviewModal from './LabelPreviewModal';
import { __ } from '../lib/i18n';

/**
 * The "print a label" button, everywhere one appears: opens the label in the
 * in-place preview (print, ZPL) instead of a dropdown of PDF/ZPL links that
 * lands in a new tab - on a station tablet that tab is a dead end. With more
 * than one active template of the type, the preview lets you pick one.
 *
 * Props:
 *   kind      — 'work-order' | 'finished-goods' | 'workstation-step' | 'pallet' | 'serial-unit'
 *   id        — entity id
 *   templates — all active label templates [{id,name,type,size,barcode_format,is_default}]
 *   label     — button text (default 'Print Label')
 *   size      — Button size ('sm' for dense rows)
 *   preferredTemplateId — template to open with when the caller knows one (a packing step's choice)
 */
const KIND_TO_TYPE = {
    'work-order': 'work_order',
    'finished-goods': 'finished_goods',
    'workstation-step': 'workstation_step',
    pallet: 'pallet',
    'serial-unit': 'serial_unit',
    carton: 'carton',
};

export default function LabelPrintMenu({ kind, id, templates = [], label = 'Print Label', size = 'md', variant = 'outline', preferredTemplateId = null }) {
    const [open, setOpen] = useState(false);
    const [templateId, setTemplateId] = useState(null);
    const wantType = KIND_TO_TYPE[kind];
    const applicable = templates.filter((t) => t.type === wantType);

    if (!id) return null;

    // No template configured for this type → point at the templates admin page.
    if (applicable.length === 0) {
        return (
            <Link
                href="/packaging/label-templates"
                className="inline-flex items-center gap-1.5 rounded-om-sm bg-om-chip px-3 py-1.5 text-sm text-om-muted hover:bg-om-line2"
                title={__('Configure label templates first')}
            >
                <Icon name="printer" size={14} /> {__('Set up labels…')}
            </Link>
        );
    }

    // Explicit pick, else what the caller prefers (a packing step's template), else the type's default.
    const chosen = applicable.find((t) => t.id === templateId)
        ?? applicable.find((t) => t.id === preferredTemplateId)
        ?? applicable.find((t) => t.is_default)
        ?? applicable[0];
    const url = (fmt) => `/packaging/labels/${kind}/${id}/${fmt}?template=${chosen.id}`;

    return (
        <>
            <Button variant={variant} size={size} leftIcon={<Icon name="printer" size={14} />} onClick={() => setOpen(true)}>
                {__(label)}
            </Button>
            <LabelPreviewModal
                open={open}
                onClose={() => setOpen(false)}
                title={__(label)}
                subtitle={`${chosen.name} · ${chosen.size} mm · ${String(chosen.barcode_format ?? '').toUpperCase()}`}
                pdfUrl={url('pdf')}
                zplUrl={url('zpl')}
                templatePicker={applicable.length > 1 ? (
                    <Dropdown
                        aria-label={__('Choose template')}
                        value={String(chosen.id)}
                        onChange={(v) => setTemplateId(Number(v))}
                        options={applicable.map((t) => ({ value: String(t.id), label: `${t.name}${t.is_default ? ` (${__('default')})` : ''} · ${t.size} mm` }))}
                        className="w-72"
                    />
                ) : null}
            />
        </>
    );
}
