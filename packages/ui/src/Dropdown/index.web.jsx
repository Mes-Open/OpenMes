/**
 * Dropdown — Geist White system (design ref: OpenMES Components.dc.html §13).
 *
 * Custom select replacing native <select>: bg trigger with 1px line border and
 * a faint chevron that rotates 180° while the menu is open; menu card
 * (radius 12, menu shadow, 6px padding) that fades/scales in. Single mode shows
 * chip-bg semibold selected rows + accent ✓; multi mode shows 17px accent
 * checkboxes — the "N selected" trigger label is the caller's job via `label`.
 * Closes on outside click/Escape; ↑/↓/Home/End move the highlight, Enter/Space
 * pick. API is identical to the native twin.
 *
 * `size="sm"` is the compact trigger used by DataTable's column-filter row —
 * mono 10.5px in a 6px-radius field, matching the design's filter controls. The
 * menu itself stays full size; only the trigger shrinks.
 *
 * Search: a long list gets a search box pinned at the top of the menu that
 * narrows the rows as the operator types (case- and accent-insensitive, on the
 * label). `searchable` is `'auto'` by default — the box appears once the list
 * is longer than `searchThreshold` rows — or `true`/`false` to force it. The
 * box takes focus when the menu opens; ↑/↓/Enter keep working from inside it.
 * The placeholder and the "nothing matches" note come from the props, else
 * from the app's `UILabelsProvider`.
 */
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { useAnchoredPopover } from '../lib/anchorPopover.web.js';
import { useUILabels } from '../lib/labels';
import { Icon } from '../Icon';

/** Lower-case, accents stripped (é→e, ł→l), so "lodz" finds "Łódź". */
export function foldForSearch(text) {
    return String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ł/g, 'l');
}

const TRIGGER_SIZE = {
    md: 'rounded-om-sm px-[13px] py-[10px] gap-[10px]',
    sm: 'rounded-[6px] px-2 py-[5px] gap-[6px]',
};
const TRIGGER_LABEL_SIZE = {
    md: 'text-[13.5px]',
    sm: 'truncate text-[13px] font-semibold',
};

export function Dropdown({
    options,
    value,
    values,
    multiple = false,
    onChange,
    label,
    placeholder,
    disabled = false,
    size = 'md',
    /** Mono uppercase caption at the top of the menu (design §13 "menu anatomy"). */
    header,
    /** Optional glyph before the trigger label — e.g. <Icon name="columns-3" />. */
    leftIcon = null,
    /** Padding/type overrides for the trigger — `className` styles the wrapper. */
    triggerClassName = '',
    className = '',
    // Named the trigger, not the wrapper — an aria-label on a plain div is ignored.
    'aria-label': ariaLabel,
    /** `'auto'` (search box once the list is longer than `searchThreshold`), `true` or `false`. */
    searchable = 'auto',
    searchThreshold = 4,
    /** Search box placeholder / empty-result note; default from `UILabelsProvider`. */
    searchPlaceholder,
    noResultsLabel,
    ...props
}) {
    const [open, setOpen] = useState(false);
    // Keyboard/hover highlight; -1 while nothing is highlighted.
    const [active, setActive] = useState(-1);
    const uid = useId();
    const listboxId = `${uid}-listbox`;
    const triggerId = `${uid}-trigger`;
    const optionId = (i) => `${uid}-opt-${i}`;
    const rootRef = useRef(null);
    const searchRef = useRef(null);
    const { anchorRef, popRef, style } = useAnchoredPopover(open, { estHeight: 320 });

    const uiLabels = useUILabels();
    const searchText = searchPlaceholder ?? uiLabels.searchPlaceholder ?? '';
    const noResultsText = noResultsLabel ?? uiLabels.noResultsLabel ?? '—';
    const showSearch = searchable === true || (searchable === 'auto' && options.length > searchThreshold);

    // The rows on screen: every option, or those whose label contains the query.
    const [query, setQuery] = useState('');
    const visible = useMemo(() => {
        const q = foldForSearch(query.trim());
        if (!showSearch || !q) return options;
        return options.filter((o) => foldForSearch(o.label).includes(q));
    }, [options, query, showSearch]);
    const search = (text) => {
        setQuery(text);
        // Highlight the first match so Enter picks it straight from the box.
        const q = foldForSearch(text.trim());
        setActive(q ? (options.some((o) => foldForSearch(o.label).includes(q)) ? 0 : -1) : -1);
    };

    const selectedValues = multiple ? (values ?? []) : [];
    const single = !multiple ? options.find((o) => o.value === value) : undefined;
    const isPlaceholder = label == null && (multiple ? selectedValues.length === 0 : !single);
    const triggerLabel = label ?? (multiple ? placeholder : (single?.label ?? placeholder));

    const pick = (option) => {
        if (multiple) {
            const next = selectedValues.includes(option.value)
                ? selectedValues.filter((v) => v !== option.value)
                : [...selectedValues, option.value];
            onChange?.(next);
        } else {
            onChange?.(option.value);
            setOpen(false);
        }
    };

    // Open with the current selection highlighted so ↑/↓ start from there.
    const toggle = () => {
        setOpen((o) => {
            if (!o) setActive(multiple ? -1 : options.findIndex((x) => x.value === value));
            return !o;
        });
    };

    // A fresh box every time the menu opens. It takes focus as it mounts (the
    // menu is portaled once its position is known, a render after `open`
    // flips, so an effect keyed on `open` would run before the box exists).
    useEffect(() => { if (!open) setQuery(''); }, [open]);
    // Not on touch screens: focusing raises the soft keyboard over the menu.
    const attachSearch = useCallback((el) => {
        searchRef.current = el;
        if (el && (typeof window === 'undefined' || !window.matchMedia || window.matchMedia('(pointer: fine)').matches)) el.focus();
    }, []);

    // Latest-props box so the document listeners below can stay registered for
    // the whole open lifetime instead of being re-bound on every render.
    const liveRef = useRef(null);
    liveRef.current = { options: visible, active, pick };

    useEffect(() => {
        if (!open) return undefined;
        const onDown = (e) => {
            // The menu is portaled — it is NOT inside rootRef's subtree.
            if (rootRef.current?.contains(e.target) || popRef.current?.contains(e.target)) return;
            setOpen(false);
        };
        const onKey = (e) => {
            const { options: opts, active: i, pick: choose } = liveRef.current;
            // Inside the search box, Space is a character and Home/End move the caret.
            const typing = searchRef.current != null && e.target === searchRef.current;
            if (e.key === 'Escape') {
                setOpen(false);
                anchorRef.current?.focus();
                return;
            }
            const step = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
            if (step) {
                if (!opts.length) return;
                e.preventDefault();
                setActive((prev) => (prev + step + opts.length) % opts.length);
            } else if (!typing && (e.key === 'Home' || e.key === 'End')) {
                if (!opts.length) return;
                e.preventDefault();
                setActive(e.key === 'Home' ? 0 : opts.length - 1);
            } else if (e.key === 'Enter' || (e.key === ' ' && !typing)) {
                if (!opts[i]) return;
                // Also stops the focused trigger's Enter/Space from re-toggling.
                e.preventDefault();
                choose(opts[i]);
            }
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, popRef, anchorRef]);

    // Keep the highlighted row in view while arrowing through a long list.
    useEffect(() => {
        if (!open || active < 0) return;
        // Query the option rows rather than indexing children — an optional
        // `header` occupies child 0 and would offset every index.
        popRef.current?.querySelectorAll('[role="option"]')[active]?.scrollIntoView({ block: 'nearest' });
    }, [open, active, popRef]);

    const rowClass = (i, tinted) =>
        `flex cursor-pointer items-center gap-[10px] rounded-[6px] px-[11px] py-[9px] transition-colors duration-100 ${
            active === i ? 'bg-om-chip' : tinted ? 'bg-om-chip' : ''
        }`;

    return (
        <div ref={rootRef} className={`relative ${className}`} {...props}>
            <button
                ref={anchorRef}
                type="button"
                disabled={disabled}
                id={triggerId}
                aria-label={ariaLabel}
                // Unlike a button, a combobox does not take its name from its own
                // text, so a trigger that was named by the word inside it ("Columns")
                // would lose that name outright. Pointing at itself puts the content
                // back into the name computation.
                aria-labelledby={ariaLabel ? undefined : triggerId}
                // `combobox`, not the implicit `button` role: `aria-activedescendant`
                // is only honoured on a role that supports it, and without it the
                // arrow keys move a highlight that a screen reader never hears —
                // focus stays on the trigger the whole time the list is open.
                role="combobox"
                aria-haspopup="listbox"
                aria-controls={open ? listboxId : undefined}
                aria-expanded={open}
                aria-activedescendant={open && active >= 0 ? optionId(active) : undefined}
                onClick={toggle}
                className={`flex w-full items-center justify-between border bg-om-bg text-left transition-[border-color,box-shadow,background-color] duration-150 ${TRIGGER_SIZE[size]} ${
                    disabled
                        ? 'cursor-not-allowed border-om-line opacity-60'
                        : open
                          ? 'cursor-pointer border-om-accent shadow-[0_0_0_3px_var(--om-accent-bg)]'
                          : 'cursor-pointer border-om-line hover:border-om-faintest hover:bg-om-card'
                } ${triggerClassName}`}
            >
                <span className="flex min-w-0 items-center gap-2">
                    {leftIcon && <span className="shrink-0 text-om-faint">{leftIcon}</span>}
                    <span className={`truncate ${TRIGGER_LABEL_SIZE[size]} ${isPlaceholder ? 'text-om-faint' : 'text-om-ink'}`}>
                        {triggerLabel}
                    </span>
                </span>
                <Icon
                    name={open ? 'chevron-up' : 'chevron-down'}
                    size={size === 'sm' ? 12 : 14}
                    stroke={1.6}
                    className={`shrink-0 transition-colors duration-200 ease-out ${open ? 'text-om-accent' : 'text-om-faint'}`}
                />
            </button>
            {open && style && createPortal(
                <div
                    ref={popRef}
                    style={style}
                    className="flex max-h-[320px] w-max max-w-[min(22rem,calc(100vw-2rem))] origin-top flex-col overflow-hidden rounded-om border border-om-line bg-om-card shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)] motion-safe:animate-om-menu-in"
                >
                    {showSearch && (
                        // Pinned above the scrolling rows, so it stays put while the list scrolls.
                        <div className="flex shrink-0 items-center gap-2 border-b border-om-line2 px-[12px] py-[8px]">
                            <Icon name="search" size={13} className="shrink-0 text-om-faint" />
                            <input
                                ref={attachSearch}
                                type="text"
                                value={query}
                                onChange={(e) => search(e.target.value)}
                                placeholder={searchText}
                                aria-label={searchText || undefined}
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded
                                aria-controls={listboxId}
                                aria-activedescendant={active >= 0 ? optionId(active) : undefined}
                                autoComplete="off"
                                spellCheck={false}
                                className="min-w-0 flex-1 bg-transparent text-[13px] text-om-ink outline-none placeholder:text-om-faint"
                            />
                            {query !== '' && (
                                <button type="button" onClick={() => search('')} className="shrink-0 text-om-faint hover:text-om-ink" aria-label={uiLabels.clearLabel ?? 'Clear'}>
                                    <Icon name="x" size={12} />
                                </button>
                            )}
                        </div>
                    )}
                    <div
                        id={listboxId}
                        role="listbox"
                        aria-label={ariaLabel}
                        aria-labelledby={ariaLabel ? undefined : triggerId}
                        aria-multiselectable={multiple || undefined}
                        className="min-h-0 flex-1 overflow-auto p-[6px]"
                    >
                    {header != null && (
                        <div className="px-[9px] pt-[7px] pb-[5px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">
                            {header}
                        </div>
                    )}
                    {showSearch && visible.length === 0 && (
                        <div className="px-[11px] py-[9px] text-[13px] text-om-faint">{noResultsText}</div>
                    )}
                    {visible.map((o, i) => {
                        const onRowHover = () => setActive(i);
                        if (multiple) {
                            const on = selectedValues.includes(o.value);
                            return (
                                <div
                                    key={o.value}
                                    id={optionId(i)}
                                    role="option"
                                    aria-selected={on}
                                    onClick={() => pick(o)}
                                    onMouseMove={onRowHover}
                                    className={`${rowClass(i, false)} gap-[11px]`}
                                >
                                    <span
                                        className={`flex size-[17px] shrink-0 items-center justify-center rounded-[5px] transition-colors duration-100 ${on ? 'bg-om-accent' : 'border-2 border-om-faintest'}`}
                                    >
                                        {on && <span className="text-[10px] font-bold leading-none text-white">✓</span>}
                                    </span>
                                    <span className="whitespace-nowrap text-[13px] text-om-ink">{o.label}</span>
                                </div>
                            );
                        }
                        const selected = o.value === value;
                        return (
                            <div
                                key={o.value}
                                id={optionId(i)}
                                role="option"
                                aria-selected={selected}
                                onClick={() => pick(o)}
                                onMouseMove={onRowHover}
                                className={`${rowClass(i, selected)} justify-between`}
                            >
                                <span className={`whitespace-nowrap text-[13px] ${selected ? 'font-semibold text-om-ink' : 'text-om-muted'}`}>
                                    {o.label}
                                </span>
                                <span
                                    className={`w-[14px] text-right text-[13px] font-bold text-om-accent transition-opacity duration-100 ${selected ? 'opacity-100' : 'opacity-0'}`}
                                >
                                    ✓
                                </span>
                            </div>
                        );
                    })}
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
