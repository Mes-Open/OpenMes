import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

const ACTION_STYLES = {
    created: 'bg-om-running-bg text-om-running',
    updated: 'bg-om-chip text-om-accent',
    deleted: 'bg-om-blocked-bg text-om-blocked',
};

function ActionBadge({ action }) {
    const cls = ACTION_STYLES[action] ?? 'bg-om-chip text-om-muted';
    return (
        <span className={`px-2 py-1 rounded text-xs font-medium ${cls}`}>
            {action ? action.charAt(0).toUpperCase() + action.slice(1) : '—'}
        </span>
    );
}

function ExpandableChanges({ log }) {
    const [expanded, setExpanded] = useState(false);

    if (log.action === 'updated' && log.after_state) {
        return (
            <div>
                <button
                    type="button"
                    onClick={() => setExpanded((v) => !v)}
                    className="text-om-accent hover:text-om-accent text-sm"
                >
                    {expanded ? 'Hide' : 'View'} Changes
                </button>
                {expanded && (
                    <div className="mt-2 p-3 bg-om-panel rounded text-xs">
                        {Object.entries(log.after_state).map(([field, newValue]) => (
                            <div key={field} className="mb-1">
                                <strong>{field}:</strong>{' '}
                                {String(log.before_state?.[field] ?? 'null')} &rarr; {String(newValue)}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    if (log.action === 'created') {
        return (
            <span className="text-om-muted text-sm">
                Created with {Object.keys(log.after_state ?? {}).length} fields
            </span>
        );
    }

    if (log.action === 'deleted') {
        return <span className="text-om-muted text-sm">Record deleted</span>;
    }

    return null;
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

export default function AuditLogs() {
    const { auditLogs, entityTypes = [], users = [], filters = {} } = usePage().props;

    const [showFilters, setShowFilters] = useState(false);
    const [form, setForm] = useState({
        entity_type: filters.entity_type ?? '',
        user_id: filters.user_id ?? '',
        action: filters.action ?? '',
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });

    const apply = (overrides = {}) => {
        const params = { ...form, ...overrides };
        // strip empty
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/admin/audit-logs', params, { preserveState: false });
    };

    const clear = () => {
        router.get('/admin/audit-logs', {}, { preserveState: false });
    };

    const goPage = (page) => {
        const params = { ...form, page };
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        router.get('/admin/audit-logs', params, { preserveState: false });
    };

    const exportUrl = () => {
        const p = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => { if (v) p.set(k, v); });
        const qs = p.toString();
        return `/admin/audit-logs/export${qs ? '?' + qs : ''}`;
    };

    const logs = auditLogs?.data ?? [];
    const meta = auditLogs?.meta ?? null;
    const paginationLinks = auditLogs?.links ?? [];

    return (
        <>
            <Head title="Audit Logs" />
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">Audit Logs</h1>
                    <p className="text-om-muted mt-2">Track all system changes and user activities</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-5 mb-6">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-bold text-om-ink">Filters</h2>
                        <button
                            type="button"
                            onClick={() => setShowFilters((v) => !v)}
                            className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                        >
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </button>
                    </div>

                    {showFilters && (
                        <div>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">Entity Type</label>
                                    <select
                                        value={form.entity_type}
                                        onChange={(e) => setForm((f) => ({ ...f, entity_type: e.target.value }))}
                                        className="form-input w-full"
                                    >
                                        <option value="">All Types</option>
                                        {entityTypes.map((t) => (
                                            <option key={t} value={t}>{t}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">User</label>
                                    <select
                                        value={form.user_id}
                                        onChange={(e) => setForm((f) => ({ ...f, user_id: e.target.value }))}
                                        className="form-input w-full"
                                    >
                                        <option value="">All Users</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name} ({u.username})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">Action</label>
                                    <select
                                        value={form.action}
                                        onChange={(e) => setForm((f) => ({ ...f, action: e.target.value }))}
                                        className="form-input w-full"
                                    >
                                        <option value="">All Actions</option>
                                        <option value="created">Created</option>
                                        <option value="updated">Updated</option>
                                        <option value="deleted">Deleted</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">Start Date</label>
                                    <input
                                        type="date"
                                        value={form.start_date}
                                        onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))}
                                        className="form-input w-full"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">End Date</label>
                                    <input
                                        type="date"
                                        value={form.end_date}
                                        onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))}
                                        className="form-input w-full"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => apply()}
                                    className="px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-white hover:bg-black transition-colors"
                                >
                                    Apply Filters
                                </button>
                                <button
                                    type="button"
                                    onClick={clear}
                                    className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                                >
                                    Clear Filters
                                </button>
                                <a
                                    href={exportUrl()}
                                    className="ml-auto px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                                >
                                    Export to CSV
                                </a>
                            </div>
                        </div>
                    )}
                </div>

                {/* Table */}
                <div className="bg-om-card rounded-om-sm shadow-sm">
                    {logs.length === 0 ? (
                        <div className="text-center py-12 text-om-muted">No audit logs found</div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-om-line2">
                                    <thead className="bg-om-panel">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-om-muted uppercase tracking-wider">Timestamp</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-om-muted uppercase tracking-wider">User</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-om-muted uppercase tracking-wider">Entity</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-om-muted uppercase tracking-wider">Action</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-om-muted uppercase tracking-wider">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-om-card divide-y divide-om-line2">
                                        {logs.map((log) => (
                                            <tr key={log.id} className="hover:bg-om-bg">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-om-muted">
                                                    {log.created_at
                                                        ? String(log.created_at).replace('T', ' ').replace(/\.\d+Z?$/, '')
                                                        : '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-om-ink">
                                                    {log.user?.name ?? 'System'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-om-muted">
                                                    {log.entity_type
                                                        ? String(log.entity_type).split('\\').pop()
                                                        : '—'}
                                                    {log.entity_id ? ` #${log.entity_id}` : ''}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <ActionBadge action={log.action} />
                                                </td>
                                                <td className="px-6 py-4 text-sm text-om-muted">
                                                    <ExpandableChanges log={log} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {meta && (
                                <div className="mt-4 mb-2 px-6">
                                    <Pagination meta={meta} links={paginationLinks} onPage={goPage} />
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

AuditLogs.layout = (page) => <AppLayout>{page}</AppLayout>;
