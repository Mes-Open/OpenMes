import { useRef } from 'react';
import { Button, Icon, Modal } from '@openmes/ui';
import { __ } from '../lib/i18n';

/**
 * A label PDF shown in place, with a Print button - for a station screen on a
 * tablet, where a new browser tab is a dead end. The PDF renders in an iframe
 * and printing goes through that frame, so the print dialog gets the label at
 * its own page size instead of the whole screen.
 *
 * Props: { open, onClose, title, subtitle?, pdfUrl, zplUrl?, templatePicker? }
 * `templatePicker` is an optional control shown above the preview (a template
 * dropdown when more than one applies).
 */
export default function LabelPreviewModal({ open, onClose, title, subtitle, pdfUrl, zplUrl = null, templatePicker = null }) {
    const frameRef = useRef(null);

    const print = () => {
        const win = frameRef.current?.contentWindow;
        if (!win) return;
        try {
            win.focus();
            win.print();
        } catch {
            window.open(pdfUrl, '_blank');
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            subtitle={subtitle}
            closeLabel={__('Close')}
            footer={
                <>
                    {zplUrl && (
                        <a href={zplUrl} className="mr-auto inline-flex items-center gap-1.5 text-[12.5px] text-om-muted hover:text-om-ink">
                            <Icon name="download" size={14} />{__('ZPL for a label printer')}
                        </a>
                    )}
                    <Button variant="secondary" onClick={onClose}>{__('Close')}</Button>
                    <Button variant="primary" leftIcon={<Icon name="printer" size={14} />} onClick={print}>{__('Print')}</Button>
                </>
            }
        >
            {templatePicker && <div className="mb-3">{templatePicker}</div>}
            {open && pdfUrl && (
                <iframe
                    key={pdfUrl}
                    ref={frameRef}
                    title={title}
                    src={`${pdfUrl}#toolbar=0&navpanes=0&view=Fit`}
                    className="w-full rounded-om-sm border border-om-line bg-om-bg"
                    style={{ height: 'min(58vh, 520px)' }}
                />
            )}
        </Modal>
    );
}
