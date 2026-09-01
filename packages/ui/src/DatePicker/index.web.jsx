/**
 * Calendar + DatePicker — Geist White system (design ref: OpenMES Components.dc.html §13).
 *
 * `Calendar` is the inline month grid: Monday-first, leading blank cells (no
 * sibling-month days), weekends de-emphasised, today marked with a chip fill +
 * accent dot, selected day accent-filled, `min`/`max` bounds. Bordered month-nav
 * buttons, a Today shortcut and a "DD Mon = today" legend in the footer.
 * `DatePicker` wraps it in a Dropdown-style trigger + popover that closes on
 * outside click / Escape. Values are ISO `YYYY-MM-DD` strings. API is identical
 * to the native twin (index.native.tsx).
 *
 * The header's month and year are **chips that swap the panel body** for a
 * 3-column month or year grid (clicking the active one returns to the days
 * view) — reaching next March is one click instead of ten taps on ›. The year
 * grid spans `year-6 … year+5`.
 *
 * `range` turns the grid into a from→to picker: the first click sets the start,
 * the second the end (a click before the start re-anchors it), days between are
 * washed in accent-bg and the endpoints keep the outer half of their radius so
 * the selection reads as one continuous band. In range mode `value` and
 * `onChange` carry `{ from, to }` instead of a single ISO string.
 *
 * ## Accessibility
 *
 * Modelled on the APG date-picker **dialog** pattern, with one deliberate
 * departure: there is no text input beside the trigger, so the button carries
 * the value and the dialog is the only way in.
 *
 * - The day grid is a real `grid` / `row` / `gridcell` tree with a **roving tab
 *   stop** — one tabbable day, not thirty-one — driven by arrows, Home/End and
 *   PageUp/PageDown (Shift for ±1 year), exactly as the pattern specifies.
 * - The dialog moves focus onto the active day when it opens, traps Tab, and
 *   hands focus back to the trigger when it closes.
 * - Cells are named by `Intl`, not by their ISO string: "Saturday, 22 August
 *   2026", never "2026-08-22" read out as digits.
 */
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { useAnchoredPopover } from '../lib/anchorPopover.web.js';
import { useDialogFocus } from '../lib/dialogFocus.web.js';
import { useRovingFocus } from '../lib/rovingFocus.web.js';
import {
    dayOf, inRange, isoWeekEnd, isoWeekStart, matchPreset, parseISO, RANGE_PRESETS,
    shiftDays, shiftMonths, toISO, todayISO, withYearMonth,
} from '../lib/rangePresets.js';
import { Icon } from '../Icon';

export { RANGE_PRESETS } from '../lib/rangePresets.js';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
/** Monday-first weekday headers (EU manufacturing default). */
const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
/** 1 Jan 2024 was a Monday — the seed for spelling the header row out in order. */
const MONDAY_SEED = 1;


/**
 * `months` is the abbreviated set to spell the date with — English unless the
 * caller passes its own, which is how the trigger stays in the same language as
 * the calendar under it.
 */
export function formatDateLong(iso, months = MONTHS_SHORT) {
    const p = parseISO(iso);
    return p ? `${p.d} ${months[p.m]} ${p.y}` : '';
}

/** "13 Jul" — the year dropped, for tight spots like a table filter cell. */
export function formatDateShort(iso, months = MONTHS_SHORT) {
    const p = parseISO(iso);
    return p ? `${p.d} ${months[p.m]}` : '';
}

/** "13 Jul → 20 Jul", with an ellipsis while the end is still being picked. */
export function formatDateRange({ from, to } = {}, months = MONTHS_SHORT) {
    if (!from && !to) return '';
    if (from && !to) return `${formatDateShort(from, months)} → …`;
    if (!from && to) return `… → ${formatDateShort(to, months)}`;
    return `${formatDateShort(from, months)} → ${formatDateShort(to, months)}`;
}

/** Leading blanks for the Monday-first offset, then the month's days (no siblings). */
function buildCells(year, month) {
    const firstDow = (new Date(year, month, 1).getDay() + 6) % 7; // Mon = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < firstDow; i++) cells.push({ blank: true, key: `b${i}` });
    for (let d = 1; d <= daysInMonth; d++) {
        const dow = new Date(year, month, d).getDay();
        cells.push({ blank: false, key: `d${d}`, d, iso: toISO(year, month, d), weekend: dow === 0 || dow === 6 });
    }
    // Trailing blanks so every `role="row"` holds seven cells — a short last row
    // makes a grid announce inconsistent column counts.
    while (cells.length % 7) cells.push({ blank: true, key: `t${cells.length}` });
    return cells;
}

const navBtn =
    'flex size-[28px] items-center justify-center rounded-[7px] border border-om-line text-[14px] text-om-muted leading-none select-none hover:bg-om-bg';
/** The month / year header chips that swap the panel body. */
const headChip =
    'rounded-[6px] bg-om-chip px-2 py-[3px] font-semibold text-om-ink cursor-pointer hover:brightness-95';
/** One cell of the month or year quick-pick grid. */
const pickCell = 'flex h-9 w-full items-center justify-center rounded-[8px] cursor-pointer';
/**
 * Roving focus is moved with `.focus()`, so the ring has to be explicit — the
 * UA default is easy to lose against the accent fill of a selected day.
 */
const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-om-accent';

/**
 * The month and year quick-pick bodies: twelve choices three to a row, on the
 * shared roving tab stop (±1 across, ±3 down) so swapping the body does not drop
 * a keyboard user out of the pattern.
 */
function PickGrid({ items, activeIndex, onPick, cellClass, label, id }) {
    // The tab stop follows the arrows here, not the selection: moving across the
    // months is not the same as choosing one, which only a click or Enter does.
    const [focused, setFocused] = useState(() => (activeIndex >= 0 ? activeIndex : 0));
    const { containerProps, itemProps } = useRovingFocus(items.length, focused, setFocused, { columns: 3 });

    const rows = Array.from({ length: Math.ceil(items.length / 3) }, (_, r) => items.slice(r * 3, r * 3 + 3));

    return (
        <div {...containerProps} id={id} role="grid" aria-label={label} className="flex flex-col gap-1">
            {rows.map((row, r) => (
                <div key={row[0].key} role="row" className="grid grid-cols-3 gap-1">
                    {row.map((item, c) => {
                        const i = r * 3 + c;
                        const on = i === activeIndex;
                        return (
                            <div key={item.key} role="gridcell" aria-selected={on}>
                                <button
                                    type="button"
                                    {...itemProps(i)}
                                    aria-label={item.name}
                                    onFocus={() => setFocused(i)}
                                    onClick={() => onPick(i)}
                                    className={`${cellClass} ${focusRing} ${
                                        on ? 'bg-om-ink font-semibold text-om-on-ink' : 'text-om-ink hover:bg-om-chip'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}

export function Calendar({
    value,
    onChange,
    min,
    max,
    range = false,
    hideToday = false,
    // Footer copy — English defaults; callers inside the app pass translations.
    todayLabel = 'Today',
    todayWord = 'today',
    rangeLabel = 'RANGE',
    pickEndLabel = 'Pick an end date',
    prevMonthLabel = 'Previous month',
    nextMonthLabel = 'Next month',
    monthGridLabel = 'Month',
    yearGridLabel = 'Year',
    /** Visible month names — `monthLabels` heads the panel, the short set fills the quick-pick. */
    monthLabels = MONTHS,
    monthShortLabels = MONTHS_SHORT,
    /** Visible weekday column heads. Two letters by design; the spoken name comes from `locale`. */
    weekdayLabels = WEEKDAYS,
    /**
     * Announcements, as `(parts) => string`. Only the assistive layer reads
     * these, so a caller that skips them loses nothing on screen.
     */
    announceRangeStart = (date) => `Start date ${date} selected. Now pick an end date.`,
    /**
     * BCP-47 tag for the **spoken** day and weekday names — "Saturday, 22 August
     * 2026" rather than "2026-08-22". Defaults to English, like the copy above;
     * the app passes its own tag.
     */
    locale = 'en',
    /** `{ [presetKey]: 'translated label' }` — the app's copy for RANGE_PRESETS. */
    presetLabels = {},
    className = '',
}) {
    // Single mode carries an ISO string; range mode carries { from, to }.
    const selected = range ? null : value || null;
    const from = range ? (value?.from ?? null) : null;
    const to = range ? (value?.to ?? null) : null;

    const anchor = selected ?? from ?? to;
    const initial = parseISO(anchor) ?? parseISO(todayISO());
    const [view, setView] = useState({ y: initial.y, m: initial.m });
    /** 'days' | 'months' | 'years' — which body the header chips have swapped in. */
    const [mode, setMode] = useState('days');
    /** The grid's single tab stop. Not the selection — where the arrows are. */
    const [focusedISO, setFocusedISO] = useState(() => anchor ?? todayISO());

    const uid = useId();
    const headingId = `${uid}-head`;
    const bodyId = `${uid}-body`;
    const gridRef = useRef(null);
    /**
     * Set just before a state change that should pull DOM focus with it, so
     * arrowing moves focus but a mouse click on › does not yank it off the button.
     */
    const focusPending = useRef(false);

    useEffect(() => {
        if (!focusPending.current) return;
        focusPending.current = false;
        gridRef.current?.querySelector('[data-day][tabindex="0"]')?.focus();
    });

    // Follow programmatic value changes into a different month, taking the tab
    // stop along — a roving grid whose active cell is off-screen has none.
    useEffect(() => {
        const p = parseISO(anchor);
        if (!p || (p.y === view.y && p.m === view.m)) return;
        setView({ y: p.y, m: p.m });
        setFocusedISO(anchor);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [anchor]);

    const cells = useMemo(() => buildCells(view.y, view.m), [view.y, view.m]);
    const weeks = useMemo(
        () => Array.from({ length: cells.length / 7 }, (_, i) => cells.slice(i * 7, i * 7 + 7)),
        [cells],
    );
    const today = todayISO();
    const todaySelectable = inRange(today, min, max);
    const showPresets = range;
    // Which chip reads as active — an exact match on both ends, so a hand-picked
    // range that happens to equal "This month" lights that chip too.
    const activePreset = useMemo(
        () => (showPresets ? matchPreset(value, today) : null),
        [showPresets, value, today],
    );
    const todayShort = useMemo(() => {
        const t = parseISO(today);
        return `${t.d} ${monthShortLabels[t.m]}`;
    }, [today, monthShortLabels]);

    // Spelled-out names for the assistive layer; the visible cells stay digits.
    const dayName = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(locale, {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        });
        return (iso) => { const d = dayOf(iso); return d ? fmt.format(d) : iso; };
    }, [locale]);
    const weekdayNames = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(locale, { weekday: 'long' });
        return weekdayLabels.map((_, i) => fmt.format(new Date(2024, 0, MONDAY_SEED + i)));
    }, [locale, weekdayLabels]);

    /**
     * What the live region says next.
     *
     * Announced **only when focus does not move**. Where it does — arrowing,
     * PageUp/PageDown, picking out of the month grid — the newly focused day is
     * read as "Tuesday, 16 September 2026", which already carries the month, and
     * a live region on top of that speaks it twice. The ‹ › buttons are the case
     * that needs this: focus stays on the button, all 42 cells repaint, and
     * without it nothing is said at all.
     *
     * It is also where range mode says which half of the selection it is waiting
     * for, since the on-screen hint is ordinary text nobody is pointed at.
     */
    const [announcement, setAnnouncement] = useState('');

    /** Move the view, taking the grid's tab stop with it. */
    const goTo = (y, m, nextFocus) => {
        setView({ y, m });
        setFocusedISO((f) => nextFocus(f) ?? f);
    };

    const step = (delta) => {
        const dt = new Date(view.y, view.m + delta, 1);
        goTo(dt.getFullYear(), dt.getMonth(), (f) => shiftMonths(f, delta));
        setAnnouncement(`${monthLabels[dt.getMonth()]} ${dt.getFullYear()}`);
    };

    /** Chips toggle: clicking the chip of the view you're in returns to the days grid. */
    const swap = (next) => setMode((m) => (m === next ? 'days' : next));

    /** Back to the days body from a quick-pick, focus following the keyboard in. */
    const backToDays = (y, m) => {
        goTo(y, m, (f) => withYearMonth(f, y, m));
        focusPending.current = true;
        setMode('days');
    };

    // First click sets the start and clears the end; a second click before the
    // start re-anchors rather than producing an inverted range.
    const pickRange = (iso) => {
        if (!from || to) {
            onChange?.({ from: iso, to: null });
            setAnnouncement(announceRangeStart(dayName(iso)));
        } else {
            // No announcement for the finished range: in a DatePicker this closes
            // the dialog in the same commit, so the live region is unmounted before
            // it can be read — and focus lands back on the trigger, whose name now
            // carries the range anyway.
            onChange?.(iso < from ? { from: iso, to: from } : { from, to: iso });
        }
    };

    const years = useMemo(
        () => Array.from({ length: 12 }, (_, i) => view.y - 6 + i),
        [view.y],
    );

    /**
     * The one day carrying `tabindex="0"`. Normally `focusedISO`, but it falls
     * back to the first selectable day whenever that has drifted out of the month
     * on screen or outside `min`/`max` — a grid with no tab stop is unreachable.
     */
    const tabbableISO = useMemo(() => {
        const ok = (iso) => iso && inRange(iso, min, max);
        if (cells.some((c) => !c.blank && c.iso === focusedISO && ok(c.iso))) return focusedISO;
        return cells.find((c) => !c.blank && ok(c.iso))?.iso ?? null;
    }, [cells, focusedISO, min, max]);

    const onGridKeyDown = (e) => {
        const cur = tabbableISO ?? focusedISO;
        if (!cur) return;
        let next;
        switch (e.key) {
            case 'ArrowLeft': next = shiftDays(cur, -1); break;
            case 'ArrowRight': next = shiftDays(cur, 1); break;
            case 'ArrowUp': next = shiftDays(cur, -7); break;
            case 'ArrowDown': next = shiftDays(cur, 7); break;
            case 'Home': next = isoWeekStart(cur); break;
            case 'End': next = isoWeekEnd(cur); break;
            case 'PageUp': next = shiftMonths(cur, e.shiftKey ? -12 : -1); break;
            case 'PageDown': next = shiftMonths(cur, e.shiftKey ? 12 : 1); break;
            default: return;
        }
        // Claimed even when the move is refused: Home/End and the arrows would
        // otherwise scroll the page out from under the open dialog.
        e.preventDefault();
        if (!next || !inRange(next, min, max)) return;
        focusPending.current = true;
        setFocusedISO(next);
        const p = parseISO(next);
        if (p.y !== view.y || p.m !== view.m) setView({ y: p.y, m: p.m });
    };

    return (
        // Range mode is wider so the preset chips sit three to a row under the
        // grid instead of stacking into a column taller than the calendar.
        <div className={`${showPresets ? 'w-[420px]' : 'w-[280px]'} ${className}`}>
            {/* Off-screen, never focusable — the only place a month change or a
                half-finished range is spoken. */}
            <div aria-live="polite" className="sr-only">{announcement}</div>
            <div className="mb-[14px] flex items-center justify-between">
                <button type="button" aria-label={prevMonthLabel} onClick={() => step(-1)} className={`${navBtn} ${focusRing}`}><Icon name="chevron-left" size={16} /></button>
                <span id={headingId} className="flex gap-1">
                    <button
                        type="button"
                        onClick={() => swap('months')}
                        aria-expanded={mode === 'months'}
                        aria-controls={bodyId}
                        className={`${headChip} ${focusRing} text-[13px]`}
                    >
                        {monthLabels[view.m]} <Icon name="chevron-down" size={12} className="inline-block align-middle" />
                    </button>
                    <button
                        type="button"
                        onClick={() => swap('years')}
                        aria-expanded={mode === 'years'}
                        aria-controls={bodyId}
                        className={`${headChip} ${focusRing} font-mono text-[12.5px]`}
                    >
                        {view.y} <Icon name="chevron-down" size={12} className="inline-block align-middle" />
                    </button>
                </span>
                <button type="button" aria-label={nextMonthLabel} onClick={() => step(1)} className={`${navBtn} ${focusRing}`}><Icon name="chevron-right" size={16} /></button>
            </div>

            {mode === 'months' && (
                <PickGrid
                    id={bodyId}
                    label={monthGridLabel}
                    items={monthShortLabels.map((label, i) => ({ key: label, label, name: monthLabels[i] }))}
                    activeIndex={view.m}
                    onPick={(i) => backToDays(view.y, i)}
                    cellClass={`${pickCell} text-[12px]`}
                />
            )}

            {mode === 'years' && (
                <PickGrid
                    id={bodyId}
                    label={yearGridLabel}
                    items={years.map((y) => ({ key: y, label: y }))}
                    activeIndex={years.indexOf(view.y)}
                    onPick={(i) => backToDays(years[i], view.m)}
                    cellClass={`${pickCell} font-mono text-[11.5px]`}
                />
            )}

            {mode === 'days' && (
            // No horizontal gap in range mode, so a selected span reads as one band.
            <div
                ref={gridRef}
                id={bodyId}
                role="grid"
                aria-labelledby={headingId}
                aria-multiselectable={range || undefined}
                onKeyDown={onGridKeyDown}
                className={`flex flex-col ${range ? 'gap-y-0.5' : 'gap-0.5'}`}
            >
                <div role="row" className={`mb-[4px] grid grid-cols-7 ${range ? '' : 'gap-0.5'}`}>
                    {weekdayLabels.map((w, i) => (
                        <div
                            key={w}
                            role="columnheader"
                            aria-label={weekdayNames[i]}
                            className="flex h-6 items-center justify-center font-mono text-[9.5px] tracking-[0.04em] text-om-faint"
                        >
                            {w}
                        </div>
                    ))}
                </div>
                {weeks.map((week) => (
                    <div key={week.find((c) => !c.blank)?.iso ?? week[0].key} role="row" className={`grid grid-cols-7 ${range ? '' : 'gap-0.5'}`}>
                        {week.map((c) => {
                            if (c.blank) return <div key={c.key} role="gridcell" className="h-[34px]" />;
                            const isStart = range && c.iso === from;
                            const isEnd = range && c.iso === to;
                            const isBetween = range && from && to && c.iso > from && c.iso < to;
                            const isSelected = range ? isStart || isEnd : c.iso === selected;
                            const isToday = c.iso === today;
                            const disabled = !inRange(c.iso, min, max);
                            const base = 'relative flex h-[34px] w-full items-center justify-center font-mono text-[12.5px]';

                            // Endpoints keep only their outer corners once a span exists.
                            let radius = 'rounded-om-sm';
                            if (isBetween) radius = 'rounded-none';
                            else if (isStart && to) radius = 'rounded-l-om-sm rounded-r-none';
                            else if (isEnd && from) radius = 'rounded-r-om-sm rounded-l-none';

                            let tone;
                            if (disabled) tone = 'text-om-faintest cursor-not-allowed';
                            else if (isSelected) tone = 'bg-om-accent font-semibold text-white cursor-pointer';
                            else if (isBetween) tone = 'bg-om-accent-bg text-om-ink cursor-pointer';
                            else if (isToday) tone = 'bg-om-chip font-semibold text-om-ink cursor-pointer';
                            else tone = `${c.weekend ? 'text-om-faint' : 'text-om-ink'} hover:bg-om-chip cursor-pointer`;
                            return (
                                // `aria-selected` covers the whole band in range mode: every
                                // day between the endpoints really is part of the selection.
                                <div key={c.key} role="gridcell" aria-selected={isSelected || isBetween || undefined}>
                                    <button
                                        type="button"
                                        data-day={c.iso}
                                        // Out-of-range days stay focusable (`aria-disabled`, not
                                        // `disabled`) so arrowing across them is not blocked —
                                        // the roving grid has to be able to pass through.
                                        aria-disabled={disabled || undefined}
                                        aria-current={isToday ? 'date' : undefined}
                                        aria-label={dayName(c.iso)}
                                        tabIndex={c.iso === tabbableISO ? 0 : -1}
                                        onFocus={() => setFocusedISO(c.iso)}
                                        onClick={() => {
                                            if (disabled) return;
                                            if (range) pickRange(c.iso);
                                            else onChange?.(c.iso);
                                        }}
                                        className={`${base} ${radius} ${tone} ${focusRing}`}
                                    >
                                        {c.d}
                                        {isToday && !isSelected && !isBetween && (
                                            <span className="absolute bottom-1 left-1/2 size-[3px] -translate-x-1/2 rounded-full bg-om-accent" />
                                        )}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
            )}
            {showPresets && (
                <div className="mt-[14px] border-t border-om-line2 pt-[13px]">
                    <div className="flex flex-wrap gap-1.5">
                        {RANGE_PRESETS.map((preset) => {
                            const on = activePreset === preset.key;
                            return (
                                <button
                                    key={preset.key}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => onChange?.(preset.resolve(dayOf(today)))}
                                    className={`rounded-[20px] px-[11px] py-[5px] text-[12px] font-medium transition-colors ${focusRing} ${
                                        on
                                            ? 'bg-om-accent text-white'
                                            : 'bg-om-chip text-om-muted hover:text-om-ink'
                                    }`}
                                >
                                    {presetLabels[preset.key] ?? preset.label}
                                </button>
                            );
                        })}
                    </div>
                    {/* Only while half-picked. At rest the chips already say what
                        this is, and a standing "Date range" caption under them
                        is a label for something nobody mistook. */}
                    {from && !to && (
                        <div className="mt-[10px] font-mono text-[10px] text-om-faint">
                            {pickEndLabel}
                        </div>
                    )}
                </div>
            )}
            {!hideToday && !showPresets && (
                <div className="mt-[14px] flex items-center justify-between border-t border-om-line2 pt-[13px]">
                    {range ? (
                        <span className="font-mono text-[10px] text-om-faint">
                            {from && !to ? pickEndLabel : rangeLabel}
                        </span>
                    ) : (
                        <>
                            {/* Bounds can exclude today — an expiry field opening at
                                `min` = tomorrow. The shortcut used to look live and do
                                nothing when pressed. */}
                            <button
                                type="button"
                                disabled={!todaySelectable}
                                onClick={() => onChange?.(today)}
                                className={`text-[12.5px] font-semibold ${focusRing} ${
                                    todaySelectable
                                        ? 'text-om-accent hover:opacity-70 cursor-pointer'
                                        : 'text-om-faintest cursor-not-allowed'
                                }`}
                            >
                                {todayLabel}
                            </button>
                            <span className="flex items-center gap-1.5 font-mono text-[10px] text-om-faint">
                                <span className="size-[7px] rounded-full bg-om-accent" />
                                {todayShort} = {todayWord}
                            </span>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * The trigger's calendar glyph (design §13).
 *
 * Colour is pinned rather than inherited: the trigger's own text switches
 * between ink and faint depending on whether a date is set, and the glyph is
 * meant to stay quiet either way.
 */
function CalendarGlyph({ small = false }) {
    return <Icon name="calendar" size={small ? 13 : 16} className="shrink-0 text-om-faint" />;
}

const TRIGGER_SIZE = {
    md: 'rounded-om-sm px-[13px] py-[10px] gap-[10px]',
    sm: 'rounded-[6px] px-2 py-[5px] gap-[6px]',
};
// Dates stay mono at md so digits line up in forms; the filter row's sm trigger
// matches the select triggers beside it instead — same size and weight, and
// "any date" reads as the current (unfiltered) choice rather than an empty
// field, so it keeps ink at full weight where a form placeholder would go faint.
const TRIGGER_TEXT_SIZE = { md: 'font-mono text-[13px]', sm: 'truncate text-[13px] font-semibold' };

export function DatePicker({
    value,
    onChange,
    label,
    placeholder = 'Select date',
    min,
    max,
    format,
    disabled = false,
    /** from→to selection; `value`/`onChange` carry `{ from, to }`. */
    range = false,
    /** 'sm' is the compact trigger used inside DataTable's column-filter row. */
    size = 'md',
    /** The dialog's accessible name. Falsy (an unwired caller) keeps the default. */
    dialogLabel,
    /** Forwarded to Calendar (footer copy, etc.). */
    calendarProps,
    /** Extra controls rendered under the calendar (e.g. a "clear" action). */
    footer,
    'aria-label': ariaLabel,
    className = '',
    ...props
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    // Measured, not guessed: 314x367 for the day grid, 454x527 once the range
    // presets and the filter footer are in. The first positioning pass runs
    // before the panel exists, and without a width it falls back to the
    // trigger's — a compact filter trigger in a right-hand column measured 84px,
    // skipped the right-edge clamp, and hung off the viewport for a frame.
    const { anchorRef, popRef, style } = useAnchoredPopover(open, {
        estHeight: range ? 530 : 370,
        estWidth: range ? 454 : 314,
    });

    const uid = useId();
    const labelId = `${uid}-label`;
    const triggerId = `${uid}-trigger`;


    useEffect(() => {
        if (!open) return undefined;
        const onDown = (e) => {
            // The calendar is portaled — it is NOT inside rootRef's subtree.
            if (rootRef.current?.contains(e.target) || popRef.current?.contains(e.target)) return;
            setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, popRef]);

    /**
     * Focus in, Tab trapped, Escape answered at the panel — shared with the rest
     * of the package's overlays. `ready` waits for the measured position, since
     * `popRef.current` is still null on the pass where `open` flips. Focus opens
     * on the grid's active day rather than the first control in the card, so the
     * keyboard starts where the reader is going.
     */
    const { restoreFocus } = useDialogFocus(open, popRef, {
        ready: !!style,
        // An outside click leaves focus where the pointer put it, so the
        // automatic restore would fight it; the two paths below ask for it.
        restoreOnClose: false,
        onEscape: () => close(true),
        initialFocus: () => popRef.current?.querySelector('[data-day][tabindex="0"]'),
    });

    /**
     * Escape and a picked date hand focus back to the trigger; an outside click
     * does not — the pointer has already moved focus wherever it was aimed.
     */
    function close(restore) {
        setOpen(false);
        if (restore) restoreFocus();
    }

    // The trigger spells the value with the same month names the panel uses, so
    // a translated calendar can't sit under an English date.
    const months = calendarProps?.monthShortLabels ?? MONTHS_SHORT;
    const fmt = format ?? ((v) => (range ? formatDateRange(v, months) : formatDateLong(v, months)));
    const hasValue = range ? !!(value?.from || value?.to) : !!value;
    const display = hasValue ? fmt(value) : '';

    return (
        <div ref={rootRef} className={`relative ${className}`} {...props}>
            {label != null && (
                <div id={labelId} className="mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
            )}
            {/* A caller that names the control instead of captioning it (the table's
                filter row) still needs the value in the name, so the label becomes a
                hidden caption rather than an `aria-label` that would replace it. */}
            {label == null && ariaLabel != null && <span id={labelId} className="sr-only">{ariaLabel}</span>}
            <button
                ref={anchorRef}
                id={triggerId}
                type="button"
                disabled={disabled}
                aria-haspopup="dialog"
                aria-expanded={open}
                // The caption is not a <label>, so it is stitched on explicitly — and
                // paired with the button's own id so the name keeps the value too:
                // "DUE DATE, 13 Jul 2026". Self-reference pulls in the button's text.
                aria-labelledby={label != null || ariaLabel != null ? `${labelId} ${triggerId}` : undefined}
                onClick={() => (open ? close(false) : setOpen(true))}
                className={`flex w-full items-center justify-between border bg-om-bg text-left transition-[border-color,box-shadow,background-color] duration-150 ${TRIGGER_SIZE[size]} ${
                    disabled
                        ? 'cursor-not-allowed border-om-line opacity-60'
                        : open
                          ? 'cursor-pointer border-om-accent shadow-[0_0_0_3px_var(--om-accent-bg)]'
                          : 'cursor-pointer border-om-line hover:border-om-faintest hover:bg-om-card'
                }`}
            >
                <span className={`${TRIGGER_TEXT_SIZE[size]} ${display || size === 'sm' ? 'text-om-ink' : 'text-om-faint'}`}>
                    {display || placeholder}
                </span>
                <CalendarGlyph small={size === 'sm'} />
            </button>
            {open && style && createPortal(
                <div
                    ref={popRef}
                    role="dialog"
                    aria-modal="true"
                    // See Modal: keeps focus — and therefore Escape — inside the
                    // panel when the pointer lands on inert calendar chrome.
                    tabIndex={-1}
                    aria-label={dialogLabel || 'Choose date'}
                    style={style}
                    className="rounded-om border border-om-line bg-om-card p-4 shadow-[0_18px_44px_-20px_rgba(0,0,0,.22)]"
                >
                    <Calendar
                        value={value}
                        range={range}
                        // Single mode is done in one click; a range stays open until
                        // both ends are picked.
                        onChange={(next) => {
                            onChange?.(next);
                            if (range ? next?.from && next?.to : next) close(true);
                        }}
                        min={min}
                        max={max}
                        {...calendarProps}
                    />
                    {footer && <div className="mt-3 border-t border-om-line2 pt-3">{footer}</div>}
                </div>,
                document.body,
            )}
        </div>
    );
}
