/**
 * ConfirmDialog — Geist White system (design ref: OpenMES Components.dc.html §09
 * confirm-modal specimen; the native twin follows the §10 "Alert dialog").
 *
 * Card over scrim: 40px radius-11 icon square (blockedBg/blocked "!"), 17px
 * semibold title, 12.5px muted body, right-aligned Cancel (secondary) +
 * confirm (blocked bg, white text when `destructive`). Reuses ../Button.
 * API is identical to the native twin (index.native.tsx).
 */
import { useEffect } from 'react';
import { createPortal } from 'react-dom';

import { Button } from '../Button';

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    children,
    confirmLabel,
    cancelLabel,
    destructive = true,
    icon = '!',
    className = '',
    ...props
}) {
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
                role="alertdialog"
                aria-modal="true"
                className={`w-full max-w-[380px] rounded-om border border-om-line bg-om-card p-[22px] shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] ${className}`}
                onClick={(e) => e.stopPropagation()}
                {...props}
            >
                <div
                    aria-hidden
                    className="mb-[14px] flex size-10 items-center justify-center rounded-[11px] bg-om-blocked-bg text-[20px] font-bold text-om-blocked"
                >
                    {icon}
                </div>
                <div className="mb-[7px] text-[17px] font-semibold tracking-[-0.01em] text-om-ink">{title}</div>
                {children != null && <p className="m-0 mb-[18px] text-[12.5px] leading-[1.5] text-om-muted">{children}</p>}
                <div className="flex justify-end gap-[9px]">
                    <Button variant="secondary" onClick={onClose}>
                        {cancelLabel}
                    </Button>
                    {destructive ? (
                        <Button
                            variant="danger"
                            className="bg-om-blocked! text-white! hover:bg-[#c23c29]! hover:brightness-100"
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    ) : (
                        <Button variant="primary" onClick={onConfirm}>
                            {confirmLabel}
                        </Button>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
