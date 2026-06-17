import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const ACTION_COLORS = {
    created:      'bg-om-running-bg text-om-running',
    updated:      'bg-om-chip text-om-accent',
    deleted:      'bg-om-blocked-bg text-om-blocked',
    login:        'bg-om-chip text-purple-700',
    logout:       'bg-om-chip text-om-muted',
    login_failed: 'bg-om-blocked-bg text-om-blocked',
};

const METHOD_COLORS = {
    GET:    'bg-om-chip text-om-muted',
    POST:   'bg-om-running-bg text-om-running',
    PUT:    'bg-om-chip text-om-accent',
    PATCH:  'bg-om-chip text-om-accent',
    DELETE: 'bg-om-blocked-bg text-om-blocked',
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
                            ? 'bg-om-ink text-white border-om-accent'
                            : link.url
                            ? 'border-om-line text-om-muted hover:bg-om-bg'
                            : 'border-om-line2 text-om-faint cursor-default'
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
                className="bg-om-card rounded-om-sm shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-4 border-b">
                    <h3 id="log-detail-title" className="text-lg font-semibold">Log entry details</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-om-faint hover:text-om-ink text-xl leading-none"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>
                <div className="p-4 space-y-3 text-sm">
                    <div>
                        <strong className="text-om-muted">Timestamp:</strong>{' '}
                        <span className="font-mono text-xs">{formatTs(log.created_at)}</span>
                    </div>
                    <div>
                        <strong className="text-om-muted">Source:</strong>{' '}
                        <span className={`px-2 py-0.5 rounded text-xs uppercase ${log.source === 'audit' ? 'bg-om-chip text-purple-700' : 'bg-om-chip text-om-muted'}`}>
                            {log.source || '—'}
                        </span>
                    </div>
                    <div>
                        <strong className="text-om-muted">User:</strong>{' '}
                        <span>{log.user?.name ?? 'Guest'}</span>
                    </div>
                    <div>
                        <strong className="text-om-muted">IP address:</strong>{' '}
                        <span className="font-mono text-xs">{log.ip_address || '—'}</span>
                    </div>

                    {log.source === 'audit' && (
                        <div className="space-y-2 border-t pt-3">
                            <div>
                                <strong className="text-om-muted">Action:</strong>{' '}
                                <span>{log.action || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Entity:</strong>{' '}
                                <span>{entityLabel(log) || '—'}</span>
                            </div>
                            {log.before_state && (
                                <details className="mt-2">
                                    <summary className="text-xs text-om-muted cursor-pointer hover:text-om-ink">Before state</summary>
                                    <pre className="bg-om-panel p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words">
                                        {prettyJson(log.before_state)}
                                    </pre>
                                </details>
                            )}
                            {log.after_state && (
                                <details className="mt-2" open>
                                    <summary className="text-xs text-om-muted cursor-pointer hover:text-om-ink">After state</summary>
                                    <pre className="bg-om-panel p-2 rounded text-xs overflow-x-auto mt-1 whitespace-pre-wrap break-words">
                                        {prettyJson(log.after_state)}
                                    </pre>
                                </details>
                            )}
                        </div>
                    )}

                    {log.source === 'request' && (
                        <div className="space-y-2 border-t pt-3">
                            <div>
                                <strong className="text-om-muted">Method:</strong>{' '}
                                <span className="font-mono px-2 py-0.5 rounded bg-om-chip text-xs">{log.method || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Path:</strong>{' '}
                                <span className="font-mono text-xs break-all">{log.path || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Route name:</strong>{' '}
                                <span className="font-mono text-xs">{log.route_name || '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Status:</strong>{' '}
                                <span>{log.status ?? '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Duration:</strong>{' '}
                                <span>{log.duration_ms != null ? `${log.duration_ms} ms` : '—'}</span>
                            </div>
                            <div>
                                <strong className="text-om-muted">Sampled:</strong>{' '}
                                <span>{log.sampled ? 'yes' : 'no'}</span>
                            </div>
                        </div>
                    )}

                    <div className="text-xs text-om-faint pt-3 border-t break-words">
                        <strong>User agent:</strong>{' '}
                        <span>{log.user_agent || '—'}</span>
                    </div>
                </div>
                <div className="flex justify-end p-3 border-t bg-om-panel">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
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

    const columns = useMemo(() => [
        {
            id: 'when',
            accessorFn: (r) => r.created_at,
            header: 'When',
            cell: ({ row }) => {
                const log = row.original;
                return (
                    <span className="text-om-muted whitespace-nowrap text-xs">
                        {log.created_at
                            ? String(log.created_at).replace('T', ' ').replace(/\.\d+Z?$/, '')
                            : '—'}
                    </span>
                );
            },
        },
        {
            id: 'who',
            accessorFn: (r) => r.user?.name ?? 'Guest',
            header: 'Who',
            cell: ({ row }) => (
                <span className="text-om-ink whitespace-nowrap">
                    {row.original.user?.name ?? 'Guest'}
                </span>
            ),
        },
        {
            id: 'what',
            accessorFn: (r) => (r.source === 'audit' ? r.action : r.path),
            header: 'What',
            cell: ({ row }) => {
                const log = row.original;
                return log.source === 'audit' ? (
                    <>
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${ACTION_COLORS[log.action] ?? 'bg-om-chip text-om-muted'}`}>
                            {log.action
                                ? log.action.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
                                : '—'}
                        </span>
                        {' '}
                        <span className="text-om-muted ml-1">
                            {entityLabel(log)}
                        </span>
                    </>
                ) : (
                    <>
                        <span className={`font-mono text-xs px-2 py-0.5 rounded ${METHOD_COLORS[log.method] ?? 'bg-om-chip text-om-muted'}`}>
                            {log.method}
                        </span>
                        {' '}
                        <span className="text-om-muted text-xs font-mono break-all">{log.path}</span>
                        {' '}
                        <span className="text-xs text-om-faint whitespace-nowrap">
                            &rarr; {log.status} &bull; {log.duration_ms}ms
                        </span>
                    </>
                );
            },
        },
        {
            id: 'details',
            header: 'Details',
            enableSorting: false,
            cell: ({ row }) => {
                const log = row.original;
                return (
                    <div className="text-xs text-om-muted">
                        <button
                            type="button"
                            onClick={() => setDetailLog(log)}
                            className="text-om-accent hover:underline text-xs"
                        >
                            Details
                        </button>
                        {log.source === 'audit' && (log.action === 'updated' || log.action === 'created') && (
                            <>
                                <span className="text-om-faintest mx-1">|</span>
                                <a
                                    href={`/admin/audit-logs?user_id=${log.user_id ?? ''}&entity_type=${encodeURIComponent(log.entity_type ?? '')}`}
                                    className="text-om-accent hover:underline"
                                >
                                    View changes
                                </a>
                            </>
                        )}
                        <div className="text-om-faint mt-1">{log.ip_address}</div>
                    </div>
                );
            },
        },
    ], [setDetailLog]);

    return (
        <>
            <Head title="Activity Logs" />
            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">Activity Logs</h1>
                    <p className="text-om-muted mt-1">
                        What users did across the system — entity changes, navigation, auth events.
                    </p>
                </div>

                {/* Filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-5 mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">From</label>
                            <DatePicker
                                value={form.from || null}
                                onChange={(iso) => setForm((f) => ({ ...f, from: iso ?? '' }))}
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">To</label>
                            <DatePicker
                                value={form.to || null}
                                onChange={(iso) => setForm((f) => ({ ...f, to: iso ?? '' }))}
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">User</label>
                            <Dropdown
                                value={form.user_id == null ? '' : String(form.user_id)}
                                onChange={(v) => setForm((f) => ({ ...f, user_id: v }))}
                                options={[
                                    { value: '', label: 'All users' },
                                    ...users.map((u) => ({ value: String(u.id), label: u.name })),
                                ]}
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">Source</label>
                            <Dropdown
                                value={form.source == null ? '' : String(form.source)}
                                onChange={(v) => setForm((f) => ({ ...f, source: v }))}
                                options={[
                                    { value: '', label: 'All sources' },
                                    { value: 'audit', label: 'Entity changes' },
                                    { value: 'request', label: 'Navigation' },
                                ]}
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">Entity</label>
                            <Dropdown
                                value={form.entity_type == null ? '' : String(form.entity_type)}
                                onChange={(v) => setForm((f) => ({ ...f, entity_type: v }))}
                                options={[
                                    { value: '', label: 'All entities' },
                                    ...entityTypes.map((et) => ({ value: String(et), label: String(et).split('\\').pop() })),
                                ]}
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">Action</label>
                            <Dropdown
                                value={form.action == null ? '' : String(form.action)}
                                onChange={(v) => setForm((f) => ({ ...f, action: v }))}
                                options={[
                                    { value: '', label: 'All actions' },
                                    ...actions.map((a) => ({ value: String(a), label: a })),
                                ]}
                                className="w-full"
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-3">
                        <button
                            type="button"
                            onClick={() => apply()}
                            className="px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-white hover:bg-black transition-colors"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            onClick={clear}
                            className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                        >
                            Clear
                        </button>
                        <a
                            href={exportUrl()}
                            className="sm:ml-auto px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>

                {/* Table */}
                <DataTable
                    data={logItems}
                    columns={columns}
                    searchable
                    columnToggle
                    paginated={false}
                    searchPlaceholder="Search activity…"
                    columnsLabel="Columns"
                    columnsMenuLabel="Toggle columns"
                    emptyLabel="No activity in this period."
                />

                {meta && meta.last_page > 1 && (
                    <div className="mt-3">
                        <Pagination meta={meta} links={paginationLinks} onPage={goPage} />
                    </div>
                )}
            </div>

            {detailLog && (
                <DetailModal log={detailLog} onClose={() => setDetailLog(null)} />
            )}
        </>
    );
}

Activity.layout = (page) => <AppLayout>{page}</AppLayout>;
