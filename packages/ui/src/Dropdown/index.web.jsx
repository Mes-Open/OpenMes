/**
 * Dropdown — Geist White system (design ref: OpenMES Components.dc.html §13).
 *
 * Custom select replacing native <select>: bg trigger with 1px line border and
 * faint ▾; menu card (radius 12, menu shadow, 6px padding). Single mode shows
 * chip-bg semibold selected rows + accent ✓; multi mode shows 17px accent
 * checkboxes — the "N selected" trigger label is the caller's job via `label`.
 * Closes on outside click/Escape. API is identical to the native twin.
 */
import { useEffect, useRef, useState } from 'react';

export function Dropdown({
    options,
    value,
    values,
    multiple = false,
    onChange,
    label,
    placeholder,
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

    return (
        <div ref={rootRef} className={`relative ${className}`} {...props}>
            <button
                type="button"
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                className={`flex w-full items-center justify-between gap-[10px] rounded-om-sm border border-om-line bg-om-bg px-[13px] py-[10px] text-left ${disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
            >
                <span className={`text-[13.5px] ${isPlaceholder ? 'text-om-faint' : 'text-om-ink'}`}>{triggerLabel}</span>
                <span aria-hidden className="text-[11px] text-om-faint">
                    ▾
                </span>
            </button>
            {open && (
                <div
                    role="listbox"
                    aria-multiselectable={multiple || undefined}
                    className="absolute left-0 right-0 top-full z-50 mt-1 rounded-om border border-om-line bg-om-card p-[6px] shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)]"
                >
                    {options.map((o) => {
                        if (multiple) {
                            const on = selectedValues.includes(o.value);
                            return (
                                <div
                                    key={o.value}
                                    role="option"
                                    aria-selected={on}
                                    onClick={() => pick(o)}
                                    className="flex cursor-pointer items-center gap-[11px] rounded-[6px] px-[11px] py-[9px] hover:bg-om-chip"
                                >
                                    <span
                                        className={`flex size-[17px] shrink-0 items-center justify-center rounded-[5px] ${on ? 'bg-om-accent' : 'border-2 border-om-faintest'}`}
                                    >
                                        {on && <span className="text-[10px] font-bold leading-none text-white">✓</span>}
                                    </span>
                                    <span className="text-[13px] text-om-ink">{o.label}</span>
                                </div>
                            );
                        }
                        const selected = o.value === value;
                        return (
                            <div
                                key={o.value}
                                role="option"
                                aria-selected={selected}
                                onClick={() => pick(o)}
                                className={`flex cursor-pointer items-center justify-between gap-[10px] rounded-[6px] px-[11px] py-[9px] ${selected ? 'bg-om-chip' : 'hover:bg-om-chip'}`}
                            >
                                <span className={`text-[13px] ${selected ? 'font-semibold text-om-ink' : 'text-om-muted'}`}>
                                    {o.label}
                                </span>
                                <span className="w-[14px] text-right text-[13px] font-bold text-om-accent">
                                    {selected ? '✓' : ''}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
