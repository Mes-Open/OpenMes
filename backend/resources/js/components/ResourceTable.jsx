import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { realtimeCollection } from '../lib/realtimeCollection';

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
 *   actions     — row → [{ label, href?, onClick?, className? }]
 *   emptyText   — shown when no rows
 */
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
                    <h1 className="text-3xl font-bold text-gray-800">{title}</h1>
                    {subtitle && <div className="mt-1">{subtitle}</div>}
                </div>
                {createHref && (
                    <Link
                        href={createHref}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                    >
                        {createLabel}
                    </Link>
                )}
            </div>

            <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b bg-gray-50">
                            {columns.map((c) => (
                                <th key={c.key} className={`px-4 py-3 ${c.align === 'right' ? 'text-right' : ''}`}>
                                    {c.label}
                                </th>
                            ))}
                            {actions && <th className="px-4 py-3 text-right">Actions</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.length === 0 && (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-8 text-center text-gray-400">
                                    {emptyText}
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
                                        <div className="flex items-center justify-end gap-3">
                                            {actions(row).map((a, i) =>
                                                a.href ? (
                                                    <Link
                                                        key={i}
                                                        href={a.href}
                                                        className={a.className ?? 'text-blue-600 hover:underline'}
                                                    >
                                                        {a.label}
                                                    </Link>
                                                ) : (
                                                    <button
                                                        key={i}
                                                        onClick={a.onClick}
                                                        className={a.className ?? 'text-gray-500 hover:text-gray-800'}
                                                    >
                                                        {a.label}
                                                    </button>
                                                ),
                                            )}
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
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}
