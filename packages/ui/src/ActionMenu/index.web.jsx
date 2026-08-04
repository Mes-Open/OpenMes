/**
 * ActionMenu — Geist White system (design ref: OpenMES Components.dc.html §07).
 *
 * Trigger node + 184px menu card (radius 12, menu shadow, 6px padding); items
 * 13px ink radius-8 rows with chip hover, destructive items in blocked,
 * hairline dividers via `{ divider: true }`. An item carrying `href` renders as
 * a link (through `linkAs`, default `a`) rather than a button, so navigating
 * items keep new-tab/copy-address; `icon` puts a Lucide glyph before the label.
 * Closes on outside click/Escape.
 * API is identical to the native twin (index.native.tsx).
 *
 * The card is portaled and positioned against the trigger, like Dropdown and
 * DatePicker: a menu opened from a row inside a scrolling table would otherwise
 * be clipped by the scroll container, which is exactly where row menus live.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { Icon } from '../Icon';
import { useAnchoredPopover } from '../lib/anchorPopover.web';

/** Card width — declared so the popover clamps correctly on its first pass. */
const MENU_WIDTH = 184;

export function ActionMenu({ trigger, items, linkAs: LinkAs = 'a', className = '', ...props }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const { anchorRef, popRef, style } = useAnchoredPopover(open, { estHeight: 220, estWidth: MENU_WIDTH });

    useEffect(() => {
        if (!open) return;
        const onDown = (e) => {
            // The card is portaled — it is NOT inside rootRef's subtree.
            if (rootRef.current?.contains(e.target) || popRef.current?.contains(e.target)) return;
            setOpen(false);
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
    }, [open, popRef]);

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
            <span ref={anchorRef} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
                {trigger}
            </span>
            {open && style && createPortal(
                <div
                    ref={popRef}
                    role="menu"
                    style={style}
                    className="w-[184px] rounded-om border border-om-line bg-om-card p-[6px] shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)]"
                >
                    {items.map((item, i) =>
                        item.divider ? (
                            <div key={item.key ?? `divider-${i}`} aria-hidden className="my-[5px] h-px bg-om-line2" />
                        ) : (
                            // An item that navigates renders as a real link, so it
                            // keeps what a link is for: middle-click, ⌘-click, "open
                            // in new tab", "copy address", the URL in the status bar.
                            // `linkAs` keeps the design system router-agnostic — the
                            // app hands it Inertia's Link.
                            item.href && !item.disabled ? (
                                <LinkAs
                                    key={item.key ?? `item-${i}`}
                                    href={item.href}
                                    role="menuitem"
                                    onClick={() => setOpen(false)}
                                    className={`flex w-full cursor-pointer items-center gap-[9px] rounded-om-sm px-[11px] py-[9px] text-left text-[13px] no-underline ${itemColor(item)}`}
                                >
                                    {item.icon && <Icon name={item.icon} size={14} className="shrink-0" />}
                                    {item.label}
                                </LinkAs>
                            ) : (
                                <button
                                    key={item.key ?? `item-${i}`}
                                    type="button"
                                    role="menuitem"
                                    disabled={item.disabled}
                                    onClick={() => select(item)}
                                    className={`flex w-full cursor-pointer items-center gap-[9px] rounded-om-sm px-[11px] py-[9px] text-left text-[13px] ${itemColor(item)}`}
                                >
                                    {item.icon && <Icon name={item.icon} size={14} className="shrink-0" />}
                                    {item.label}
                                </button>
                            )
                        ),
                    )}
                </div>,
                document.body,
            )}
        </div>
    );
}
