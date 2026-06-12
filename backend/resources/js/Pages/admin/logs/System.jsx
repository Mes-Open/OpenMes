import { useState, useEffect, useRef } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const LEVEL_BADGE = {
    debug:     'bg-om-chip text-om-muted',
    info:      'bg-om-chip text-om-accent',
    notice:    'bg-om-chip text-om-accent',
    warning:   'bg-om-downtime-bg text-om-downtime',
    error:     'bg-om-blocked-bg text-om-blocked',
    critical:  'bg-om-blocked-bg text-om-blocked font-bold',
    alert:     'bg-om-blocked-bg text-om-blocked font-bold',
    emergency: 'bg-om-blocked-bg border border-om-blocked text-om-blocked font-bold',
};

const DEPLOYMENT_STATE_BADGE = {
    completed:   'bg-om-running-bg text-om-running',
    failed:      'bg-om-blocked-bg text-om-blocked',
    rolled_back: 'bg-om-blocked-bg text-om-blocked',
    queued:      'bg-om-chip text-om-accent',
    in_progress: 'bg-om-chip text-om-accent',
};

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

// ─── App log tab ─────────────────────────────────────────────────────────────

function AppTab({ entries: initialEntries, availableDates, date, level, search }) {
    const [form, setForm] = useState({ date: date ?? '', level: level ?? '', search: search ?? '' });
    const [entries, setEntries] = useState(Array.isArray(initialEntries) ? initialEntries : []);
    const [live, setLive] = useState(false);
    const [liveError, setLiveError] = useState(null);
    const timerRef = useRef(null);

    // Keep entries in sync when Inertia re-renders after navigation
    useEffect(() => {
        if (!live) {
            setEntries(Array.isArray(initialEntries) ? initialEntries : []);
        }
    }, [initialEntries]);

    const applyFilters = () => {
        const params = { tab: 'app', ...form };
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        if (!params.tab) params.tab = 'app';
        router.get('/admin/logs/system', params, { preserveState: false });
    };

    const clearFilters = () => {
        router.get('/admin/logs/system', { tab: 'app' }, { preserveState: false });
    };

    const fetchLive = async () => {
        try {
            const r = await fetch('/admin/logs/system/tail', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!r.ok) { setLiveError(`Live tail error: ${r.status}`); return; }
            const d = await r.json();
            setEntries(Array.isArray(d.entries) ? d.entries : []);
            setLiveError(null);
        } catch {
            setLiveError('Live tail unreachable');
        }
    };

    const startLive = () => {
        setLive(true);
        setLiveError(null);
        fetchLive();
        timerRef.current = setInterval(fetchLive, 5000);
    };

    const stopLive = () => {
        setLive(false);
        clearInterval(timerRef.current);
        timerRef.current = null;
    };

    useEffect(() => () => clearInterval(timerRef.current), []);

    const truncate = (s, n) => (!s ? '' : s.length > n ? s.substring(0, n) + '…' : s);

    return (
        <div>
            {/* App log filters */}
            <div className="bg-om-card rounded-om-sm shadow-sm p-5 mb-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">Date</label>
                        {availableDates && availableDates.length > 0 ? (
                            <select
                                value={form.date}
                                onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))}
                                className="form-input w-full"
                            >
                                {availableDates.map((d) => (
                                    <option key={d} value={d}>{d}</option>
                                ))}
                            </select>
                        ) : (
                            <input
                                type="date"
                                value={form.date}
                                onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))}
                                className="form-input w-full"
                            />
                        )}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">Level</label>
                        <select
                            value={form.level}
                            onChange={(e) => setForm((f) => ({ ...f, level: e.target.value }))}
                            className="form-input w-full"
                        >
                            <option value="">All levels</option>
                            {['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'].map((lvl) => (
                                <option key={lvl} value={lvl}>{lvl.charAt(0).toUpperCase() + lvl.slice(1)}</option>
                            ))}
                        </select>
                    </div>
                    <div className="sm:col-span-2">
                        <label className="block text-xs font-medium text-om-muted mb-1">Search</label>
                        <input
                            type="text"
                            value={form.search}
                            onChange={(e) => setForm((f) => ({ ...f, search: e.target.value }))}
                            placeholder="Search message or stack trace…"
                            className="form-input w-full"
                            onKeyDown={(e) => { if (e.key === 'Enter') applyFilters(); }}
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2 mt-3">
                    <button
                        type="button"
                        onClick={applyFilters}
                        className="px-4 py-2 text-sm font-medium rounded-om-sm bg-om-ink text-white hover:bg-black transition-colors"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        onClick={clearFilters}
                        className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors"
                    >
                        Clear
                    </button>
                </div>
            </div>

            {/* Live tail toggle */}
            <div className="flex items-center justify-between mb-3">
                <button
                    type="button"
                    onClick={live ? stopLive : startLive}
                    aria-pressed={live}
                    className="px-4 py-2 text-sm font-medium rounded-om-sm border border-om-line text-om-muted hover:bg-om-bg transition-colors flex items-center gap-2"
                >
                    {live ? (
                        <>
                            <span className="w-2 h-2 rounded-full bg-om-blocked animate-pulse" aria-hidden="true" />
                            Live &mdash; stop
                        </>
                    ) : (
                        <>&#9654; Live tail</>
                    )}
                </button>
                {live && (
                    <span className="text-xs text-om-muted">
                        {liveError
                            ? <span className="text-om-blocked">{liveError}</span>
                            : 'Auto-refreshing every 5s'}
                    </span>
                )}
            </div>

            {/* Entries */}
            <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden">
                {entries.length === 0 ? (
                    <div className="px-4 py-16 text-center text-om-faint">No log entries match your filters.</div>
                ) : entries.map((entry, idx) => (
                    <AppLogEntry key={`${idx}:${entry.timestamp}:${String(entry.message ?? '').substring(0, 40)}`} entry={entry} truncate={truncate} />
                ))}
            </div>

            {entries.length > 0 && (
                <p className="text-xs text-om-faint mt-2">
                    {entries.length} entries shown (most recent first). Older entries beyond the 2 MB tail window are not displayed.
                </p>
            )}
        </div>
    );
}

function AppLogEntry({ entry, truncate }) {
    const [open, setOpen] = useState(false);
    const badgeCls = LEVEL_BADGE[(entry.level ?? '').toLowerCase()] ?? 'bg-om-chip text-om-muted';
    const hasContext = (entry.context && entry.context.trim() !== '') || (entry.message && entry.message.length > 300);

    return (
        <div className="border-b last:border-b-0">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="w-full text-left px-4 py-3 hover:bg-om-bg flex items-start gap-3"
            >
                <span className="font-mono text-xs text-om-muted whitespace-nowrap mt-0.5">{entry.timestamp}</span>
                <span className={`inline-block px-2 py-0.5 rounded text-xs uppercase whitespace-nowrap ${badgeCls}`}>
                    {entry.level}
                </span>
                <span className="text-xs text-om-faint whitespace-nowrap">{entry.environment}</span>
                <span className="text-sm text-om-ink break-all flex-1">{truncate(entry.message, 300)}</span>
            </button>
            {open && hasContext && (
                <pre className="bg-om-panel text-xs text-om-muted px-4 py-3 overflow-x-auto whitespace-pre-wrap break-words border-t">
                    {entry.message}{entry.context ? '\n\n' + entry.context.replace(/\s+$/, '') : ''}
                </pre>
            )}
        </div>
    );
}

// ─── Failed jobs tab ──────────────────────────────────────────────────────────

function FailedJobsTab({ entries, missing }) {
    const logItems = entries?.data ?? (Array.isArray(entries) ? entries : []);
    const meta = entries?.meta ?? null;
    const paginationLinks = entries?.links ?? [];

    const goPage = (page) => {
        router.get('/admin/logs/system', { tab: 'failed_jobs', page }, { preserveState: false });
    };

    if (missing) {
        return (
            <div className="rounded-om-sm border border-om-line bg-om-downtime-bg text-amber-900 p-5">
                <p className="font-medium">Failed jobs table is missing.</p>
                <p className="text-sm mt-1">Run the Laravel queue migrations to enable the failed_jobs table.</p>
            </div>
        );
    }

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm divide-y divide-om-line2">
                    <thead className="bg-om-panel text-xs uppercase text-om-muted">
                        <tr>
                            <th className="text-left px-4 py-2">ID</th>
                            <th className="text-left px-4 py-2">Connection</th>
                            <th className="text-left px-4 py-2">Queue</th>
                            <th className="text-left px-4 py-2">Payload</th>
                            <th className="text-left px-4 py-2">Exception</th>
                            <th className="text-left px-4 py-2">Failed at</th>
                            <th className="text-left px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-om-line2 bg-om-card">
                        {logItems.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-16 text-center text-om-faint">No failed jobs.</td>
                            </tr>
                        ) : logItems.map((job) => (
                            <FailedJobRow key={job.id} job={job} />
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
    );
}

function FailedJobRow({ job }) {
    const [showPayload, setShowPayload] = useState(false);
    const [showTrace, setShowTrace] = useState(false);
    const { csrf_token } = usePage().props;

    const firstLine = (job.exception ?? '').split('\n')[0].substring(0, 200);

    return (
        <tr className="hover:bg-om-bg align-top">
            <td className="px-4 py-3 text-xs font-mono text-om-muted whitespace-nowrap">{job.id}</td>
            <td className="px-4 py-3 text-xs text-om-muted whitespace-nowrap">{job.connection}</td>
            <td className="px-4 py-3 text-xs text-om-muted whitespace-nowrap">{job.queue}</td>
            <td className="px-4 py-3 text-xs text-om-muted">
                <button type="button" onClick={() => setShowPayload((v) => !v)} className="text-om-accent hover:underline">
                    {showPayload ? 'Hide payload' : 'View payload'}
                </button>
                {showPayload && (
                    <pre className="mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words">
                        {String(job.payload ?? '').substring(0, 4000)}
                    </pre>
                )}
            </td>
            <td className="px-4 py-3 text-xs text-om-muted max-w-md">
                <div className="text-om-blocked break-words">{firstLine}</div>
                <button type="button" onClick={() => setShowTrace((v) => !v)} className="mt-1 text-om-accent hover:underline text-xs">
                    {showTrace ? 'Hide stack trace' : 'Show stack trace'}
                </button>
                {showTrace && (
                    <pre className="mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words text-om-muted">
                        {String(job.exception ?? '').substring(0, 8000)}
                    </pre>
                )}
            </td>
            <td className="px-4 py-3 text-xs text-om-muted whitespace-nowrap font-mono">{job.failed_at}</td>
            <td className="px-4 py-3 text-xs whitespace-nowrap">
                <form method="POST" action={`/admin/logs/system/retry-failed-job/${job.uuid}`} className="inline">
                    <input type="hidden" name="_token" value={csrf_token} />
                    <button
                        type="submit"
                        className="px-3 py-1 text-xs rounded bg-om-ink text-white hover:bg-black transition-colors"
                    >
                        Retry
                    </button>
                </form>
            </td>
        </tr>
    );
}

// ─── Deployments tab ──────────────────────────────────────────────────────────

function DeploymentsTab({ entries, missing }) {
    const logItems = entries?.data ?? (Array.isArray(entries) ? entries : []);
    const meta = entries?.meta ?? null;
    const paginationLinks = entries?.links ?? [];

    const goPage = (page) => {
        router.get('/admin/logs/system', { tab: 'deployments', page }, { preserveState: false });
    };

    if (missing) {
        return (
            <div className="rounded-om-sm border border-om-line bg-om-chip text-om-ink p-5">
                <p className="font-medium">Deployment audit log is not available on this build.</p>
                <p className="text-sm mt-1">
                    Deployments log requires v0.12+ schema (system_updates table). This table was introduced by the
                    updater hardening work and has not been merged into this branch yet.
                </p>
                <p className="text-xs mt-2 text-om-accent">
                    Once the system_updates migration lands, this tab will surface start/end timestamps, the upgraded
                    version, success/failure status, and any error output from each deployment.
                </p>
            </div>
        );
    }

    return (
        <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm divide-y divide-om-line2">
                    <thead className="bg-om-panel text-xs uppercase text-om-muted">
                        <tr>
                            <th className="text-left px-4 py-2">Started</th>
                            <th className="text-left px-4 py-2">Finished</th>
                            <th className="text-left px-4 py-2">Version</th>
                            <th className="text-left px-4 py-2">Status</th>
                            <th className="text-left px-4 py-2">Triggered by</th>
                            <th className="text-left px-4 py-2">Output</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-om-line2 bg-om-card">
                        {logItems.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-16 text-center text-om-faint">
                                    No deployments recorded.
                                </td>
                            </tr>
                        ) : logItems.map((row, i) => (
                            <DeploymentRow key={row.id ?? i} row={row} />
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
    );
}

function DeploymentRow({ row }) {
    const [showOutput, setShowOutput] = useState(false);
    const state = row.state ?? 'unknown';
    const badgeCls = DEPLOYMENT_STATE_BADGE[state] ?? 'bg-om-chip text-om-muted';

    return (
        <tr className="hover:bg-om-bg align-top">
            <td className="px-4 py-3 text-xs font-mono text-om-muted whitespace-nowrap">{row.started_at ?? '—'}</td>
            <td className="px-4 py-3 text-xs font-mono text-om-muted whitespace-nowrap">{row.finished_at ?? '—'}</td>
            <td className="px-4 py-3 text-xs text-om-ink whitespace-nowrap font-mono">
                {row.from_version ?? '—'} &rarr; {row.to_version ?? '—'}
            </td>
            <td className="px-4 py-3 text-xs whitespace-nowrap">
                <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${badgeCls}`}>
                    {state.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())}
                </span>
            </td>
            <td className="px-4 py-3 text-xs text-om-muted whitespace-nowrap">{row.triggered_by ?? '—'}</td>
            <td className="px-4 py-3 text-xs text-om-muted">
                {row.error ? (
                    <>
                        <button type="button" onClick={() => setShowOutput((v) => !v)} className="text-om-accent hover:underline">
                            {showOutput ? 'Hide output' : 'View output'}
                        </button>
                        {showOutput && (
                            <pre className="mt-2 bg-om-panel p-2 rounded max-w-xl overflow-x-auto whitespace-pre-wrap break-words">
                                {String(row.error).substring(0, 8000)}
                            </pre>
                        )}
                    </>
                ) : (
                    <span className="text-om-faint">—</span>
                )}
            </td>
        </tr>
    );
}

// ─── Page root ────────────────────────────────────────────────────────────────

const TABS = [
    { key: 'app',          label: 'Application log' },
    { key: 'failed_jobs',  label: 'Failed jobs' },
    { key: 'deployments',  label: 'Deployments' },
];

export default function System() {
    const { tab, entries, availableDates, date, level, search, missing } = usePage().props;

    const switchTab = (key) => {
        router.get('/admin/logs/system', { tab: key }, { preserveState: false });
    };

    return (
        <>
            <Head title="System Logs" />
            <div className="max-w-7xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-om-ink">System Logs</h1>
                    <p className="text-om-muted mt-1">
                        Application errors, failed jobs, and deployment events — for diagnostics.
                    </p>
                </div>

                {/* Tabs */}
                <div className="border-b mb-4 flex gap-1 flex-wrap">
                    {TABS.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => switchTab(key)}
                            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition ${
                                tab === key
                                    ? 'border-om-accent text-om-accent'
                                    : 'border-transparent text-om-muted hover:text-om-ink'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'app' && (
                    <AppTab
                        entries={entries}
                        availableDates={availableDates}
                        date={date}
                        level={level}
                        search={search}
                    />
                )}
                {tab === 'failed_jobs' && (
                    <FailedJobsTab entries={entries} missing={missing ?? false} />
                )}
                {tab === 'deployments' && (
                    <DeploymentsTab entries={entries} missing={missing ?? false} />
                )}
            </div>
        </>
    );
}

System.layout = (page) => <AppLayout>{page}</AppLayout>;
