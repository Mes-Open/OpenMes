import { useCallback, useRef } from 'react';

/**
 * Arrow-key navigation over a set of sibling controls that share **one** tab
 * stop — the half of the radiogroup and tabs patterns that is invisible until
 * someone puts the mouse down.
 *
 * A group of radios or tabs is one stop in the tab sequence, not one per option:
 * Tab moves past the whole group, and the arrows choose within it. Rendered as
 * plain buttons they are each independently tabbable instead, so a seven-option
 * segmented control costs seven presses to get past and the arrows do nothing —
 * which is also how a screen reader user is told these options belong together.
 *
 *   const { containerProps, itemProps } = useRovingFocus(options.length, index, onChange, {
 *       columns: 1,   // >1 makes Up/Down step a row instead of an item
 *   });
 *   <div role="radiogroup">{options.map((o, i) => <button {...itemProps(i)} … />)}</div>
 *
 * Selection follows focus, which is what the radio-group and tabs patterns both
 * do here — and what the month/year quick-pick wants too, since its "selection"
 * is just where the cursor is. A caller needing manual activation (Enter to
 * commit) would add that option then, with a test behind it.
 */
export function useRovingFocus(count, activeIndex, onSelect, { columns = 1 } = {}) {
    const containerRef = useRef(null);

    /**
     * Focus is moved here and now, not queued for after the render. Selecting
     * does not add or remove items — it only changes which one holds the tab
     * stop — so the element is already in the DOM either way. Deferring it to an
     * effect meant a keypress that produced no re-render (an `onChange` the
     * caller omitted, or arrowing onto the option already selected) left the
     * index armed, and the next unrelated render pulled focus into the group out
     * of nowhere.
     */
    const move = useCallback((i, items) => {
        onSelect?.(i);
        (items ?? containerRef.current?.querySelectorAll('[data-roving]'))?.[i]?.focus();
    }, [onSelect]);

    const onKeyDown = useCallback((e) => {
        if (!count) return;
        // The index holding the tab stop is also where the arrows start from —
        // every caller moves the two together.
        const items = containerRef.current?.querySelectorAll('[data-roving]');
        const from = Math.max(0, activeIndex);
        let next;
        switch (e.key) {
            case 'ArrowLeft': next = from - 1; break;
            case 'ArrowRight': next = from + 1; break;
            case 'ArrowUp': next = from - columns; break;
            case 'ArrowDown': next = from + columns; break;
            case 'Home': next = 0; break;
            case 'End': next = count - 1; break;
            default: return;
        }
        // Claimed either way: the arrows would otherwise scroll the page under a
        // group that looked like it was handling them.
        e.preventDefault();
        // Wrapping matches the pattern — a radio group is a ring, and arrowing
        // off one end should not strand focus at the edge.
        if (columns === 1) next = (next + count) % count;
        else if (next < 0 || next >= count) return;
        move(next, items);
    }, [count, activeIndex, columns, move]);

    const itemProps = useCallback((i) => ({
        'data-roving': '',
        tabIndex: i === activeIndex ? 0 : -1,
    }), [activeIndex]);

    return { containerProps: { ref: containerRef, onKeyDown }, itemProps };
}
