import { useEffect, useState } from 'react';
import { Icon } from '@openmes/ui';
import { __ } from '../../lib/i18n';
import { readParam, writeParams } from '../../lib/urlState';
import { FILTERS, pillCls } from './operatorUi';

/** A value remembered in localStorage under `key` (a choice that outlives the page). */
export function useStoredState(key, initial) {
    const [value, setValue] = useState(() => {
        try { return localStorage.getItem(key) ?? initial; } catch { return initial; }
    });
    useEffect(() => {
        try { localStorage.setItem(key, value); } catch { /* private mode: lasts this page only */ }
    }, [key, value]);
    return [value, setValue];
}

/**
 * The order the execution panel shows. Kept in the address (`?wo=`) so a
 * write that redirects back — and remounts the page — lands on the same order,
 * as does a reload or a shared link. `mobileOpen` is the drawer below xl.
 */
export function useOrderSelection(orders) {
    const [selectedId, setSelectedId] = useState(() => {
        const fromUrl = Number(readParam('wo'));
        return fromUrl || null;
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    // Nothing chosen yet: the first running order, else the first in the list.
    // A chosen order stays even when it leaves the list (finished, filtered out).
    useEffect(() => {
        if (selectedId) return;
        const first = orders.find((o) => o.status === 'IN_PROGRESS') ?? orders[0] ?? null;
        if (first) setSelectedId(first.id);
    }, [orders, selectedId]);

    const select = (id) => {
        setSelectedId(id);
        writeParams({ wo: id });
        setMobileOpen(true);
    };

    return { selectedId, select, mobileOpen, closeMobile: () => setMobileOpen(false) };
}

/**
 * Search, status filter pills and the count above the operator's list
 * (design ref: OpenMES Operator.dc.html). `leading` goes first (the workstation
 * picker); `views` adds the page's own view switch at the end (the grouped list
 * next to the older table / cards).
 */
export default function ListToolbar({ query, onQuery, filter, onFilter, count, views = null, view, onView, leading = null, children }) {
    return (
        <div className="flex flex-wrap items-center gap-3 mb-3.5">
            {leading}
            <label className="flex items-center gap-[9px] w-[300px] max-w-full bg-om-card border border-om-line rounded-[9px] px-3 py-2 focus-within:border-om-accent">
                <Icon name="search" size={15} className="text-om-faint shrink-0" />
                <input
                    type="search"
                    value={query}
                    onChange={(e) => onQuery(e.target.value)}
                    placeholder={__('Search order, product…')}
                    aria-label={__('Search order, product…')}
                    className="flex-1 min-w-0 border-0 bg-transparent p-0 text-[13px] text-om-ink placeholder:text-om-faint outline-none focus:ring-0"
                />
            </label>
            <div className="flex gap-[3px] bg-om-card border border-om-line rounded-[9px] p-[3px]" role="group" aria-label={__('Status filter')}>
                {FILTERS.map((f) => (
                    <button key={f.key} type="button" aria-pressed={filter === f.key} onClick={() => onFilter(f.key)} className={pillCls(filter === f.key)}>
                        {__(f.label)}
                    </button>
                ))}
            </div>
            {children}
            <div className="flex-1" />
            <span className="font-mono text-[11px] uppercase text-om-faint">{__(':count work orders', { count })}</span>
            {views && (
                <div className="flex gap-[3px] bg-om-card border border-om-line rounded-[9px] p-[3px]" role="group" aria-label={__('View')}>
                    {views.map(([key, label]) => (
                        <button key={key} type="button" aria-pressed={view === key} onClick={() => onView(key)} className={pillCls(view === key)}>
                            {label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
