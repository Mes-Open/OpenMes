/**
 * Modal — Geist White system (design ref: OpenMES Components.dc.html §09
 * form-modal specimen).
 *
 * Form-modal shell: header (15px semibold title + optional mono 9.5px
 * subtitle, × close, line2 hairline), body (children), footer (right-aligned
 * actions, top hairline, panel bg) over the scrim token.
 * API is identical to the native twin (index.native.tsx).
 */
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

import { Icon } from '../Icon';

export function Modal({
    open,
    onClose,
    title,
    subtitle,
    footer,
    children,
    closeLabel,
    /**
     * Keep the children mounted (hidden) after the first open, so a half-filled
     * form survives a stray click on the scrim.
     *
     * Off by default, and deliberately so: a modal opened per row (`open={holdFor
     * != null}`) reuses one instance for every record, and retained state there
     * would carry one row's typed values onto the next. Only turn it on where the
     * modal always edits the same thing — a "new X" form.
     */
    keepMounted = false,
    className = '',
    ...props
}) {
    // Nothing is mounted until the first open, so `keepMounted` costs nothing on
    // pages where the modal is never used.
    const opened = useRef(false);
    if (open) opened.current = true;

    useEffect(() => {
        if (!open) return;
        const onKey = (e) => {
            if (e.key === 'Escape') onClose?.();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open && !(keepMounted && opened.current)) return null;

    return createPortal(
        // `hidden` (not unmount) while retained: display:none also drops the
        // subtree from the tab order and the accessibility tree.
        <div
            className={`fixed inset-0 z-50 items-center justify-center bg-[rgba(10,9,8,0.4)] p-6 ${open ? 'flex' : 'hidden'}`}
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-modal="true"
                // Capped to the viewport with only the body scrolling, so a long
                // form keeps its header and footer actions in reach.
                className={`flex max-h-[88vh] w-full max-w-[440px] flex-col overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] ${className}`}
                onClick={(e) => e.stopPropagation()}
                {...props}
            >
                <div className="flex shrink-0 items-center justify-between border-b border-om-line2 px-[18px] py-4">
                    <div>
                        <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                        {subtitle != null && <div className="mt-[3px] font-mono text-[9.5px] text-om-faint">{subtitle}</div>}
                    </div>
                    <button
                        type="button"
                        aria-label={closeLabel}
                        onClick={onClose}
                        className="flex size-7 cursor-pointer items-center justify-center rounded-[6px] text-om-blocked hover:bg-om-blocked-bg"
                    >
                        <Icon name="x" size={16} />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-[18px] py-4">{children}</div>
                {footer != null && (
                    <div className="flex shrink-0 justify-end gap-[9px] border-t border-om-line2 bg-om-panel px-[18px] py-[14px]">{footer}</div>
                )}
            </div>
        </div>,
        document.body,
    );
}
