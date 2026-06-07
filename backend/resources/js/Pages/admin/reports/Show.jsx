import { Fragment } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDateTime, formatNumber } from '../../../lib/i18n';

const STATUS_BADGE = {
    DONE: 'bg-green-100 text-green-800',
    CANCELLED: 'bg-gray-100 text-gray-600',
    REJECTED: 'bg-red-100 text-red-700',
};

function fmtDuration(min) {
    if (min == null) return '—';
    const total = Math.round(min);
    const h = Math.floor(total / 60);
    const m = total % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

const dt = (v) => (v ? formatDateTime(v) : '—');

export default function ReportShow() {
    const { workOrder: wo } = usePage().props;

    return (
        <>
            <Head title={`${wo.order_no} — ${__('History')}`} />
            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <Link href="/admin/reports" className="text-blue-600 hover:text-blue-800 text-sm">
                        ← {__('Back to History')}
                    </Link>
                    <div className="flex items-center gap-3 mt-2">
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{wo.order_no}</h1>
                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${STATUS_BADGE[wo.status] ?? 'bg-gray-100 text-gray-600'}`}>
                            {__(wo.status)}
                        </span>
                        {wo.on_time === true && (
                            <span className="px-3 py-1 rounded-full text-sm bg-green-50 text-green-700">{__('On-time')}</span>
                        )}
                        {wo.on_time === false && (
                            <span className="px-3 py-1 rounded-full text-sm bg-red-50 text-red-700">{__('Late')}</span>
                        )}
                    </div>
                    <p className="text-gray-600 dark:text-gray-400 mt-1">
                        {wo.product?.name}
                        {wo.product?.code && <span className="font-mono text-xs text-gray-400 ml-1">{wo.product.code}</span>}
                        {wo.line_name && <span> · {wo.line_name}</span>}
                    </p>
                </div>

                {/* Identity + quantities + timing */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Card title={__('Quantities')}>
                        <Row label={__('Planned')} value={formatNumber(wo.planned_qty)} />
                        <Row label={__('Produced')} value={formatNumber(wo.produced_qty)} />
                        <Row
                            label={__('Yield')}
                            value={wo.planned_qty > 0 ? `${Math.round((wo.produced_qty / wo.planned_qty) * 100)}%` : '—'}
                        />
                    </Card>
                    <Card title={__('Timing')}>
                        <Row label={__('Created')} value={dt(wo.dates.created_at)} />
                        <Row label={__('Due')} value={dt(wo.dates.due_date)} />
                        <Row label={__('Completed')} value={dt(wo.dates.completed_at)} />
                        <Row label={__('Execution')} value={fmtDuration(wo.execution_minutes)} />
                    </Card>
                    <Card title={__('Process')}>
                        <Row label={__('Template')} value={wo.template?.name ?? '—'} />
                        <Row label={__('Version')} value={wo.template?.version != null ? `v${wo.template.version}` : '—'} />
                        {wo.description && <Row label={__('Description')} value={wo.description} />}
                    </Card>
                </div>

                {/* Batches */}
                <div className="space-y-4">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">{__('Batches')}</h2>
                    {wo.batches.length === 0 && (
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6 text-gray-500 text-sm">
                            {__('No batches recorded.')}
                        </div>
                    )}
                    {wo.batches.map((b) => (
                        <BatchCard key={b.id} batch={b} />
                    ))}
                </div>

                {/* Issues */}
                {wo.issues.length > 0 && (
                    <div>
                        <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100 mb-3">{__('Issues during this order')}</h2>
                        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm divide-y divide-gray-100 dark:divide-slate-700">
                            {wo.issues.map((i) => (
                                <div key={i.id} className="p-4">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium text-gray-800 dark:text-gray-200">{i.type ?? __('Issue')}</span>
                                        {i.is_blocking && (
                                            <span className="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">{__('blocking')}</span>
                                        )}
                                        <span className="text-xs text-gray-400">{__(i.status)}</span>
                                    </div>
                                    {i.description && <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{i.description}</p>}
                                    <p className="text-xs text-gray-400 mt-1">
                                        {i.reported_by ?? '—'} · {dt(i.reported_at)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function BatchCard({ batch: b }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-3">
                    <h3 className="text-lg font-bold text-gray-800 dark:text-gray-100">
                        {__('Batch')} #{b.batch_number}
                    </h3>
                    {b.lot_number && (
                        <span className="px-2 py-1 rounded bg-blue-50 text-blue-700 font-mono text-sm">
                            {__('LOT')}: {b.lot_number}
                        </span>
                    )}
                    <span className="text-xs text-gray-400">{__(b.status)}</span>
                </div>
                <div className="text-sm text-gray-500">
                    {formatNumber(b.produced_qty)} / {formatNumber(b.target_qty)}
                    {b.scrap_qty > 0 && <span className="text-red-500 ml-2">{__('scrap')}: {formatNumber(b.scrap_qty)}</span>}
                </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-1 text-sm mb-4">
                <Row label={__('Started')} value={dt(b.started_at)} />
                <Row label={__('Completed')} value={dt(b.completed_at)} />
                <Row label={__('Released')} value={dt(b.released_at)} />
                <Row label={__('Released by')} value={b.released_by ?? '—'} />
                {b.workstation && <Row label={__('Workstation')} value={b.workstation} />}
                {b.udi_code && <Row label={__('UDI')} value={b.udi_code} />}
                {b.expiry_date && <Row label={__('Expiry')} value={dt(b.expiry_date)} />}
            </div>

            {/* Steps */}
            {b.steps.length === 0 && (
                <p className="text-sm text-gray-400 italic">{__('No production steps were recorded for this batch.')}</p>
            )}
            {b.steps.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase border-b border-gray-200 dark:border-slate-700">
                                <th className="py-2 pr-3">#</th>
                                <th className="py-2 pr-3">{__('Step')}</th>
                                <th className="py-2 pr-3">{__('Workstation')}</th>
                                <th className="py-2 pr-3">{__('Started')}</th>
                                <th className="py-2 pr-3">{__('Completed')}</th>
                                <th className="py-2 pr-3">{__('Duration')}</th>
                                <th className="py-2 pr-3">{__('Operator')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
                            {b.steps.map((s) => (
                                <Fragment key={s.step_number}>
                                    <tr>
                                        <td className="py-2 pr-3 text-gray-500">{s.step_number}</td>
                                        <td className="py-2 pr-3 font-medium text-gray-800 dark:text-gray-200">{s.name}</td>
                                        <td className="py-2 pr-3 text-gray-600 dark:text-gray-400">{s.workstation ?? '—'}</td>
                                        <td className="py-2 pr-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{dt(s.started_at)}</td>
                                        <td className="py-2 pr-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{dt(s.completed_at)}</td>
                                        <td className="py-2 pr-3 text-gray-600 dark:text-gray-400">
                                            {s.duration_minutes != null ? fmtDuration(s.duration_minutes) : '—'}
                                        </td>
                                        <td className="py-2 pr-3 text-gray-600 dark:text-gray-400">
                                            {s.completed_by ?? s.started_by ?? '—'}
                                        </td>
                                    </tr>
                                    {s.consumptions.length > 0 && (
                                        <tr>
                                            <td></td>
                                            <td colSpan={6} className="pb-2">
                                                <div className="text-xs text-gray-500">
                                                    {__('Materials consumed')}:{' '}
                                                    {s.consumptions.map((c, ci) => (
                                                        <span key={ci} className="font-mono mr-3">
                                                            {c.material_code ?? c.material_name}
                                                            {c.lot_number && <span className="text-blue-600"> [{c.lot_number}]</span>}
                                                            <span className="text-gray-400"> ×{formatNumber(c.quantity)}</span>
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Quality checks */}
            {b.quality_checks.length > 0 && (
                <div className="mt-4 border-t border-gray-100 dark:border-slate-700 pt-3">
                    <div className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{__('Quality checks')}</div>
                    {b.quality_checks.map((qc) => (
                        <div key={qc.id} className="text-sm mb-2">
                            <span className={qc.all_passed ? 'text-green-600' : 'text-red-600'}>
                                {qc.all_passed ? __('Passed') : __('Failed')}
                            </span>
                            <span className="text-gray-400 text-xs ml-2">
                                {qc.checked_by ?? '—'} · {dt(qc.checked_at)}
                            </span>
                            {qc.samples.length > 0 && (
                                <span className="text-xs text-gray-500 ml-2">
                                    ({qc.samples.length} {__('samples')})
                                </span>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Output lots */}
            {b.output_lots.length > 0 && (
                <div className="mt-3 text-xs text-gray-500">
                    {__('Output lots')}:{' '}
                    {b.output_lots.map((l, li) => (
                        <span key={li} className="font-mono text-blue-600 mr-2">
                            {l.lot_number}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-5">
            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">{title}</h3>
            <div className="space-y-1">{children}</div>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4 text-sm">
            <span className="text-gray-500">{label}</span>
            <span className="text-gray-800 dark:text-gray-200 font-medium text-right">{value}</span>
        </div>
    );
}

ReportShow.layout = (page) => <AppLayout>{page}</AppLayout>;
