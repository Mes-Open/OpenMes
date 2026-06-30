import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const ACTION_STYLES = {
    created: 'bg-om-running-bg text-om-running',
    updated: 'bg-om-chip text-om-accent',
    deleted: 'bg-om-blocked-bg text-om-blocked',
};

function ActionBadge({ action }) {
    const cls = ACTION_STYLES[action] ?? 'bg-om-chip text-om-muted';
    return (
        <span className={`px-2 py-1 rounded text-xs font-medium ${cls}`}>
            {action ? __(action.charAt(0).toUpperCase() + action.slice(1)) : '—'}
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
                    {expanded ? __('Hide') : __('View')} {__('Changes')}
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
                {__('Created with :count fields', { count: Object.keys(log.after_state ?? {}).length })}
            </span>
        );
    }

    if (log.action === 'deleted') {
        return <span className="text-om-muted text-sm">{__('Record deleted')}</span>;
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
                            ? 'bg-om-ink text-om-on-ink border-om-accent'
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

    const columns = useMemo(() => [
        {
            id: 'timestamp',
            accessorFn: (log) => log.created_at ?? '',
            header: __('Timestamp'),
            cell: ({ row }) => {
                const log = row.original;
                return log.created_at
                    ? String(log.created_at).replace('T', ' ').replace(/\.\d+Z?$/, '')
                    : '—';
            },
            meta: { align: 'left' },
        },
        {
            id: 'user',
            accessorFn: (log) => log.user?.name ?? __('System'),
            header: __('User'),
            cell: ({ row }) => row.original.user?.name ?? __('System'),
            meta: { align: 'left' },
        },
        {
            id: 'entity',
            accessorFn: (log) =>
                (log.entity_type ? String(log.entity_type).split('\\').pop() : '—') +
                (log.entity_id ? ` #${log.entity_id}` : ''),
            header: __('Entity'),
            cell: ({ row }) => {
                const log = row.original;
                return (
                    <>
                        {log.entity_type ? String(log.entity_type).split('\\').pop() : '—'}
                        {log.entity_id ? ` #${log.entity_id}` : ''}
                    </>
                );
            },
            meta: { align: 'left' },
        },
        {
            id: 'action',
            accessorFn: (log) => log.action ?? '',
            header: __('Action'),
            cell: ({ row }) => <ActionBadge action={row.original.action} />,
            meta: { align: 'left' },
        },
        {
            id: 'details',
            header: __('Details'),
            enableSorting: false,
            cell: ({ row }) => <ExpandableChanges log={row.original} />,
            meta: { align: 'left' },
        },
    ], []);

    return (
        <>
            <Head title={__('Audit Logs')} />
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">{__('Audit Logs')}</h1>
                    <p className="text-om-muted mt-2">{__('Track all system changes and user activities')}</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-5 mb-6">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-bold text-om-ink">{__('Filters')}</h2>
                        <button
                            type="button"
                            onClick={() => setShowFilters((v) => !v)}
                            className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                        >
                            {showFilters ? __('Hide Filters') : __('Show Filters')}
                        </button>
                    </div>

                    {showFilters && (
                        <div>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">{__('Entity Type')}</label>
                                    <Dropdown
                                        options={[
                                            { value: '', label: __('All Types') },
                                            ...entityTypes.map((t) => ({ value: String(t), label: t })),
                                        ]}
                                        value={form.entity_type == null ? '' : String(form.entity_type)}
                                        onChange={(v) => setForm((f) => ({ ...f, entity_type: v }))}
                                        className="w-full"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">{__('User')}</label>
                                    <Dropdown
                                        options={[
                                            { value: '', label: __('All Users') },
                                            ...users.map((u) => ({ value: String(u.id), label: `${u.name} (${u.username})` })),
                                        ]}
                                        value={form.user_id == null ? '' : String(form.user_id)}
                                        onChange={(v) => setForm((f) => ({ ...f, user_id: v }))}
                                        className="w-full"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">{__('Action')}</label>
                                    <Dropdown
                                        options={[
                                            { value: '', label: __('All Actions') },
                                            { value: 'created', label: __('Created') },
                                            { value: 'updated', label: __('Updated') },
                                            { value: 'deleted', label: __('Deleted') },
                                        ]}
                                        value={form.action == null ? '' : String(form.action)}
                                        onChange={(v) => setForm((f) => ({ ...f, action: v }))}
                                        className="w-full"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">{__('Start Date')}</label>
                                    <DatePicker
                                        value={form.start_date || null}
                                        onChange={(iso) => setForm((f) => ({ ...f, start_date: iso ?? '' }))}
                                        className="w-full"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-om-muted mb-1">{__('End Date')}</label>
                                    <DatePicker
                                        value={form.end_date || null}
                                        onChange={(iso) => setForm((f) => ({ ...f, end_date: iso ?? '' }))}
                                        className="w-full"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => apply()}
                                    className="px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-om-on-ink hover:bg-om-ink-hover transition-colors"
                                >
                                    {__('Apply Filters')}
                                </button>
                                <button
                                    type="button"
                                    onClick={clear}
                                    className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                                >
                                    {__('Clear Filters')}
                                </button>
                                <a
                                    href={exportUrl()}
                                    className="ml-auto px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                                >
                                    {__('Export to CSV')}
                                </a>
                            </div>
                        </div>
                    )}
                </div>

                {/* Table */}
                <DataTable
                    data={logs}
                    columns={columns}
                    searchable
                    columnToggle
                    paginated={false}
                    searchPlaceholder="Search audit logs…"
                    columnsLabel="Columns"
                    columnsMenuLabel="Toggle columns"
                    emptyLabel={__('No audit logs found')}
                />

                {meta && (
                    <div className="mt-4">
                        <Pagination meta={meta} links={paginationLinks} onPage={goPage} />
                    </div>
                )}
            </div>
        </>
    );
}

AuditLogs.layout = (page) => <AppLayout>{page}</AppLayout>;
