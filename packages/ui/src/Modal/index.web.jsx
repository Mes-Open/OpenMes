/**
 * Modal — Geist White system (design ref: OpenMES Components.dc.html §09
 * form-modal specimen).
 *
 * Form-modal shell: header (15px semibold title + optional mono 9.5px
 * subtitle, × close, line2 hairline), body (children), footer (right-aligned
 * actions, top hairline, panel bg) over the scrim token.
 *
 * `side` turns the same shell into an edge drawer — the panel is full height,
 * flush to one edge, and slides in. Nothing else about it changes: the header,
 * the scrollable body, the footer, the focus trap and `keepMounted` all behave
 * as they do in the centered form.
 *
 * API is identical to the native twin (index.native.tsx).
 */
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { useDialogFocus } from '../lib/dialogFocus.web.js';
import { Icon } from '../Icon';

/**
 * How long the slide runs. The number is repeated in the `duration-[260ms]`
 * classes below rather than interpolated — Tailwind scans this file as text and
 * only emits utilities it can read literally.
 */
const SLIDE_MS = 260;

export function Modal({
    open,
    onClose,
    title,
    subtitle,
    footer,
    children,
    closeLabel,
    /**
     * Which edge the panel sits on. `'center'` (the default) is the classic
     * form modal; `'right'` / `'left'` make it a full-height drawer that slides
     * in from that edge — the shape to reach for when the form is long enough
     * that a centered card would scroll inside a scroll.
     */
    side = 'center',
    /**
     * Cap on the panel's width — a number (px) or any CSS length. Applied as an
     * inline style rather than a class on purpose: `max-w-[560px]` from a caller
     * and `max-w-[480px]` from the base are two arbitrary utilities of equal
     * specificity, so which one wins is decided by the order Tailwind happened to
     * emit them in — ascending by value, today. That silently loses for any
     * caller asking for something *narrower* than the default.
     */
    width,
    /**
     * Keep the children mounted (hidden) after the first open, so a half-filled
     * form survives a stray click on the scrim.
     *
     * Off by default, and deliberately so: a modal opened per row (`open={holdFor
     * != null}`) reuses one instance for every record, and retained state there
     * would carry one row's typed values onto the next. Only turn it on where the
     * modal always edits the same thing — a "new X" form.
     */
    /** Ref to the control that opened this, for pages whose trigger re-renders. */
    restoreTo,
    keepMounted = false,
    className = '',
    style,
    ...props
}) {
    // Nothing is mounted until the first open, so `keepMounted` costs nothing on
    // pages where the modal is never used.
    const opened = useRef(false);
    if (open) opened.current = true;

    const panelRef = useRef(null);
    const uid = useId();
    const titleId = `${uid}-title`;
    const subtitleId = `${uid}-subtitle`;

    const escape = useCallback(() => onClose?.(), [onClose]);
    useDialogFocus(open, panelRef, { onEscape: escape, restoreTo });

    const drawer = side === 'right' || side === 'left';

    /**
     * A drawer has to be in the DOM at its offscreen position for one frame
     * before the transform can transition, and has to stay there for the length
     * of the slide on the way out. `shown` drives the transform, `closing` keeps
     * the panel rendered while it slides away.
     */
    const [shown, setShown] = useState(false);
    const [closing, setClosing] = useState(false);
    const wasOpen = useRef(false);
    useEffect(() => {
        if (!drawer) return undefined;
        if (open) {
            wasOpen.current = true;
            setClosing(false);
            const frame = requestAnimationFrame(() => setShown(true));
            return () => cancelAnimationFrame(frame);
        }
        if (!wasOpen.current) return undefined;
        wasOpen.current = false;
        setShown(false);
        setClosing(true);
        const timer = setTimeout(() => setClosing(false), SLIDE_MS);
        return () => clearTimeout(timer);
    }, [open, drawer]);

    // `closing` holds the panel through its slide-out. It's set by an effect, so
    // on the very render where `open` flips false it is still false — hence
    // `wasOpen`, which is already true by then. Without it a drawer that isn't
    // `keepMounted` unmounts for a frame and remounts offscreen, and the slide
    // out never plays.
    if (!open && !closing && !wasOpen.current && !(keepMounted && opened.current)) return null;

    const visible = open || closing || (drawer && wasOpen.current);

    const scrim = drawer
        ? `${side === 'right' ? 'justify-end' : 'justify-start'} transition-opacity duration-[260ms] ${shown ? 'opacity-100' : 'opacity-0'}`
        : 'items-center justify-center p-6';

    // The base cap steps aside when the caller names a width, so the inline
    // style is the only thing setting one.
    const cap = width != null ? '' : drawer ? 'max-w-[480px]' : 'max-w-[440px]';

    const panel = drawer
        ? [
            'h-full rounded-none',
            cap,
            side === 'right' ? 'border-l' : 'border-r',
            side === 'right'
                ? 'shadow-[-20px_0_50px_-20px_rgba(0,0,0,.35)]'
                : 'shadow-[20px_0_50px_-20px_rgba(0,0,0,.35)]',
            'transition-transform duration-[260ms] ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none',
            shown ? 'translate-x-0' : side === 'right' ? 'translate-x-full' : '-translate-x-full',
        ].join(' ')
        // Capped to the viewport with only the body scrolling, so a long form
        // keeps its header and footer actions in reach.
        : `max-h-[88vh] ${cap} rounded-om border shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)]`;

    return createPortal(
        // `hidden` (not unmount) while retained: display:none also drops the
        // subtree from the tab order and the accessibility tree.
        <div
            className={`fixed inset-0 z-50 bg-[rgba(10,9,8,0.4)] ${scrim} ${visible ? 'flex' : 'hidden'}`}
            onClick={onClose}
        >
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                // Focusable so a mousedown on inert content inside — a heading, a
                // caption — lands on the panel instead of blurring to <body>.
                // Escape is bound to this node, and would stop working if focus
                // ever left it while the dialog was still open.
                tabIndex={-1}
                // The heading is the dialog's name. Without this the whole thing
                // announces as an unnamed dialog and the reader has to go looking
                // for what it is about.
                aria-labelledby={title != null ? titleId : undefined}
                aria-describedby={subtitle != null ? subtitleId : undefined}
                className={`flex w-full flex-col overflow-hidden border-om-line bg-om-card ${panel} ${className}`}
                style={width != null ? { maxWidth: typeof width === 'number' ? `${width}px` : width, ...style } : style}
                onClick={(e) => e.stopPropagation()}
                {...props}
            >
                <div className="flex shrink-0 items-center justify-between border-b border-om-line2 px-[18px] py-4">
                    <div>
                        <div id={titleId} className="text-[15px] font-semibold text-om-ink">{title}</div>
                        {subtitle != null && <div id={subtitleId} className="mt-[3px] font-mono text-[9.5px] text-om-faint">{subtitle}</div>}
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
