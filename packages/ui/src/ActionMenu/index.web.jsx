/**
 * ActionMenu — Geist White system (design ref: OpenMES Components.dc.html §07).
 *
 * Trigger node + 184px menu card (radius 12, menu shadow, 6px padding); items
 * 13px ink radius-8 rows with chip hover, destructive items in blocked,
 * hairline dividers via `{ divider: true }`. Closes on outside click/Escape.
 * API is identical to the native twin (index.native.tsx).
 */
import { useEffect, useRef, useState } from 'react';

export function ActionMenu({ trigger, items, className = '', ...props }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e) => {
            if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const select = (item) => {
        setOpen(false);
        item.onSelect?.();
    };

    const itemColor = (item) =>
        item.disabled
            ? 'cursor-not-allowed text-om-faint'
            : item.destructive
              ? 'text-om-blocked hover:bg-om-chip'
              : 'text-om-ink hover:bg-om-chip';

    return (
        <div ref={rootRef} className={`relative inline-block ${className}`} {...props}>
            <span aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
                {trigger}
            </span>
            {open && (
                <div
                    role="menu"
                    className="absolute left-0 top-full z-50 mt-[6px] w-[184px] rounded-om border border-om-line bg-om-card p-[6px] shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)]"
                >
                    {items.map((item, i) =>
                        item.divider ? (
                            <div key={item.key ?? `divider-${i}`} aria-hidden className="my-[5px] h-px bg-om-line2" />
                        ) : (
                            <button
                                key={item.key ?? `item-${i}`}
                                type="button"
                                role="menuitem"
                                disabled={item.disabled}
                                onClick={() => select(item)}
                                className={`block w-full cursor-pointer rounded-om-sm px-[11px] py-[9px] text-left text-[13px] ${itemColor(item)}`}
                            >
                                {item.label}
                            </button>
                        ),
                    )}
                </div>
            )}
        </div>
    );
}
