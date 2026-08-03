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
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { useAnchoredPopover } from '../lib/anchorPopover.web.js';
import { Icon } from '../Icon';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
/** Monday-first weekday headers (EU manufacturing default). */
const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

const pad = (n) => String(n).padStart(2, '0');
/** month is 0-based. */
const toISO = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
const parseISO = (s) => {
    if (!s) return null;
    const [y, m, d] = String(s).split('-').map(Number);
    if (!y || !m || !d) return null;
    return { y, m: m - 1, d };
};
const todayISO = () => {
    const t = new Date();
    return toISO(t.getFullYear(), t.getMonth(), t.getDate());
};
/** ISO YYYY-MM-DD compares correctly as plain strings. */
const inRange = (iso, min, max) => (!min || iso >= min) && (!max || iso <= max);

export function formatDateLong(iso) {
    const p = parseISO(iso);
    return p ? `${p.d} ${MONTHS_SHORT[p.m]} ${p.y}` : '';
}

/** "13 Jul" — the year dropped, for tight spots like a table filter cell. */
export function formatDateShort(iso) {
    const p = parseISO(iso);
    return p ? `${p.d} ${MONTHS_SHORT[p.m]}` : '';
}

/** "13 Jul → 20 Jul", with an ellipsis while the end is still being picked. */
export function formatDateRange({ from, to } = {}) {
    if (!from && !to) return '';
    if (from && !to) return `${formatDateShort(from)} → …`;
    if (!from && to) return `… → ${formatDateShort(to)}`;
    return `${formatDateShort(from)} → ${formatDateShort(to)}`;
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
    return cells;
}

const navBtn =
    'flex size-[28px] items-center justify-center rounded-[7px] border border-om-line text-[14px] text-om-muted leading-none select-none hover:bg-om-bg';
/** The month / year header chips that swap the panel body. */
const headChip =
    'rounded-[6px] bg-om-chip px-2 py-[3px] font-semibold text-om-ink cursor-pointer hover:brightness-95';
/** One cell of the month or year quick-pick grid. */
const pickCell = 'flex h-9 items-center justify-center rounded-[8px] cursor-pointer';

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

    // Follow programmatic value changes into a different month.
    useEffect(() => {
        const p = parseISO(anchor);
        if (p && (p.y !== view.y || p.m !== view.m)) setView({ y: p.y, m: p.m });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [anchor]);

    const cells = useMemo(() => buildCells(view.y, view.m), [view.y, view.m]);
    const today = todayISO();
    const todayShort = useMemo(() => {
        const t = parseISO(today);
        return `${t.d} ${MONTHS_SHORT[t.m]}`;
    }, [today]);

    const step = (delta) => setView((v) => {
        const dt = new Date(v.y, v.m + delta, 1);
        return { y: dt.getFullYear(), m: dt.getMonth() };
    });

    /** Chips toggle: clicking the chip of the view you're in returns to the days grid. */
    const swap = (next) => setMode((m) => (m === next ? 'days' : next));

    // First click sets the start and clears the end; a second click before the
    // start re-anchors rather than producing an inverted range.
    const pickRange = (iso) => {
        if (!from || to) onChange?.({ from: iso, to: null });
        else if (iso < from) onChange?.({ from: iso, to: from });
        else onChange?.({ from, to: iso });
    };

    const years = useMemo(
        () => Array.from({ length: 12 }, (_, i) => view.y - 6 + i),
        [view.y],
    );

    return (
        <div className={`w-[280px] ${className}`}>
            <div className="mb-[14px] flex items-center justify-between">
                <button type="button" aria-label="Previous month" onClick={() => step(-1)} className={navBtn}>‹</button>
                <span className="flex gap-1">
                    <button
                        type="button"
                        onClick={() => swap('months')}
                        aria-expanded={mode === 'months'}
                        className={`${headChip} text-[13px]`}
                    >
                        {MONTHS[view.m]} ▾
                    </button>
                    <button
                        type="button"
                        onClick={() => swap('years')}
                        aria-expanded={mode === 'years'}
                        className={`${headChip} font-mono text-[12.5px]`}
                    >
                        {view.y} ▾
                    </button>
                </span>
                <button type="button" aria-label="Next month" onClick={() => step(1)} className={navBtn}>›</button>
            </div>

            {mode === 'months' && (
                <div className="grid grid-cols-3 gap-1">
                    {MONTHS_SHORT.map((label, i) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => { setView((v) => ({ ...v, m: i })); setMode('days'); }}
                            className={`${pickCell} text-[12px] ${
                                i === view.m ? 'bg-om-ink font-semibold text-om-on-ink' : 'text-om-ink hover:bg-om-chip'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}

            {mode === 'years' && (
                <div className="grid grid-cols-3 gap-1">
                    {years.map((y) => (
                        <button
                            key={y}
                            type="button"
                            onClick={() => { setView((v) => ({ ...v, y })); setMode('days'); }}
                            className={`${pickCell} font-mono text-[11.5px] ${
                                y === view.y ? 'bg-om-ink font-semibold text-om-on-ink' : 'text-om-ink hover:bg-om-chip'
                            }`}
                        >
                            {y}
                        </button>
                    ))}
                </div>
            )}

            {mode === 'days' && (
            <>
            {/* No horizontal gap in range mode, so a selected span reads as one band. */}
            <div className={`mb-[6px] grid grid-cols-7 ${range ? 'gap-y-0.5' : 'gap-0.5'}`}>
                {WEEKDAYS.map((w) => (
                    <div key={w} className="flex h-6 items-center justify-center font-mono text-[9.5px] tracking-[0.04em] text-om-faint">{w}</div>
                ))}
            </div>
            <div className={`grid grid-cols-7 ${range ? 'gap-y-0.5' : 'gap-0.5'}`}>
                {cells.map((c) => {
                    if (c.blank) return <span key={c.key} className="h-[34px]" />;
                    const isStart = range && c.iso === from;
                    const isEnd = range && c.iso === to;
                    const isBetween = range && from && to && c.iso > from && c.iso < to;
                    const isSelected = range ? isStart || isEnd : c.iso === selected;
                    const isToday = c.iso === today;
                    const disabled = !inRange(c.iso, min, max);
                    const base = 'relative flex h-[34px] items-center justify-center font-mono text-[12.5px]';

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
                        <button
                            key={c.key}
                            type="button"
                            disabled={disabled}
                            aria-pressed={isSelected}
                            aria-label={c.iso}
                            onClick={() => (range ? pickRange(c.iso) : onChange?.(c.iso))}
                            className={`${base} ${radius} ${tone}`}
                        >
                            {c.d}
                            {isToday && !isSelected && !isBetween && (
                                <span className="absolute bottom-1 left-1/2 size-[3px] -translate-x-1/2 rounded-full bg-om-accent" />
                            )}
                        </button>
                    );
                })}
            </div>
            </>
            )}
            {!hideToday && (
                <div className="mt-[14px] flex items-center justify-between border-t border-om-line2 pt-[13px]">
                    {range ? (
                        // Half-picked ranges are the only confusing state here, so the
                        // footer says what the next click does.
                        <span className="font-mono text-[10px] text-om-faint">
                            {from && !to ? pickEndLabel : rangeLabel}
                        </span>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={() => { if (inRange(today, min, max)) onChange?.(today); }}
                                className="text-[12.5px] font-semibold text-om-accent hover:opacity-70"
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
const TRIGGER_TEXT_SIZE = { md: 'text-[13px]', sm: 'truncate text-[10.5px]' };

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
    const { anchorRef, popRef, style } = useAnchoredPopover(open, { estHeight: 360 });

    useEffect(() => {
        if (!open) return;
        const onDown = (e) => {
            // The calendar is portaled — it is NOT inside rootRef's subtree.
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

    const fmt = format ?? (range ? formatDateRange : formatDateLong);
    const hasValue = range ? !!(value?.from || value?.to) : !!value;
    const display = hasValue ? fmt(value) : '';

    return (
        <div ref={rootRef} className={`relative ${className}`} {...props}>
            {label != null && (
                <div className="mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
            )}
            <button
                ref={anchorRef}
                type="button"
                disabled={disabled}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-label={ariaLabel}
                onClick={() => setOpen((o) => !o)}
                className={`flex w-full items-center justify-between border bg-om-bg text-left transition-[border-color,box-shadow,background-color] duration-150 ${TRIGGER_SIZE[size]} ${
                    disabled
                        ? 'cursor-not-allowed border-om-line opacity-60'
                        : open
                          ? 'cursor-pointer border-om-accent shadow-[0_0_0_3px_var(--om-accent-bg)]'
                          : 'cursor-pointer border-om-line hover:border-om-faintest hover:bg-om-card'
                }`}
            >
                <span className={`font-mono ${TRIGGER_TEXT_SIZE[size]} ${display ? 'text-om-ink' : 'text-om-faint'}`}>
                    {display || placeholder}
                </span>
                <CalendarGlyph small={size === 'sm'} />
            </button>
            {open && style && createPortal(
                <div
                    ref={popRef}
                    role="dialog"
                    aria-label="Choose date"
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
                            if (range ? next?.from && next?.to : next) setOpen(false);
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
