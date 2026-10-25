import { useEffect, useState } from 'react';

/**
 * The rendered width in px of the element the returned callback ref is put on,
 * following resizes. `fallback` until it has been measured. A callback ref, so
 * an element that mounts later (after an empty state) is still observed.
 */
export default function useWidth(fallback) {
    const [el, setEl] = useState(null);
    const [width, setWidth] = useState(fallback);

    useEffect(() => {
        if (!el || typeof ResizeObserver === 'undefined') return undefined;
        const observer = new ResizeObserver(([entry]) => {
            setWidth(Math.max(300, Math.round(entry.contentRect.width)));
        });
        observer.observe(el);
        return () => observer.disconnect();
    }, [el]);

    return [setEl, width];
}
