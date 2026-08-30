import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const GAP = 10;
const MARGIN = 8; // keep this far from the viewport edges
/** Grace period for the pointer to cross the gap between anchor and panel. */
const CLOSE_DELAY = 180;

/**
 * Hover-opened panel — a tooltip you can put things in and then reach with the
 * mouse.
 *
 * `Tooltip` deliberately can't do this: its bubble is `pointer-events: none` and
 * takes a string, which is right for a label and wrong for a list you might want
 * to click. This keeps the same portal-and-clamp positioning (so it escapes the
 * sidebar's `overflow: hidden` without a z-index war) and adds the two things
 * that make a panel usable: it stays open while the pointer is inside it, and a
 * short grace period covers the gap on the way over.
 *
 *   <HoverPanel placement="right" panel={<LatestAlerts />}>
 *       <Link href="/admin/alerts">…</Link>
 *   </HoverPanel>
 *
 * Hover is not an interaction every input can perform, so whatever this decorates
 * must stand on its own — here the nav item still navigates to the full page.
 */
export default function HoverPanel({
    panel,
    children,
    placement = 'right',
    delay = 140,
    disabled = false,
}) {
    const anchorRef = useRef(null);
    const panelRef = useRef(null);
    const openTimer = useRef(null);
    const closeTimer = useRef(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const id = useId();

    const active = open && !disabled && !!panel;

    const clear = () => {
        clearTimeout(openTimer.current);
        clearTimeout(closeTimer.current);
    };

    const show = useCallback(() => {
        clear();
        openTimer.current = setTimeout(() => setOpen(true), delay);
    }, [delay]);

    /** Delayed so moving the pointer from the anchor onto the panel doesn't close it. */
    const scheduleHide = useCallback(() => {
        clear();
        closeTimer.current = setTimeout(() => setOpen(false), CLOSE_DELAY);
    }, []);

    const hideNow = useCallback(() => {
        clear();
        setOpen(false);
    }, []);

    useEffect(() => clear, []);

    useLayoutEffect(() => {
        if (!active) return undefined;

        const place = () => {
            // The wrapper is `display: contents`, so it has no box of its own —
            // measure the element it wraps.
            const el = anchorRef.current?.firstElementChild ?? anchorRef.current;
            const anchor = el?.getBoundingClientRect();
            const box = panelRef.current?.getBoundingClientRect();
            if (!anchor || !box) return;

            // Top-aligned, not centred: a panel is many times taller than the
            // control that opens it, and centring one on a nav item near the top
            // of the sidebar just pins it to the viewport edge, pointing at
            // nothing. Sharing a top edge is what reads as "this belongs to that".
            const top = placement === 'right' || placement === 'left'
                ? anchor.top
                : anchor.bottom + GAP;
            const left = placement === 'left'
                ? anchor.left - GAP - box.width
                : placement === 'right'
                    ? anchor.right + GAP
                    : anchor.left;

            setPos({
                top: Math.max(MARGIN, Math.min(top, window.innerHeight - box.height - MARGIN)),
                left: Math.max(MARGIN, Math.min(left, window.innerWidth - box.width - MARGIN)),
            });
        };

        place();

        // Any scroll or resize invalidates the anchor rect — drop the panel
        // rather than let it drift away from what it belongs to.
        window.addEventListener('scroll', hideNow, true);
        window.addEventListener('resize', hideNow);
        return () => {
            window.removeEventListener('scroll', hideNow, true);
            window.removeEventListener('resize', hideNow);
        };
    }, [active, placement, panel, hideNow]);

    useEffect(() => {
        if (!active) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') hideNow();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [active, hideNow]);

    return (
        <>
            <span
                ref={anchorRef}
                className="contents"
                onMouseEnter={show}
                onMouseLeave={scheduleHide}
                onFocus={show}
                onBlur={scheduleHide}
                aria-describedby={active ? id : undefined}
            >
                {children}
            </span>

            {active &&
                createPortal(
                    <div
                        ref={panelRef}
                        id={id}
                        style={{ top: pos.top, left: pos.left }}
                        onMouseEnter={clear}
                        onMouseLeave={scheduleHide}
                        // Clicking through to a record navigates away; the panel
                        // would otherwise linger over the new page.
                        onClick={hideNow}
                        className="fixed z-[100] overflow-hidden rounded-om border border-om-line bg-om-card
                                   shadow-[0_18px_44px_-18px_rgba(0,0,0,.45)]"
                    >
                        {panel}
                    </div>,
                    document.body,
                )}
        </>
    );
}
