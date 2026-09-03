import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { ProgressBar, StatusPill } from '@openmes/ui';
import { useSyncedShape } from '../../lib/useSyncedShape';
import { __ } from '../../lib/i18n';
import { newerRun, progressOf, statusTone } from './statusTone';

/**
 * Recent import runs. The server snapshot seeds the list; the `data_imports`
 * collection then keeps status and counters moving while a job runs.
 */
export default function ImportHistoryTable({ basePath, recentImports = [], entities = [], userNames = {}, limit = 20 }) {
    const { data: live = [] } = useSyncedShape('data_imports');

    const rows = useMemo(() => {
        const byId = new Map();
        for (const r of recentImports) byId.set(r.id, r);
        for (const r of live) byId.set(r.id, { ...(byId.get(r.id) ?? {}), ...newerRun(byId.get(r.id), r) });
        return [...byId.values()].sort((a, b) => b.id - a.id).slice(0, limit);
    }, [recentImports, live, limit]);

    const entityLabel = (key) => entities.find((e) => e.key === key)?.label ?? key;

    return (
        <div className="px-5 py-4">
            <h2 className="text-sm font-bold text-om-ink mb-2">{__('Recent imports')}</h2>
            {rows.length === 0 ? (
                <p className="text-sm text-om-muted">{__('No imports yet.')}</p>
            ) : (
                <ul className="divide-y divide-om-line2">
                    {rows.map((r) => {
                        const { tone, label } = statusTone(r.status, r.failed_rows);
                        const pct = progressOf(r);
                        return (
                            <li key={r.id} className="py-2.5">
                                <div className="flex items-center justify-between gap-2 mb-1">
                                    <Link
                                        href={`${basePath}/runs/${r.id}`}
                                        className="text-sm font-medium text-om-ink hover:text-om-accent truncate"
                                        title={r.original_filename ?? r.filename}
                                    >
                                        {r.original_filename ?? r.filename}
                                    </Link>
                                    <StatusPill status={tone} label={label} />
                                </div>
                                <p className="text-xs text-om-muted mb-1.5">
                                    {entityLabel(r.entity)}
                                    {userNames[r.user_id] && <> &middot; {userNames[r.user_id]}</>}
                                </p>
                                <ProgressBar value={pct} />
                                <p className="text-xs text-om-muted mt-1 flex flex-wrap gap-x-3">
                                    <span>{Number(r.processed_rows ?? 0)} / {Number(r.total_rows ?? 0)}</span>
                                    <span className="text-om-running">+{Number(r.created_rows ?? 0)}</span>
                                    <span className="text-om-accent">~{Number(r.updated_rows ?? 0)}</span>
                                    {Number(r.skipped_rows ?? 0) > 0 && <span>{__(':n skipped', { n: Number(r.skipped_rows ?? 0) })}</span>}
                                    {Number(r.failed_rows ?? 0) > 0 && <span className="text-om-blocked">{__(':n failed', { n: Number(r.failed_rows ?? 0) })}</span>}
                                </p>
                                {Number(r.failed_rows ?? 0) > 0 && (
                                    <details className="mt-1.5">
                                        <summary className="text-xs text-om-blocked cursor-pointer hover:underline">
                                            {__('Show errors (:n)', { n: Number(r.failed_rows ?? 0) })}
                                        </summary>
                                        {(r.errors ?? []).length > 0 && (
                                            <ul className="mt-1.5 space-y-1 rounded-om-sm bg-om-blocked-bg p-2 text-xs">
                                                {r.errors.map((e, i) => (
                                                    <li key={i} className="text-om-blocked break-words">
                                                        {e.row != null && <span className="font-mono">{__('Row')} {e.row}</span>}
                                                        {e.field && <span className="font-mono"> · {e.field}</span>}
                                                        {(e.row != null || e.field) && ': '}
                                                        {e.message}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        <Link href={`${basePath}/runs/${r.id}`} className="mt-1.5 inline-block text-xs text-om-accent hover:underline">
                                            {Number(r.failed_rows ?? 0) > (r.errors ?? []).length
                                                ? __('All :n errors on the run page', { n: Number(r.failed_rows ?? 0) })
                                                : __('Open the run page')}
                                        </Link>
                                    </details>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
