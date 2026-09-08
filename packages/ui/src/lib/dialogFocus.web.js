import { useCallback, useEffect, useRef } from 'react';

/**
 * Focus management for an overlay — the half of the dialog pattern that is easy
 * to leave out and impossible to use without.
 *
 * Every overlay in this package is portaled to `document.body` so no ancestor's
 * `overflow: hidden` can clip it. That puts the panel at the far end of the DOM,
 * nowhere near the control that opened it, which breaks the keyboard twice over:
 * Tab from the trigger walks into the rest of the page instead of into the
 * panel, and when the panel closes there is nothing sensible for focus to fall
 * back to. So opening has to place focus, Tab has to be caught at the edges, and
 * closing has to put focus back where it came from.
 *
 * Escape is bound to the panel node rather than to `document`, and stops there.
 * Two overlays both listening on `document` — a date picker inside a modal, a
 * confirm inside a drawer — would otherwise close together on one press, taking
 * the outer form's half-typed contents with them. `stopPropagation` on a
 * document-level listener cannot fix that: the listeners are on the same node,
 * so ordering decides, and ordering is mount order.
 *
 *   const { restoreFocus } = useDialogFocus(open, panelRef, {
 *       ready: !!style,                       // anchored popovers render a frame late
 *       onEscape: () => setOpen(false),
 *       initialFocus: () => panelRef.current?.querySelector('[data-day]'),
 *   });
 *
 * `trap: false` suits a menu, which the pattern says Tab should dismiss rather
 * than cycle within; pair it with `onTabOut` to do the dismissing.
 *
 * Focus comes home on close by default. The element is remembered by identity,
 * so a trigger that React replaces while the overlay is open — a row in a
 * live-synced list — can no longer be focused; pass `restoreTo` (a ref the
 * caller keeps pointing at the current node) where that matters.
 *
 * `restoreFocus()` is for closing by some other route — picking a value, hitting
 * Cancel — where focus should also come home. An outside click is the exception
 * and should not call it: the pointer has already moved focus wherever it was
 * aimed, and yanking it back would fight the user.
 */

/** Everything inside the panel that Tab should stop on, in DOM order. */
const TABBABLE = 'a[href],button,input,select,textarea,[tabindex]';

const tabbablesIn = (node) =>
    Array.from(node?.querySelectorAll(TABBABLE) ?? []).filter(
        (el) => !el.hasAttribute('disabled') && el.getAttribute('tabindex') !== '-1',
    );

export function useDialogFocus(open, panelRef, {
    onEscape, onTabOut, initialFocus, restoreTo, ready = true, trap = true, restoreOnClose = true,
} = {}) {
    /** Who had focus before this opened, so closing can hand it back. */
    const returnToRef = useRef(null);

    /**
     * The key handlers live in a ref rather than the effect's deps. Callers pass
     * inline arrows, so depending on their identity tore the listener off the
     * panel and re-attached it on every render — and with one ActionMenu per
     * table row, a 50-row list ran the effect 50 times per render to do it.
     */
    const handlers = useRef(null);
    handlers.current = { onEscape, onTabOut };

    const restoreFocus = useCallback(() => {
        // `restoreTo` wins: a caller that can name the control itself survives a
        // re-render of the thing it lives in, which the remembered node may not.
        const el = restoreTo?.current ?? returnToRef.current;
        // A trigger can be gone by now — a row menu whose row was deleted by the
        // very action that closed it, or a row in a live-synced list that React
        // replaced while the overlay was open. Focusing a detached node does
        // nothing but silently drop focus to <body>, so don't pretend.
        if (el && el.isConnected) el.focus();
    }, [restoreTo]);

    /**
     * Remember who had focus before this opened, and hand it back when it shuts.
     * Closing is the easy half to forget: the control that was focused is inside
     * the panel being unmounted, so focus falls to `<body>` and the next Tab
     * starts again from the top of the page.
     *
     * `restoreOnClose: false` is for an overlay that decides for itself — one
     * that should come home from Escape but not from a click that landed
     * somewhere else on purpose.
     */
    const wasOpenRef = useRef(false);
    useEffect(() => {
        if (open) {
            wasOpenRef.current = true;
            returnToRef.current = document.activeElement;
            return;
        }
        if (!wasOpenRef.current) return;
        wasOpenRef.current = false;
        if (restoreOnClose) restoreFocus();
    }, [open, restoreOnClose, restoreFocus]);

    // Place focus once the panel is actually in the DOM. `ready` covers the
    // anchored popovers, which render nothing until their position is measured.
    useEffect(() => {
        if (!open || !ready) return;
        const node = panelRef.current;
        if (!node) return;
        const target = initialFocus?.() ?? tabbablesIn(node)[0] ?? node;
        target?.focus();
        // Deliberately not re-running on `initialFocus`: callers pass an inline
        // arrow, and a new identity every render would re-steal focus mid-use.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, ready, panelRef]);

    useEffect(() => {
        const node = panelRef.current;
        if (!open || !ready || !node) return undefined;

        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                handlers.current.onEscape?.();
                return;
            }
            if (e.key !== 'Tab') return;
            // A menu is not modal: the pattern says Tab dismisses it and carries
            // on through the page, so those callers opt out of the trap.
            if (!trap) { handlers.current.onTabOut?.(); return; }
            const list = tabbablesIn(node);
            if (!list.length) return;
            const edge = e.shiftKey ? list[0] : list[list.length - 1];
            if (document.activeElement !== edge) return;
            e.preventDefault();
            (e.shiftKey ? list[list.length - 1] : list[0]).focus();
        };

        node.addEventListener('keydown', onKey);
        return () => node.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, ready, panelRef, trap]);

    return { restoreFocus };
}
