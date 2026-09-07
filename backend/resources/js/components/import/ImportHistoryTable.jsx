import { useMemo } from 'react';
import { router } from '@inertiajs/react';
import { ProgressBar, StatusPill } from '@openmes/ui';
import AppDataTable from '../AppDataTable';
import { useSyncedShape } from '../../lib/useSyncedShape';
import { __ } from '../../lib/i18n';
import { isActiveStatus, newerRun, progressOf, statusTone } from './statusTone';

/**
 * Recent import runs. The server snapshot seeds the list; the `data_imports`
 * collection then keeps status and counters moving while a job runs.
 *
 * The panel is narrow, so the file cell carries the entity and user underneath
 * the name, and a running job's progress bar replaces the counters until it has
 * some. Per-row errors live on the run page — a row opens it on click — which
 * lists every failed row and offers them as CSV.
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

    const columns = useMemo(() => [
        {
            id: 'file',
            header: __('File'),
            accessorFn: (r) => r.original_filename ?? r.filename ?? '',
            enableSorting: false,
            meta: { flex: true },
            cell: ({ row }) => {
                const r = row.original;

                return (
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-om-ink truncate" title={r.original_filename ?? r.filename}>
                            {r.original_filename ?? r.filename}
                        </p>
                        <p className="text-xs text-om-muted truncate">
                            {entityLabel(r.entity)}
                            {userNames[r.user_id] && <> &middot; {userNames[r.user_id]}</>}
                        </p>
                    </div>
                );
            },
        },
        {
            id: 'rows',
            header: __('Rows'),
            accessorFn: (r) => Number(r.processed_rows ?? 0),
            enableSorting: false,
            cell: ({ row }) => {
                const r = row.original;
                const failed = Number(r.failed_rows ?? 0);

                if (isActiveStatus(r.status)) {
                    return (
                        <div className="w-28">
                            <ProgressBar value={progressOf(r)} />
                            <p className="text-xs text-om-muted mt-1">
                                {Number(r.processed_rows ?? 0)} / {Number(r.total_rows ?? 0)}
                            </p>
                        </div>
                    );
                }

                return (
                    <p className="text-xs text-om-muted whitespace-nowrap">
                        <span className="text-om-running">+{Number(r.created_rows ?? 0)}</span>
                        {' '}
                        <span className="text-om-accent">~{Number(r.updated_rows ?? 0)}</span>
                        {failed > 0 && <span className="text-om-blocked"> {__(':n failed', { n: failed })}</span>}
                    </p>
                );
            },
        },
        {
            id: 'status',
            header: __('Status'),
            accessorFn: (r) => r.status ?? '',
            enableSorting: false,
            cell: ({ row }) => {
                const { tone, label } = statusTone(row.original.status, row.original.failed_rows);

                return <StatusPill status={tone} label={label} />;
            },
        },
    ], [entities, userNames]);

    return (
        <div>
            {/* The table draws its own borders and runs edge to edge, the way the
                mapping screen's preview does; only the heading is inset. */}
            <h2 className="px-5 py-3 text-sm font-bold text-om-ink">{__('Recent imports')}</h2>
            <AppDataTable
                data={rows}
                columns={columns}
                getRowId={(r) => String(r.id)}
                onRowClick={(r) => router.visit(`${basePath}/runs/${r.id}`)}
                searchable={false}
                columnToggle={false}
                // Paged so the panel stays a fixed height instead of growing with
                // the entity's field count / the run history. Ten rows rather than
                // DataTable's default six: these are scanned, not read one by one.
                pageSize={10}
                bodyMaxHeight={null}
                emptyLabel={__('No imports yet.')}
            />
        </div>
    );
}
