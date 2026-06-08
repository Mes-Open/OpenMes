import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { realtimeCollection } from '../lib/realtimeCollection';
import { __ } from '../lib/i18n';

/**
 * Generic admin list backed by a Reverb-synced collection + TanStack DB live
 * query.
 *
 * Extracted from the Product Types pilot — the shared shape of every admin CRUD
 * list. Rows live-sync (create/edit/delete reflect without refresh); the page
 * just declares columns and per-row actions.
 *
 * Props:
 *   shape       — collection name (must be in ShapeRegistry)
 *   title       — heading
 *   createHref / createLabel — optional "new" button
 *   columns     — [{ key, label, render?(row), className?, align? }]
 *   orderBy     — row field to sort by (default 'name')
 *   orderDir    — 'asc' | 'desc' (default 'asc')
 *   getKey      — row → key (default row.id)
 *   actions     — row → [{ label, href?, onClick?, variant?, className? }]
 *                 variant: 'secondary' (default) | 'primary' | 'danger' | 'warning'
 *                 className overrides the variant when you need a one-off style.
 *   emptyText   — shown when no rows
 */

/**
 * Icon-button row actions — the pre-React-migration look. The standard CRUD trio
 * (Edit / toggle-active / Delete) renders as a compact colored icon button with a
 * tooltip; SVG paths are copied verbatim from the legacy Blade tables so the icons
 * match exactly. Pass `icon: 'edit' | 'delete' | 'activate' | 'deactivate'`.
 */
const ICON_PATH = {
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    delete: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    deactivate: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    activate: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
};
const ICON_COLOR = {
    edit: 'text-blue-600 hover:text-blue-800',
    delete: 'text-red-600 hover:text-red-800',
    deactivate: 'text-gray-600 hover:text-gray-800',
    activate: 'text-gray-600 hover:text-gray-800',
};

/**
 * Labeled-button styles for non-CRUD domain actions (Accept, Pause, Cancel, …)
 * that have no obvious icon — kept as real buttons rather than plain links.
 */
const ACTION_CLASS = {
    primary: 'btn-touch btn-primary text-sm',
    secondary: 'btn-touch btn-secondary text-sm',
    danger: 'btn-touch text-sm bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50',
    warning: 'btn-touch text-sm bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50',
};
const actionClass = (a) => a.className ?? ACTION_CLASS[a.variant] ?? ACTION_CLASS.secondary;
export default function ResourceTable({
    shape,
    title,
    createHref,
    createLabel = '+ New',
    columns,
    orderBy = 'name',
    orderDir = 'asc',
    getKey = (row) => row.id,
    actions,
    emptyText = 'Nothing here yet.',
    filterFn,
    subtitle,
}) {
    const collection = useMemo(() => realtimeCollection(shape, getKey), [shape]);

    const { data: rows } = useLiveQuery((q) =>
        q.from({ r: collection }).orderBy(({ r }) => r[orderBy], orderDir),
    );

    // Optional client-side filter (e.g. a dashboard KPI deep-link like
    // ?status=IN_PROGRESS) — applied over the live rows so it stays reactive.
    const visibleRows = filterFn ? (rows ?? []).filter(filterFn) : rows;

    return (
        <div className="max-w-7xl mx-auto">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800">{__(title)}</h1>
                    {subtitle && <div className="mt-1">{subtitle}</div>}
                </div>
                {createHref && (
                    <Link
                        href={createHref}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                    >
                        {__(createLabel)}
                    </Link>
                )}
            </div>

            <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b bg-gray-50">
                            {columns.map((c) => (
                                <th key={c.key} className={`px-4 py-3 ${c.align === 'right' ? 'text-right' : ''}`}>
                                    {__(c.label)}
                                </th>
                            ))}
                            {actions && <th className="px-4 py-3 text-right">{__('Actions')}</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.length === 0 && (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-8 text-center text-gray-400">
                                    {__(emptyText)}
                                </td>
                            </tr>
                        )}
                        {visibleRows.map((row) => (
                            <tr key={getKey(row)} className="border-b last:border-0 hover:bg-gray-50">
                                {columns.map((c) => (
                                    <td
                                        key={c.key}
                                        className={`px-4 py-3 ${c.className ?? 'text-gray-700'} ${c.align === 'right' ? 'text-right' : ''}`}
                                    >
                                        {c.render ? c.render(row) : row[c.key]}
                                    </td>
                                ))}
                                {actions && (
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            {actions(row).map((a, i) => {
                                                // Icon button (Edit / toggle / Delete) — the legacy look.
                                                if (a.icon && ICON_PATH[a.icon]) {
                                                    const cls = `p-1.5 rounded-md transition-colors ${ICON_COLOR[a.icon]}`;
                                                    const glyph = (
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={ICON_PATH[a.icon]} />
                                                        </svg>
                                                    );
                                                    return a.href ? (
                                                        <Link key={i} href={a.href} className={cls} title={__(a.label)} aria-label={__(a.label)}>
                                                            {glyph}
                                                        </Link>
                                                    ) : (
                                                        <button key={i} onClick={a.onClick} className={cls} title={__(a.label)} aria-label={__(a.label)}>
                                                            {glyph}
                                                        </button>
                                                    );
                                                }
                                                // Labeled button (domain actions without an icon).
                                                return a.href ? (
                                                    <Link key={i} href={a.href} className={actionClass(a)}>
                                                        {__(a.label)}
                                                    </Link>
                                                ) : (
                                                    <button key={i} onClick={a.onClick} className={actionClass(a)}>
                                                        {__(a.label)}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Reusable Active/Inactive pill for an `is_active` boolean column. */
export function ActiveBadge({ active }) {
    return (
        <span
            className={`text-xs px-2 py-0.5 rounded font-medium ${
                active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'
            }`}
        >
            {__(active ? 'Active' : 'Inactive')}
        </span>
    );
}
