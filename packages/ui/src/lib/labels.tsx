/**
 * App-wide default labels for the package's chrome — the words a control needs
 * that are not the caller's data: a search box placeholder, an empty-result
 * note. The package is locale-free, so it ships none; the app provides them
 * once at its root (`<UILabelsProvider labels={{ … }}>`) and every control
 * picks them up. A prop passed at the call site always wins.
 *
 *   searchPlaceholder — Dropdown search box (and any future search field)
 *   noResultsLabel    — Dropdown when the search matches nothing
 *   clearLabel        — the button that empties a search box (screen readers)
 */
import React, { createContext, useContext, type ReactNode } from 'react';

export interface UILabels {
    searchPlaceholder?: string;
    noResultsLabel?: string;
    clearLabel?: string;
}

const UILabelsContext = createContext<UILabels>({});

export function UILabelsProvider({ labels, children }: { labels?: UILabels; children?: ReactNode }) {
    return <UILabelsContext.Provider value={labels ?? {}}>{children}</UILabelsContext.Provider>;
}

export function useUILabels(): UILabels {
    return useContext(UILabelsContext);
}
