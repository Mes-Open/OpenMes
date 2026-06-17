/**
 * Calendar + DatePicker — Geist White system (design ref: OpenMES Components.dc.html §14).
 *
 * `Calendar` is the inline month grid: Monday-first, leading blank cells (no
 * sibling-month days), weekends de-emphasised, today marked with a chip fill +
 * accent dot, selected day accent-filled, `min`/`max` bounds. Bordered month-nav
 * buttons, a Today shortcut and a "DD Mon = today" legend in the footer.
 * `DatePicker` wraps it in a Dropdown-style trigger + popover that closes on
 * outside click / Escape. Values are ISO `YYYY-MM-DD` strings. API is identical
 * to the native twin (index.native.tsx).
 */
import { useEffect, useMemo, useRef, useState } from 'react';

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

export function Calendar({ value, onChange, min, max, hideToday = false, className = '' }) {
    const selected = value || null;
    const initial = parseISO(selected) ?? parseISO(todayISO());
    const [view, setView] = useState({ y: initial.y, m: initial.m });

    // Follow programmatic value changes into a different month.
    useEffect(() => {
        const p = parseISO(selected);
        if (p && (p.y !== view.y || p.m !== view.m)) setView({ y: p.y, m: p.m });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected]);

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

    return (
        <div className={`w-[280px] ${className}`}>
            <div className="mb-[14px] flex items-center justify-between">
                <button type="button" aria-label="Previous month" onClick={() => step(-1)} className={navBtn}>‹</button>
                <span className="text-[14px] font-semibold tracking-[-0.01em] text-om-ink">{MONTHS[view.m]} {view.y}</span>
                <button type="button" aria-label="Next month" onClick={() => step(1)} className={navBtn}>›</button>
            </div>
            <div className="mb-[6px] grid grid-cols-7 gap-0.5">
                {WEEKDAYS.map((w) => (
                    <div key={w} className="flex h-6 items-center justify-center font-mono text-[9.5px] tracking-[0.04em] text-om-faint">{w}</div>
                ))}
            </div>
            <div className="grid grid-cols-7 gap-0.5">
                {cells.map((c) => {
                    if (c.blank) return <span key={c.key} className="h-[34px]" />;
                    const isSelected = c.iso === selected;
                    const isToday = c.iso === today;
                    const disabled = !inRange(c.iso, min, max);
                    const base = 'relative flex h-[34px] items-center justify-center rounded-om-sm font-mono text-[12.5px]';
                    let tone;
                    if (disabled) tone = 'text-om-faintest cursor-not-allowed';
                    else if (isSelected) tone = 'bg-om-accent font-semibold text-white cursor-pointer';
                    else if (isToday) tone = 'bg-om-chip font-semibold text-om-ink cursor-pointer';
                    else tone = `${c.weekend ? 'text-om-faint' : 'text-om-ink'} hover:bg-om-chip cursor-pointer`;
                    return (
                        <button
                            key={c.key}
                            type="button"
                            disabled={disabled}
                            aria-pressed={isSelected}
                            aria-label={c.iso}
                            onClick={() => onChange?.(c.iso)}
                            className={`${base} ${tone}`}
                        >
                            {c.d}
                            {isToday && !isSelected && (
                                <span className="absolute bottom-1 left-1/2 size-[3px] -translate-x-1/2 rounded-full bg-om-accent" />
                            )}
                        </button>
                    );
                })}
            </div>
            {!hideToday && (
                <div className="mt-[14px] flex items-center justify-between border-t border-om-line2 pt-[13px]">
                    <button
                        type="button"
                        onClick={() => { if (inRange(today, min, max)) onChange?.(today); }}
                        className="text-[12.5px] font-semibold text-om-accent hover:opacity-70"
                    >
                        Today
                    </button>
                    <span className="flex items-center gap-1.5 font-mono text-[10px] text-om-faint">
                        <span className="size-[7px] rounded-full bg-om-accent" />
                        {todayShort} = today
                    </span>
                </div>
            )}
        </div>
    );
}

/** Small calendar glyph built from divs (design §14 trigger). */
function CalendarGlyph() {
    return (
        <span aria-hidden className="relative block size-[15px] rounded-[3px] border-[1.6px] border-om-faint">
            <span className="absolute -top-[3px] left-[2px] h-1 w-0.5 rounded-full bg-om-faint" />
            <span className="absolute -top-[3px] right-[2px] h-1 w-0.5 rounded-full bg-om-faint" />
        </span>
    );
}

export function DatePicker({
    value,
    onChange,
    label,
    placeholder = 'Select date',
    min,
    max,
    format = formatDateLong,
    disabled = false,
    className = '',
    ...props
}) {
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

    const display = value ? format(value) : '';

    return (
        <div ref={rootRef} className={`relative ${className}`} {...props}>
            {label != null && (
                <div className="mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{label}</div>
            )}
            <button
                type="button"
                disabled={disabled}
                aria-haspopup="dialog"
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                className={`flex w-full items-center justify-between gap-[10px] rounded-om-sm border border-om-line bg-om-bg px-[13px] py-[10px] text-left ${disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
            >
                <span className={`font-mono text-[13px] ${display ? 'text-om-ink' : 'text-om-faint'}`}>{display || placeholder}</span>
                <CalendarGlyph />
            </button>
            {open && (
                <div
                    role="dialog"
                    aria-label="Choose date"
                    className="absolute left-0 top-full z-50 mt-1 rounded-om border border-om-line bg-om-card p-4 shadow-[0_18px_44px_-20px_rgba(0,0,0,.22)]"
                >
                    <Calendar
                        value={value}
                        onChange={(iso) => { onChange?.(iso); if (iso) setOpen(false); }}
                        min={min}
                        max={max}
                    />
                </div>
            )}
        </div>
    );
}
