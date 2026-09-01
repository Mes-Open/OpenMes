import { useEffect, useRef, useState } from 'react';

/**
 * Anchored-popover positioning for portaled menus (web only): the popover is
 * rendered into document.body so no ancestor `overflow: hidden` (modals,
 * sheets, table cells) can clip it, with fixed coordinates measured from the
 * trigger — flipped above when the viewport bottom would cut it off, clamped
 * to the right edge, re-measured on scroll/resize while open.
 *
 * Returns { anchorRef, popRef, style }: attach anchorRef to the trigger,
 * popRef + style to the portaled popover, and render it only while `style`
 * is set. Outside-click handlers must check BOTH refs (the popover no longer
 * lives inside the trigger's subtree).
 */
export function useAnchoredPopover(open, { estHeight = 340, estWidth = 0, gap = 4 } = {}) {
    const anchorRef = useRef(null);
    const popRef = useRef(null);
    const [style, setStyle] = useState(null);

    useEffect(() => {
        if (!open) {
            setStyle(null);
            return undefined;
        }
        const measure = () => {
            const el = anchorRef.current;
            if (!el) return;
            const r = el.getBoundingClientRect();
            const height = popRef.current?.offsetHeight || estHeight;
            // The first pass runs before the popover exists (it only renders once
            // `style` is set), so it falls back to the trigger's own width. That is
            // fine for a popover as wide as its trigger, but a narrow trigger with
            // a wide card — an icon button opening a menu — would measure 34px,
            // skip the clamp and hang off the right edge for a frame. `estWidth`
            // lets such a caller declare the card's real width up front.
            const width = popRef.current?.offsetWidth || estWidth || r.width;
            const flip = r.bottom + gap + height > window.innerHeight && r.top - gap - height > 0;
            // Flipping needs room above; when there is none either way — a short
            // viewport, a landscape tablet — the popover would hang off the bottom
            // at `position: fixed` with nothing to scroll it back, taking the last
            // items in the card (often the destructive one) out of reach. Clamp
            // into the viewport as the horizontal axis already does.
            const wanted = flip ? r.top - gap - height : r.bottom + gap;
            setStyle({
                position: 'fixed',
                left: Math.max(8, Math.min(r.left, window.innerWidth - width - 8)),
                top: Math.max(8, Math.min(wanted, window.innerHeight - height - 8)),
                minWidth: r.width,
                zIndex: 80,
            });
        };
        measure();
        // Re-measure once the popover has painted with its real dimensions.
        // A single rAF is not enough: it can fire before React commits the
        // portal (the popover only renders once `style` is set), leaving the
        // estHeight-based flip in place until the next scroll — a menu opened
        // mid-viewport then hangs far above its trigger. A ResizeObserver on
        // the popover fires exactly when it first gets laidout (and again if
        // its content changes), so the position always settles on real sizes.
        const raf = requestAnimationFrame(measure);
        const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(measure) : null;
        let watchRaf = 0;
        const watch = () => {
            if (popRef.current) ro?.observe(popRef.current);
            else watchRaf = requestAnimationFrame(watch);
        };
        if (ro) watch();
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);
        return () => {
            cancelAnimationFrame(raf);
            cancelAnimationFrame(watchRaf);
            ro?.disconnect();
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [open, estHeight, estWidth, gap]);

    return { anchorRef, popRef, style };
}
