import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const ACTION_COLORS = {
    created:      'bg-green-100 text-green-700',
    updated:      'bg-blue-100 text-blue-700',
    deleted:      'bg-red-100 text-red-700',
    login:        'bg-purple-100 text-purple-700',
    logout:       'bg-gray-100 text-gray-600',
    login_failed: 'bg-red-100 text-red-700',
};

const METHOD_COLORS = {
    GET:    'bg-gray-100 text-gray-600',
    POST:   'bg-green-100 text-green-700',
    PUT:    'bg-blue-100 text-blue-700',
    PATCH:  'bg-blue-100 text-blue-700',
    DELETE: 'bg-red-100 text-red-700',
};

function entityLabel(log) {
    const type = log.entity_type ? String(log.entity_type).split('\\').pop() : null;
    if (!type) return null;
    return log.entity_id ? `${type} #${log.entity_id}` : type;
}

function Pagination({ meta, links, onPage }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="flex items-center gap-1 flex-wrap">
            {links.map((link, i) => (
                <button
                    key={i}
                    type="button"
                    disabled={!link.url}
                    onClick={() => link.url && onPage(new URL(link.url).searchParams.get('page'))}
                    className={`px-3 py-1 text-sm rounded border transition-colors ${
                        link.active
                            ? 'bg-blue-600 text-white border-blue-600'
                            : link.url
                            ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            : 'border-gray-200 text-gray-400 cursor-default'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

function DetailModal({ log, onClose }) {
    if (!log) return null;

    const formatTs = (v) => {
        if (!v) return '—';
        return String(v).replace('T', ' ').replace(/\.\d+Z?$/, '');
    };

    const prettyJson = (v) => {
        if (v == null) return '';
        try { return JSON.stringify(v, null, 2); } catch { return String(v); }
    };

    return (
        <div
            className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="log-detail-title"
            onClick={onClose}
        >
            <div
                className="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-4 border-b">
                    <h3 id="log-detail-title" className="text-lg font-semibold">Log entry details</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 text-xl leading-none"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>
                <div className="p-4 space-y-3 text-sm">
                    <div>
                        <strong className="text-gray-700">Timestamp:</strong>{' '}
                        <span className="font-mono text-xs">{formatTs(log.created_at)}</span>
                    </div>
                    <div>
                        <strong className="text-gray-700">Source:</strong>{' '}
                        <span className={`px-2 py-0.5 rounded text-xs uppercase ${log.source === 'audit' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'}`}>
                            {log.source || '—'}
                        </span>
                    </div>
                    <div>
                        <strong className="text-gray-700">User:</strong>{' '}
                        <span>{log.user?.name ?? 'Guest'}</span>
                    </div>
                    <div>
                        <strong className="text-gray-700">IP address:</strong>{' '}
                        <span className="font-mono text-xs">{log.ip_address || '—'}</span>
                    </div>

                    {log.source === 'audit' && (
                        <div className="space-y-2 border-t pt-3">
                            <div>
                                <strong className="text-gray-700">Action:</strong>{' '}
                                <span>{log.action || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Entity:</strong>{' '}
                                <span>{entityLabel(log) || '—'}</span>
                            </div>
                            {log.before_state && (
                                <details className="mt-2">
                                    <summary className="text-xs text-gray-500 cursor-pointer hover:text-gray-700">Before state</summary>
                                    <pre className="bg-gray-50 p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words">
                                        {prettyJson(log.before_state)}
                                    </pre>
                                </details>
                            )}
                            {log.after_state && (
                                <details className="mt-2" open>
                                    <summary className="text-xs text-gray-500 cursor-pointer hover:text-gray-700">After state</summary>
                                    <pre className="bg-gray-50 p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words">
                                        {prettyJson(log.after_state)}
                                    </pre>
                                </details>
                            )}
                        </div>
                    )}

                    {log.source === 'request' && (
                        <div className="space-y-2 border-t pt-3">
                            <div>
                                <strong className="text-gray-700">Method:</strong>{' '}
                                <span className="font-mono px-2 py-0.5 rounded bg-gray-100 text-xs">{log.method || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Path:</strong>{' '}
                                <span className="font-mono text-xs break-all">{log.path || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Route name:</strong>{' '}
                                <span className="font-mono text-xs">{log.route_name || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Status:</strong>{' '}
                                <span>{log.status ?? '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Duration:</strong>{' '}
                                <span>{log.duration_ms != null ? `${log.duration_ms} ms` : '—'}</span>
                            </div>
                            <div>
                                <strong className="text-gray-700">Sampled:</strong>{' '}
                                <span>{log.sampled ? 'yes' : 'no'}</span>
                            </div>
                        </div>
                    )}

                    <div className="text-xs text-gray-400 pt-3 border-t break-words">
                        <strong>User agent:</strong>{' '}
                        <span>{log.user_agent || '—'}</span>
                    </div>
                </div>
                <div className="flex justify-end p-3 border-t bg-gray-50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Activity() {
    const { logs, users = [], actions = [], entityTypes = [], filters = {} } = usePage().props;

    const [form, setForm] = useState({
        from:        filters.from ?? '',
        to:          filters.to ?? '',
        user_id:     filters.user_id ?? '',
        source:      filters.source ?? '',
        entity_type: filters.entity_type ?? '',
        action:      filters.action ?? '',
    });

    const [detailLog, setDetailLog] = useState(null);

    const apply = (overrides = {}) => {
        const params = { ...form, ...overrides };
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/admin/logs/activity', params, { preserveState: false });
    };

    const clear = () => {
        router.get('/admin/logs/activity', {}, { preserveState: false });
    };

    const goPage = (page) => {
        const params = { ...form, page };
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/admin/logs/activity', params, { preserveState: false });
    };

    const exportUrl = () => {
        const p = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => { if (v) p.set(k, v); });
        const qs = p.toString();
        return `/admin/logs/activity/export${qs ? '?' + qs : ''}`;
    };

    const logItems = logs?.data ?? [];
    const meta = logs?.meta ?? null;
    const paginationLinks = logs?.links ?? [];

    return (
        <>
            <Head title="Activity Logs" />
            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-800">Activity Logs</h1>
                    <p className="text-gray-600 mt-1">
                        What users did across the system — entity changes, navigation, auth events.
                    </p>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-lg shadow-sm p-5 mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">From</label>
                            <input
                                type="date"
                                value={form.from}
                                onChange={(e) => setForm((f) => ({ ...f, from: e.target.value }))}
                                className="form-input w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">To</label>
                            <input
                                type="date"
                                value={form.to}
                                onChange={(e) => setForm((f) => ({ ...f, to: e.target.value }))}
                                className="form-input w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">User</label>
                            <select
                                value={form.user_id}
                                onChange={(e) => setForm((f) => ({ ...f, user_id: e.target.value }))}
                                className="form-input w-full"
                            >
                                <option value="">All users</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Source</label>
                            <select
                                value={form.source}
                                onChange={(e) => setForm((f) => ({ ...f, source: e.target.value }))}
                                className="form-input w-full"
                            >
                                <option value="">All sources</option>
                                <option value="audit">Entity changes</option>
                                <option value="request">Navigation</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Entity</label>
                            <select
                                value={form.entity_type}
                                onChange={(e) => setForm((f) => ({ ...f, entity_type: e.target.value }))}
                                className="form-input w-full"
                            >
                                <option value="">All entities</option>
                                {entityTypes.map((et) => (
                                    <option key={et} value={et}>{String(et).split('\\').pop()}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Action</label>
                            <select
                                value={form.action}
                                onChange={(e) => setForm((f) => ({ ...f, action: e.target.value }))}
                                className="form-input w-full"
                            >
                                <option value="">All actions</option>
                                {actions.map((a) => (
                                    <option key={a} value={a}>{a}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-3">
                        <button
                            type="button"
                            onClick={() => apply()}
                            className="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            onClick={clear}
                            className="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Clear
                        </button>
                        <a
                            href={exportUrl()}
                            className="sm:ml-auto px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm divide-y divide-gray-200">
                            <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="text-left px-4 py-2">When</th>
                                    <th className="text-left px-4 py-2">Who</th>
                                    <th className="text-left px-4 py-2">What</th>
                                    <th className="text-left px-4 py-2">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {logItems.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-16 text-center text-gray-400">
                                            No activity in this period.
                                        </td>
                                    </tr>
                                ) : logItems.map((log, i) => (
                                    <tr key={log.id ?? i} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                            {log.created_at
                                                ? String(log.created_at).replace('T', ' ').replace(/\.\d+Z?$/, '')
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-gray-800 whitespace-nowrap">
                                            {log.user?.name ?? 'Guest'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.source === 'audit' ? (
                                                <>
                                                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${ACTION_COLORS[log.action] ?? 'bg-gray-100 text-gray-600'}`}>
                                                        {log.action
                                                            ? log.action.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
                                                            : '—'}
                                                    </span>
                                                    {' '}
                                                    <span className="text-gray-700 ml-1">
                                                        {entityLabel(log)}
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <span className={`font-mono text-xs px-2 py-0.5 rounded ${METHOD_COLORS[log.method] ?? 'bg-gray-100 text-gray-600'}`}>
                                                        {log.method}
                                                    </span>
                                                    {' '}
                                                    <span className="text-gray-700 text-xs font-mono break-all">{log.path}</span>
                                                    {' '}
                                                    <span className="text-xs text-gray-400 whitespace-nowrap">
                                                        &rarr; {log.status} &bull; {log.duration_ms}ms
                                                    </span>
                                                </>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-500">
                                            <button
                                                type="button"
                                                onClick={() => setDetailLog(log)}
                                                className="text-blue-600 hover:underline text-xs"
                                            >
                                                Details
                                            </button>
                                            {log.source === 'audit' && (log.action === 'updated' || log.action === 'created') && (
                                                <>
                                                    <span className="text-gray-300 mx-1">|</span>
                                                    <a
                                                        href={`/admin/audit-logs?user_id=${log.user_id ?? ''}&entity_type=${encodeURIComponent(log.entity_type ?? '')}`}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        View changes
                                                    </a>
                                                </>
                                            )}
                                            <div className="text-gray-400 mt-1">{log.ip_address}</div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {meta && meta.last_page > 1 && (
                        <div className="p-3 border-t">
                            <Pagination meta={meta} links={paginationLinks} onPage={goPage} />
                        </div>
                    )}
                </div>
            </div>

            {detailLog && (
                <DetailModal log={detailLog} onClose={() => setDetailLog(null)} />
            )}
        </>
    );
}

Activity.layout = (page) => <AppLayout>{page}</AppLayout>;
