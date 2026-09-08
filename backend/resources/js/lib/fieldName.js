import { cloneElement, isValidElement } from 'react';

/**
 * Hand a field's caption to the control it describes.
 *
 * A `<label>` can only point at a native form element, and half this app's
 * fields are custom components — a Dropdown, a date picker — that `htmlFor`
 * cannot target. So the caption is rendered as plain text and the control is
 * given the name directly.
 *
 * This lives in one place because the alternative was thirteen copies, and the
 * copies had already drifted: one page passes a JSX fragment as its caption
 * ("Password" plus a muted "(leave blank to keep current)"), which a naive
 * `aria-label={label}` renders as the string "[object Object]" — an accessible
 * name worse than none. `captionText` flattens that back to real words.
 */

/** The plain string behind a caption that may be a string, a number or JSX. */
export function captionText(label) {
    if (label == null || typeof label === 'boolean') return '';
    if (typeof label === 'string' || typeof label === 'number') return String(label);
    if (Array.isArray(label)) return label.map(captionText).join(' ');
    if (isValidElement(label)) return captionText(label.props?.children);
    return '';
}

/**
 * `children` with the caption applied as its accessible name.
 *
 * Left untouched when the caption yields no words, when the child is not a
 * single element (a control plus a suffix, say), or when the caller already
 * named it — an explicit `aria-label` on the control always wins.
 */
export function nameControl(children, label) {
    const name = captionText(label).replace(/\s+/g, ' ').trim();
    if (!name || !isValidElement(children) || children.props['aria-label']) return children;
    return cloneElement(children, { 'aria-label': name });
}
