import { useEffect, useMemo, useRef, useState } from 'react';
import { Icon } from '@openmes/ui';
import { __ } from '../../lib/i18n';

/**
 * The toolbar's workstation picker (design ref: OpenMES Operator.dc.html):
 * which bench the lists are scoped to, chosen from the line's stations by
 * code or name, grouped, with how many active orders wait at each.
 *
 * `stations` is the page's `stationPicker` prop (OperatorStationPicker);
 * `onPick(id | null)` — null is "all workstations". `footer` sits under the
 * list (the line's tracking mode).
 */
export default function StationPicker({ stations = [], selectedId = null, onPick, footer = null }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const rootRef = useRef(null);
    const searchRef = useRef(null);

    const selected = stations.find((s) => String(s.id) === String(selectedId)) ?? null;

    useEffect(() => {
        if (!open) return undefined;
        searchRef.current?.focus();
        const onDoc = (e) => { if (!rootRef.current?.contains(e.target)) close(); };
        const onKey = (e) => { if (e.key === 'Escape') close(); };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onKey);
        return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const groups = useMemo(() => {
        const q = query.trim().toLowerCase();
        const out = [];
        stations
            .filter((s) => !q || `${s.code} ${s.name}`.toLowerCase().includes(q))
            .forEach((s) => {
                const g = out.find((x) => x.label === s.group) ?? out[out.push({ label: s.group, items: [] }) - 1];
                g.items.push(s);
            });
        return out;
    }, [stations, query]);

    function close() { setOpen(false); setQuery(''); }
    const pick = (id) => { close(); if (String(id ?? '') !== String(selectedId ?? '')) onPick(id); };

    return (
        <div ref={rootRef} className="relative" data-testid="station-picker">
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => (open ? close() : setOpen(true))}
                className={`flex items-center gap-2.5 min-w-[280px] max-w-[380px] bg-om-card border rounded-[9px] px-3 py-[7px] text-left cursor-pointer ${selected ? 'border-om-accent' : 'border-om-line hover:border-om-faintest'}`}
            >
                <span className="flex-1 min-w-0 leading-[1.25]">
                    <span className="block font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">{__('Workstation')}</span>
                    <span className="block text-[13px] text-om-ink truncate">
                        <span className="font-mono font-semibold">{selected ? selected.code : __('All workstations')}</span>
                        <span className="text-om-muted"> · {selected ? selected.name : __(':count stations', { count: stations.length })}</span>
                    </span>
                </span>
                {selected && (
                    <span
                        role="button"
                        tabIndex={0}
                        aria-label={__('Show all workstations')}
                        onClick={(e) => { e.stopPropagation(); pick(null); }}
                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); pick(null); } }}
                        className="flex text-om-faint hover:text-om-ink px-1 py-0.5"
                    >
                        <Icon name="x" size={14} />
                    </span>
                )}
                <Icon name="chevron-down" size={14} className="text-om-faint shrink-0" aria-hidden="true" />
            </button>

            {open && (
                <div className="absolute top-[calc(100%+6px)] left-0 z-40 w-[420px] max-w-[calc(100vw-2rem)] bg-om-card border border-om-line rounded-xl shadow-[0_18px_44px_-18px_rgba(0,0,0,.3)] p-2">
                    <label className="flex items-center gap-2 bg-om-bg border border-om-line2 rounded-lg px-2.5 py-2 mb-1.5">
                        <Icon name="search" size={14} className="text-om-faint shrink-0" />
                        <input
                            ref={searchRef}
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={__('Search code or name…')}
                            aria-label={__('Search code or name…')}
                            className="flex-1 min-w-0 border-0 bg-transparent p-0 text-[13px] text-om-ink placeholder:text-om-faint outline-none focus:ring-0"
                        />
                    </label>
                    <div className="max-h-[380px] overflow-auto" role="listbox" aria-label={__('Workstation')}>
                        <button type="button" role="option" aria-selected={!selected} onClick={() => pick(null)}
                                className={`w-full flex items-center justify-between px-2.5 py-2 rounded-[7px] text-left cursor-pointer ${!selected ? 'bg-om-accent-bg' : 'hover:bg-om-bg'}`}>
                            <span className="text-[13px] font-semibold text-om-ink">{__('All workstations')}</span>
                        </button>
                        {groups.map((g) => (
                            <div key={g.label}>
                                <div className="font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint px-2.5 pt-2.5 pb-1">{g.label}</div>
                                {g.items.map((s) => {
                                    const on = selected?.id === s.id;
                                    return (
                                        <button key={s.id} type="button" role="option" aria-selected={on} onClick={() => pick(s.id)}
                                                className={`w-full grid grid-cols-[72px_minmax(0,1fr)_24px] gap-2.5 items-center px-2.5 py-2 rounded-[7px] text-left cursor-pointer ${on ? 'bg-om-accent-bg' : 'hover:bg-om-bg'}`}>
                                            <span className={`font-mono text-[12px] font-semibold truncate ${on ? 'text-om-accent' : 'text-om-ink'}`}>{s.code}</span>
                                            <span className="text-[13px] text-om-ink truncate">{s.name}</span>
                                            <span className={`font-mono text-[11px] text-right ${s.count ? 'text-om-ink' : 'text-om-faintest'}`}>{s.count || ''}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                        {groups.length === 0 && <div className="px-2.5 py-4 text-[12.5px] text-om-faint">{__('No workstation matches.')}</div>}
                    </div>
                    {footer && <div className="border-t border-om-line2 mt-1.5 pt-2 px-2.5 pb-0.5">{footer}</div>}
                </div>
            )}
        </div>
    );
}
