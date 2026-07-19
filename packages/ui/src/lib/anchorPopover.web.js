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
export function useAnchoredPopover(open, { estHeight = 340, gap = 4 } = {}) {
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
            const width = popRef.current?.offsetWidth || r.width;
            const flip = r.bottom + gap + height > window.innerHeight && r.top - gap - height > 0;
            setStyle({
                position: 'fixed',
                left: Math.max(8, Math.min(r.left, window.innerWidth - width - 8)),
                top: flip ? r.top - gap - height : r.bottom + gap,
                minWidth: r.width,
                zIndex: 80,
            });
        };
        measure();
        // Re-measure once the popover has painted with its real dimensions.
        const raf = requestAnimationFrame(measure);
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [open, estHeight, gap]);

    return { anchorRef, popRef, style };
}
