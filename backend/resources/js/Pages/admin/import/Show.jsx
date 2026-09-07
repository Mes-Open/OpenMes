import { useEffect, useMemo, useRef } from 'react';
import AppDataTable from '../../../components/AppDataTable';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { InlineAlert, ProgressBar, StatusPill } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import PageTrail from '../../../components/PageTrail';
import { useSyncedShape } from '../../../lib/useSyncedShape';
import { __, formatDateTime } from '../../../lib/i18n';
import { isActiveStatus, newerRun, progressOf, statusTone } from '../../../components/import/statusTone';

export default function ImportShow() {
    const { basePath = '/admin/import', import: initial, entity, userName } = usePage().props;

    const { data: live = [] } = useSyncedShape('data_imports');
    const run = useMemo(() => {
        const row = live.find((r) => r.id === initial?.id);
        if (!row) return initial;
        const newest = newerRun(initial, row);
        return { ...initial, ...newest, errors: initial?.errors ?? [] };
    }, [live, initial]);

    const errorColumns = useMemo(() => [
        { accessorKey: 'row', header: __('Row'), cell: ({ getValue }) => <span className="font-mono text-om-muted">{getValue() ?? '—'}</span>, meta: { align: 'right' } },
        { accessorKey: 'field', header: __('Field'), cell: ({ getValue }) => <span className="font-mono text-om-muted">{getValue() ?? '—'}</span> },
        { accessorKey: 'message', header: __('Message'), cell: ({ getValue }) => <span className="text-om-blocked">{getValue()}</span>, meta: { flex: true } },
    ], []);

    const active = isActiveStatus(run?.status);

    // A worker without Reverb configured still moves the page along — and the
    // moment the live row says the run is over, fetch the props once more: the
    // error list only travels with the page, never with the collection.
    const wasActive = useRef(active);
    useEffect(() => {
        if (wasActive.current && !active) router.reload({ only: ['import'] });
        wasActive.current = active;
        if (!active) return undefined;
        const id = setInterval(() => router.reload({ only: ['import'] }), 5000);
        return () => clearInterval(id);
    }, [active]);

    if (!run) return null;

    const { tone, label } = statusTone(run.status, run.failed_rows);
    const pct = progressOf(run);
    const failed = Number(run.failed_rows ?? 0);
    const errors = run.errors ?? [];

    // A dry run produces the same counters as a real one, so every message has
    // to say the counts are what *would* have happened — an unqualified
    // "Created 400" on a run that wrote nothing is the one way to misread this
    // page.
    const dryRun = Boolean(run.dry_run);

    let alert;
    if (run.status === 'FAILED') {
        alert = dryRun
            ? { severity: 'error', title: __('The validation failed'), body: __('The run stopped before finishing, so the file is only partly checked. Nothing was saved.') }
            : { severity: 'error', title: __('The import failed'), body: __('The run stopped before finishing. Rows already written stay; the rest were not imported.') };
    } else if (active) {
        alert = dryRun
            ? { severity: 'info', title: __('Validation in progress'), body: __('Rows are being checked in the background. Nothing is saved — this run only reports what an import would do.') }
            : { severity: 'info', title: __('Import in progress'), body: __('Rows are being processed in the background. You can leave this page; the result stays in the history.') };
    } else if (failed > 0) {
        alert = dryRun
            ? { severity: 'warning', title: __('Validation found problems'), body: __(':n rows would fail to import. See the list below. Nothing was saved.', { n: failed }) }
            : { severity: 'warning', title: __('Import Completed with errors'), body: __(':n rows could not be imported. See the list below.', { n: failed }) };
    } else {
        alert = dryRun
            ? { severity: 'success', title: __('Validation passed'), body: __('Every row would import cleanly. Nothing was saved — use Run the import to apply it.') }
            : { severity: 'success', title: __('Import Completed'), body: __('Every row was processed.') };
    }

    const tiles = [
        [__('Total'), Number(run.total_rows ?? 0), ''],
        [dryRun ? __('Would create') : __('Created'), Number(run.created_rows ?? 0), 'text-om-running'],
        [dryRun ? __('Would update') : __('Updated'), Number(run.updated_rows ?? 0), 'text-om-accent'],
        [__('Skipped'), Number(run.skipped_rows ?? 0), 'text-om-muted'],
        [__('Failed'), failed, failed > 0 ? 'text-om-blocked' : ''],
    ];

    return (
        <div className="w-full">
            <Head title={__('Import run')} />
            <PageTrail append={[{ label: run.original_filename ?? run.filename ?? `#${run.id}` }]} />

            <div className="bg-om-card border-b border-om-line2">
                <div className="flex items-center gap-3 px-5 py-3 border-b border-om-line2 flex-wrap">
                    <h1 className="text-lg font-bold text-om-ink break-all">{run.original_filename ?? run.filename}</h1>
                    <StatusPill status={tone} label={label} />
                    {dryRun && <StatusPill status="pending" label={__('Validation only')} />}
                    <span className="text-[12px] text-om-muted">
                        {entity?.label ?? run.entity}
                        {userName && <> &middot; {userName}</>}
                        {run.created_at && <> &middot; {formatDateTime(run.created_at)}</>}
                    </span>
                    <div className="ml-auto flex items-center gap-2">
                        <Link href={basePath} className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors">
                            {__('All imports')}
                        </Link>
                        {/* A finished validation still has its file and mapping, so the
                            real import is one click away — re-uploading to act on what
                            the validation just reported would defeat the point. */}
                        {dryRun && !active && run.token && entity?.slug && (
                            <Link href={`${basePath}/${entity.slug}/map/${run.token}`} className="inline-flex items-center justify-center rounded-om-sm bg-om-ink px-4 py-[9px] text-[13px] font-semibold text-om-on-ink hover:bg-om-ink-hover transition-colors">
                                {__('Run the import')}
                            </Link>
                        )}
                        {entity?.slug && (
                            <Link href={`${basePath}/${entity.slug}`} className={`inline-flex items-center justify-center rounded-om-sm px-4 py-[9px] text-[13px] font-semibold transition-colors ${dryRun && !active && run.token ? 'border border-om-line text-om-ink hover:bg-om-chip' : 'bg-om-ink text-om-on-ink hover:bg-om-ink-hover'}`}>
                                {__('Import another file')}
                            </Link>
                        )}
                    </div>
                </div>

                <div className="px-5 py-3 border-b border-om-line2">
                    <InlineAlert severity={alert.severity} title={alert.title}>{alert.body}</InlineAlert>
                </div>

                {active && (
                    <div className="px-5 py-3 border-b border-om-line2">
                        <div className="flex justify-between text-xs text-om-muted mb-2">
                            <span>{__('Progress')}</span>
                            <span>{Number(run.processed_rows ?? 0)} / {Number(run.total_rows ?? 0)}</span>
                        </div>
                        <ProgressBar value={pct} />
                    </div>
                )}

                <div className="grid grid-cols-2 md:grid-cols-5 divide-x divide-om-line2 border-b border-om-line2">
                    {tiles.map(([tileLabel, value, toneCls]) => (
                        <div key={tileLabel} className="px-5 py-3">
                            <p className="font-mono text-[10px] uppercase tracking-wide text-om-faint">{tileLabel}</p>
                            <p className={`text-2xl font-semibold ${toneCls}`}>{value}</p>
                        </div>
                    ))}
                </div>

                {errors.length > 0 && (
                    <div>
                        <div className="flex items-center justify-between px-5 py-3">
                            <h2 className="text-sm font-bold text-om-ink">{__('Errors')} <span className="text-xs font-normal text-om-muted">({errors.length})</span></h2>
                            <a href={`${basePath}/runs/${run.id}/errors.csv`} className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors">
                                {__('Download errors as CSV')}
                            </a>
                        </div>
                        <AppDataTable
                            data={errors}
                            columns={errorColumns}
                            getRowId={(_, index) => String(index)}
                            columnToggle={false}
                            bodyMaxHeight={null}
                            pageSize={25}
                            emptyLabel={__('Every row was processed.')}
                            rangeLabel={(start, end, total) => (total === 0 ? __('0 results') : `${start}–${end} / ${total}`)}
                        />
                        {failed > errors.length && (
                            <p className="px-5 py-2 text-xs text-om-faint">{__('Only the first :n errors are kept.', { n: errors.length })}</p>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

ImportShow.layout = (page) => <AppLayout>{page}</AppLayout>;
