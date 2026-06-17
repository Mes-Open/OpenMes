/**
 * Modal — Geist White system (design ref: OpenMES Components.dc.html §09
 * form-modal specimen).
 *
 * Form-modal shell: header (15px semibold title + optional mono 9.5px
 * subtitle, × close, line2 hairline), body (children), footer (right-aligned
 * actions, top hairline, panel bg) over the scrim token.
 * API is identical to the native twin (index.native.tsx).
 */
import { useEffect } from 'react';
import { createPortal } from 'react-dom';

export function Modal({ open, onClose, title, subtitle, footer, children, closeLabel, className = '', ...props }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => {
            if (e.key === 'Escape') onClose?.();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(10,9,8,0.4)] p-6" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                className={`w-full max-w-[440px] overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] ${className}`}
                onClick={(e) => e.stopPropagation()}
                {...props}
            >
                <div className="flex items-center justify-between border-b border-om-line2 px-[18px] py-4">
                    <div>
                        <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                        {subtitle != null && <div className="mt-[3px] font-mono text-[9.5px] text-om-faint">{subtitle}</div>}
                    </div>
                    <button
                        type="button"
                        aria-label={closeLabel}
                        onClick={onClose}
                        className="cursor-pointer text-[18px] leading-none text-om-faint hover:text-om-muted"
                    >
                        ×
                    </button>
                </div>
                <div className="px-[18px] py-4">{children}</div>
                {footer != null && (
                    <div className="flex justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]">{footer}</div>
                )}
            </div>
        </div>,
        document.body,
    );
}
