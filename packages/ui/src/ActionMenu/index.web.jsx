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
 *
 * `trigger` is cloned rather than wrapped, so `aria-haspopup`/`aria-expanded`
 * land on the element that actually takes focus. On a wrapper `<span>` they
 * described nothing: the button inside announced as a plain button with no hint
 * that it opens anything.
 *
 * Keyboard follows the menu-button pattern — Enter/Space/Down open onto the
 * first item, Up onto the last, arrows and Home/End move between items on a
 * single tab stop, Escape closes and hands focus back, Tab closes and moves on.
 * Without it the items were unreachable: the card is portaled to the end of the
 * body, so Tab from the trigger skipped straight past them into the page.
 */
import { cloneElement, isValidElement, useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { Icon } from '../Icon';
import { useAnchoredPopover } from '../lib/anchorPopover.web';
import { useDialogFocus } from '../lib/dialogFocus.web.js';

/** Card width — declared so the popover clamps correctly on its first pass. */
const MENU_WIDTH = 184;

export function ActionMenu({ trigger, items, linkAs: LinkAs = 'a', className = '', ...props }) {
    const [open, setOpen] = useState(false);
    /** 'first' | 'last' — which end the menu should open onto. */
    const entryRef = useRef('first');
    const rootRef = useRef(null);
    const { anchorRef, popRef, style } = useAnchoredPopover(open, { estHeight: 220, estWidth: MENU_WIDTH });

    const menuItems = (node) => Array.from(node?.querySelectorAll('[role="menuitem"]') ?? [])
        .filter((el) => !el.hasAttribute('disabled') && el.getAttribute('aria-disabled') !== 'true');

    const close = useCallback(() => setOpen(false), []);
    const { restoreFocus } = useDialogFocus(open, popRef, {
        ready: !!style,
        trap: false,
        // Closing by clicking elsewhere must leave focus where the pointer put
        // it; only Escape and picking an item bring it home.
        restoreOnClose: false,
        onEscape: () => { close(); restoreFocus(); },
        onTabOut: close,
        initialFocus: () => {
            const list = menuItems(popRef.current);
            return entryRef.current === 'last' ? list[list.length - 1] : list[0];
        },
    });

    useEffect(() => {
        if (!open) return undefined;
        const onDown = (e) => {
            // The card is portaled — it is NOT inside rootRef's subtree.
            if (rootRef.current?.contains(e.target) || popRef.current?.contains(e.target)) return;
            setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open, popRef]);

    const openAt = (end) => { entryRef.current = end; setOpen(true); };

    const onTriggerKeyDown = (e) => {
        if (open || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
        e.preventDefault();
        openAt(e.key === 'ArrowUp' ? 'last' : 'first');
    };

    /** One tab stop in the card; the arrows move focus between the items. */
    const onMenuKeyDown = (e) => {
        const list = menuItems(popRef.current);
        if (!list.length) return;
        const i = list.indexOf(document.activeElement);
        let next;
        if (e.key === 'ArrowDown') next = (i + 1) % list.length;
        else if (e.key === 'ArrowUp') next = (i - 1 + list.length) % list.length;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = list.length - 1;
        else return;
        e.preventDefault();
        list[next]?.focus();
    };

    const select = (item) => {
        setOpen(false);
        restoreFocus();
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
            {isValidElement(trigger)
                ? cloneElement(trigger, {
                    ref: anchorRef,
                    'aria-haspopup': 'menu',
                    'aria-expanded': open,
                    onClick: (e) => { trigger.props.onClick?.(e); setOpen((o) => !o); },
                    onKeyDown: (e) => { trigger.props.onKeyDown?.(e); onTriggerKeyDown(e); },
                })
                // A trigger that isn't an element (a bare string) can't carry the
                // state, but must still be clickable rather than silently inert.
                : (
                    <button type="button" ref={anchorRef} aria-haspopup="menu" aria-expanded={open}
                        onClick={() => setOpen((o) => !o)} onKeyDown={onTriggerKeyDown}>
                        {trigger}
                    </button>
                )}
            {open && style && createPortal(
                <div
                    ref={popRef}
                    role="menu"
                    onKeyDown={onMenuKeyDown}
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
                                    tabIndex={-1}
                                    onClick={() => { setOpen(false); }}
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
                                    tabIndex={-1}
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
