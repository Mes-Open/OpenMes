/**
 * ConfirmDialog — Geist White system (design ref: OpenMES Components.dc.html §09
 * confirm-modal specimen; the native twin follows the §10 "Alert dialog").
 *
 * Card over scrim: 40px radius-11 icon square (blockedBg/blocked "!"), 17px
 * semibold title, 12.5px muted body, right-aligned Cancel (secondary) +
 * confirm (blocked bg, white text when `destructive`). Reuses ../Button.
 * API is identical to the native twin (index.native.tsx).
 */
import { useCallback, useId, useRef } from 'react';
import { createPortal } from 'react-dom';

import { useDialogFocus } from '../lib/dialogFocus.web.js';
import { Button } from '../Button';

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    children,
    confirmLabel,
    cancelLabel,
    /** Ref to the control that opened this, for pages whose trigger re-renders. */
    restoreTo,
    destructive = true,
    icon = '!',
    className = '',
    ...props
}) {
    const panelRef = useRef(null);
    const cancelRef = useRef(null);
    const uid = useId();
    const titleId = `${uid}-title`;
    const bodyId = `${uid}-body`;

    const escape = useCallback(() => onClose?.(), [onClose]);
    // Focus opens on Cancel, not on the confirm button: this dialog exists to
    // ask before something irreversible, and the default target should be the
    // answer that changes nothing.
    useDialogFocus(open, panelRef, { onEscape: escape, restoreTo, initialFocus: () => cancelRef.current });

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(10,9,8,0.4)] p-6" onClick={onClose}>
            <div
                ref={panelRef}
                role="alertdialog"
                aria-modal="true"
                // See Modal: keeps focus (and therefore Escape) inside the panel
                // when the pointer lands on the question or the consequence text.
                tabIndex={-1}
                // An alertdialog is required to name *and* describe itself — the
                // question is the name, the consequence underneath is the
                // description, and it is the half that decides the answer.
                aria-labelledby={titleId}
                aria-describedby={children != null ? bodyId : undefined}
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
                <div id={titleId} className="mb-[7px] text-[17px] font-semibold tracking-[-0.01em] text-om-ink">{title}</div>
                {children != null && <p id={bodyId} className="m-0 mb-[18px] text-[12.5px] leading-[1.5] text-om-muted">{children}</p>}
                <div className="flex justify-end gap-[9px]">
                    <Button ref={cancelRef} variant="secondary" onClick={onClose}>
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
