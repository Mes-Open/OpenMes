import { useContext, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import { PAGE_TITLE_SLOT, PageTitleContext } from '../layouts/AppLayout';

/**
 * Renders a page's heading into the app header's title slot, so it shares that
 * bar with the clock instead of costing the content area a row of its own.
 *
 * There is one slot per breakpoint — the desktop bar is `hidden lg:flex`, the
 * mobile header `lg:hidden` — and the heading is portaled into every one of
 * them. Only the visible slot shows, so CSS decides which without this
 * component knowing anything about breakpoints.
 *
 * Slots are looked up after mount rather than during render: they belong to the
 * layout, which is a parent, so they exist by the time effects run but not
 * necessarily on first render.
 *
 * A layout with no slot at all (the operator shell, print views) falls back to
 * rendering the heading in place, so a page never silently loses its title.
 */
export default function PageTitle({ children }) {
    const [slots, setSlots] = useState(null);
    const { register } = useContext(PageTitleContext);

    useEffect(() => {
        setSlots([...document.querySelectorAll(PAGE_TITLE_SLOT)]);
    }, []);

    // Tells the layout to stand down its nav-derived trail: this page has said
    // what it is, and it knows things the menu doesn't (which record you opened).
    useEffect(() => register(), [register]);

    // A string is a title and gets a heading; an element (a breadcrumb trail)
    // goes in as-is. Wrapping the latter would put a <nav> inside an <h1>, which
    // is invalid — a heading takes phrasing content, not flow content.
    const heading = typeof children === 'string'
        ? (
            <h1 className="truncate text-[15px] font-semibold tracking-[-0.01em] text-om-ink">
                {children}
            </h1>
        )
        : children;

    // Not looked up yet — render nothing rather than flashing the fallback into
    // the content area and then moving it.
    if (slots === null) {
        return null;
    }

    if (slots.length === 0) {
        return <div className="mb-3">{heading}</div>;
    }

    return slots.map((slot, i) => createPortal(heading, slot, `page-title-${i}`));
}
