import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const GAP = 10;
const MARGIN = 8; // keep this far from the viewport edges

/**
 * Hover/focus label for controls that show no text of their own — icon-only
 * buttons, a collapsed sidebar, truncated cells.
 *
 * The bubble is rendered into a portal with `position: fixed`, so it escapes
 * `overflow: hidden` ancestors (the sidebar nav clips its own children) and
 * never needs a z-index war with the rest of the page.
 *
 *   <Tooltip label={__('Analytics')} placement="right">
 *       <Link href="/analytics"><Icon … /></Link>
 *   </Tooltip>
 *
 * A tooltip is not an accessible name: a control with no text of its own still
 * needs its own `aria-label`.
 */
export default function Tooltip({
    label,
    children,
    placement = 'top',
    delay = 120,
    className = '',
    disabled = false,
}) {
    const anchorRef = useRef(null);
    const bubbleRef = useRef(null);
    const timerRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const id = useId();

    const active = open && !disabled && !!label;

    const clearTimer = () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    };

    const show = useCallback(() => {
        clearTimer();
        timerRef.current = setTimeout(() => setOpen(true), delay);
    }, [delay]);

    const hide = useCallback(() => {
        clearTimer();
        setOpen(false);
    }, []);

    useEffect(() => clearTimer, []);

    // Position against the anchor once the bubble has a measurable size, then
    // clamp it into the viewport so edge items stay readable.
    useLayoutEffect(() => {
        if (!active) return;

        const place = () => {
            // The wrapper is `display: contents` (so it never disturbs the
            // caller's layout) and therefore has no box of its own — measure the
            // wrapped element instead.
            const el = anchorRef.current?.firstElementChild ?? anchorRef.current;
            const anchor = el?.getBoundingClientRect();
            const bubble = bubbleRef.current?.getBoundingClientRect();
            if (!anchor || !bubble) return;

            let top;
            let left;
            switch (placement) {
                case 'right':
                    top = anchor.top + anchor.height / 2 - bubble.height / 2;
                    left = anchor.right + GAP;
                    break;
                case 'left':
                    top = anchor.top + anchor.height / 2 - bubble.height / 2;
                    left = anchor.left - GAP - bubble.width;
                    break;
                case 'bottom':
                    top = anchor.bottom + GAP;
                    left = anchor.left + anchor.width / 2 - bubble.width / 2;
                    break;
                default:
                    top = anchor.top - GAP - bubble.height;
                    left = anchor.left + anchor.width / 2 - bubble.width / 2;
            }

            const maxLeft = window.innerWidth - bubble.width - MARGIN;
            const maxTop = window.innerHeight - bubble.height - MARGIN;
            setPos({
                top: Math.max(MARGIN, Math.min(top, maxTop)),
                left: Math.max(MARGIN, Math.min(left, maxLeft)),
            });
        };

        place();

        // Any scroll (capture: catches scrollable ancestors too) or resize
        // invalidates the anchor rect — drop the bubble rather than let it drift.
        window.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
        return () => {
            window.removeEventListener('scroll', hide, true);
            window.removeEventListener('resize', hide);
        };
    }, [active, placement, label, hide]);

    // Escape closes it, matching the rest of the app's overlays.
    useEffect(() => {
        if (!active) return;
        const onKey = (e) => {
            if (e.key === 'Escape') hide();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [active, hide]);

    return (
        <>
            <span
                ref={anchorRef}
                className={`contents ${className}`.trim()}
                onMouseEnter={show}
                onMouseLeave={hide}
                onFocus={show}
                onBlur={hide}
                onPointerDown={hide}
                aria-describedby={active ? id : undefined}
            >
                {children}
            </span>

            {active &&
                createPortal(
                    <span
                        ref={bubbleRef}
                        id={id}
                        role="tooltip"
                        style={{ top: pos.top, left: pos.left }}
                        className="fixed z-[100] max-w-xs px-2.5 py-1.5 rounded-om-sm bg-om-ink text-om-on-ink
                                   text-xs font-medium whitespace-nowrap pointer-events-none
                                   shadow-[0_18px_44px_-18px_rgba(0,0,0,.45)]"
                    >
                        {label}
                    </span>,
                    document.body,
                )}
        </>
    );
}
