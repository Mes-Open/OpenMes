import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

/**
 * Traceability / genealogy console. Resolves a finished LOT, material lot,
 * supplier LOT or serial number (server-side) and renders its genealogy.
 * Props: { term, result } — result.type is 'batch' | 'material_lot' | 'serial'.
 */
export default function TraceabilityIndex() {
    const { term = '', result = null } = usePage().props;
    const [q, setQ] = useState(term);

    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/traceability', { q }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title={__('Traceability')} />

            <div className="p-6 max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{__('Traceability')}</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {__('Trace a finished LOT, material lot, supplier LOT or serial number through its full genealogy.')}
                    </p>
                </div>

                {/* Search */}
                <form onSubmit={submit} className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{__('Search')}</label>
                    <div className="flex gap-3">
                        <input
                            type="text"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            autoFocus
                            placeholder={__('Finished LOT, material lot, supplier LOT or serial number…')}
                            className="form-input flex-1"
                        />
                        <button type="submit" className="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                            {__('Trace')}
                        </button>
                    </div>
                </form>

                {term !== '' && !result && (
                    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                        <svg className="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p className="mt-3 text-gray-500 dark:text-gray-400">
                            {__('No finished LOT, material lot or serial number matches')} <strong>{term}</strong>.
                        </p>
                    </div>
                )}

                {result?.type === 'batch' && <BatchResult data={result.data} />}
                {result?.type === 'material_lot' && <MaterialLotResult forward={result.forward} backward={result.backward} />}
                {result?.type === 'serial' && <SerialResult unit={result.data} />}
            </div>
        </>
    );
}

TraceabilityIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── helpers ─────────────────────────────────────────────────────────── */

function Card({ children, className = '' }) {
    return (
        <div className={`bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 ${className}`}>
            {children}
        </div>
    );
}

function traceLink(lotNumber) {
    return `/admin/traceability?q=${encodeURIComponent(lotNumber)}`;
}

/* ── Finished batch (backward genealogy) ─────────────────────────────── */

function BatchResult({ data }) {
    const b = data.batch;
    const lots = data.distinct_input_lots ?? [];

    return (
        <div className="space-y-4">
            <Card>
                <div className="flex items-start justify-between flex-wrap gap-2">
                    <div>
                        <span className="text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">{__('Finished LOT')}</span>
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white font-mono">{b.lot_number}</h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {__('Work Order')}: <span className="font-medium">{b.work_order?.order_no ?? '—'}</span>
                            {' · '}{__('Product')}: <span className="font-medium">{b.work_order?.product ?? '—'}</span>
                            {' · '}{__('Batch')} #{b.batch_number}
                        </p>
                    </div>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                        {__('Backward trace')}
                    </span>
                </div>
            </Card>

            <Card>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-3">{__('Ingredient lots')} ({lots.length})</h3>
                {lots.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{__('No material lots were recorded as consumed for this batch.')}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr className="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th className="px-3 py-2">{__('Material')}</th>
                                    <th className="px-3 py-2">{__('LOT')}</th>
                                    <th className="px-3 py-2">{__('Supplier LOT')}</th>
                                    <th className="px-3 py-2">{__('Status')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {lots.map((lot, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2 text-gray-800 dark:text-gray-200">
                                            {lot.material ?? '—'} <span className="text-xs text-gray-400 font-mono">{lot.material_code}</span>
                                        </td>
                                        <td className="px-3 py-2 font-mono">
                                            <Link href={traceLink(lot.lot_number)} className="text-blue-600 dark:text-blue-400 hover:underline">{lot.lot_number}</Link>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-gray-600 dark:text-gray-400">{lot.supplier_lot_no ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{lot.status}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-3">{__('Process history')}</h3>
                <div className="space-y-3">
                    {b.steps.map((step) => (
                        <div key={step.id} className={`border-l-2 pl-4 py-1 ${step.status === 'DONE' ? 'border-green-400' : 'border-gray-200 dark:border-gray-600'}`}>
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-bold text-gray-800 dark:text-gray-100">{__('Step')} {step.step_number}: {step.name}</span>
                                {step.workstation && <span className="text-xs px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">{step.workstation}</span>}
                                {step.completed_by && <span className="text-xs text-gray-500 dark:text-gray-400">{__('by')} {step.completed_by}</span>}
                                {step.completed_at && <span className="text-xs text-gray-400 dark:text-gray-500">{step.completed_at}</span>}
                            </div>
                            {step.consumptions.length > 0 && (
                                <ul className="mt-1 ml-1 text-sm text-gray-600 dark:text-gray-400 space-y-0.5">
                                    {step.consumptions.map((c, i) => (
                                        <li key={i} className="flex items-center gap-2">
                                            <span className="w-1 h-1 rounded-full bg-gray-400" />
                                            <span className="font-mono">{c.lot_number}</span>
                                            <span className="text-gray-400 dark:text-gray-500">{c.material}</span>
                                            <span className="text-gray-500 dark:text-gray-400">— {c.quantity.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}
                </div>

                {b.output_lots.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <h4 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{__('Output lots')}</h4>
                        {b.output_lots.map((out, i) => (
                            <Link key={i} href={traceLink(out.lot_number)} className="inline-block mr-2 mb-2 px-2 py-1 rounded bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs font-mono hover:bg-purple-100">
                                {out.lot_number}
                            </Link>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}

/* ── Material lot (forward + backward) ───────────────────────────────── */

function MaterialLotResult({ forward, backward }) {
    return (
        <div className="space-y-4">
            <Card>
                <span className="text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">{__('Material lot')}</span>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-white font-mono">{forward.lot.lot_number}</h2>
                {backward.supplier_lot_no && (
                    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">{__('Supplier LOT')}: <span className="font-mono">{backward.supplier_lot_no}</span></p>
                )}
            </Card>

            {/* Forward */}
            <Card>
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-lg font-bold text-gray-900 dark:text-white">{__('Forward trace — where did this lot go?')}</h3>
                    <span className="px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                        {forward.work_orders.length} {__('work orders')}
                    </span>
                </div>
                {forward.work_orders.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{__('This lot has not been consumed yet.')}</p>
                ) : (
                    <>
                        <ul className="space-y-2">
                            {forward.work_orders.map((wo, i) => (
                                <li key={i} className="flex items-center gap-3 text-sm">
                                    <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">{wo.order_no}</span>
                                    <span className="text-gray-500 dark:text-gray-400">{wo.product}</span>
                                    <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{wo.status}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-3">
                            {__('Total consumed')}: {Number(forward.total_consumed).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                    </>
                )}
            </Card>

            {/* Backward */}
            <Card>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-3">{__('Backward trace — what fed into this lot?')}</h3>
                {backward.source_batch_id ? (
                    <>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            {__('Produced by batch')} #{backward.source_batch?.batch_number ?? backward.source_batch_id}
                            {backward.source_batch?.lot_number && (
                                <> (<Link href={traceLink(backward.source_batch.lot_number)} className="text-blue-600 dark:text-blue-400 hover:underline font-mono">{backward.source_batch.lot_number}</Link>)</>
                            )}
                        </p>
                        <IngredientTree node={backward} />
                    </>
                ) : (
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                        <p>{__('Inbound raw lot (terminal).')}</p>
                        {backward.supplier_reference && <p className="mt-1">{__('Supplier reference')}: <span className="font-mono">{backward.supplier_reference}</span></p>}
                        {backward.inspection_id && <p className="mt-1">{__('Inbound inspection')} #{backward.inspection_id}</p>}
                    </div>
                )}
            </Card>
        </div>
    );
}

/** Recursive backward genealogy node (mirrors the old _ingredient-tree partial). */
function IngredientTree({ node }) {
    if (!node.ingredients || node.ingredients.length === 0) return null;

    return (
        <ul className="ml-4 border-l border-gray-200 dark:border-gray-700 pl-4 space-y-2">
            {node.ingredients.map((child, i) => (
                <li key={i}>
                    <div className="flex items-center gap-2 flex-wrap text-sm">
                        <Link href={traceLink(child.lot?.lot_number)} className="font-mono font-medium text-blue-600 dark:text-blue-400 hover:underline">
                            {child.lot?.lot_number ?? '—'}
                        </Link>
                        <span className="text-gray-500 dark:text-gray-400">{child.material?.name ?? ''}</span>
                        {child.supplier_lot_no && (
                            <span className="text-xs text-gray-400 dark:text-gray-500">{__('Supplier LOT')}: <span className="font-mono">{child.supplier_lot_no}</span></span>
                        )}
                        {child.source_batch_id && (
                            <span className="text-xs px-2 py-0.5 rounded bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">{__('semi-finished')}</span>
                        )}
                    </div>
                    {child.truncated ? (
                        <p className="text-xs text-amber-600 dark:text-amber-400 ml-1">{__('Trace truncated (max depth reached).')}</p>
                    ) : (
                        <IngredientTree node={child} />
                    )}
                </li>
            ))}
        </ul>
    );
}

/* ── Serial unit (per-unit history) ──────────────────────────────────── */

function SerialResult({ unit }) {
    const RESULT_BADGE = {
        pass: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        fail: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    };

    return (
        <div className="space-y-4">
            <Card>
                <span className="text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">{__('Serial unit')}</span>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-white font-mono">{unit.serial_no}</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {__('Product')}: <span className="font-medium">{unit.product ?? '—'}</span>
                    {unit.work_order && <> · {__('Work Order')}: <span className="font-medium">{unit.work_order}</span></>}
                    {' · '}<span className="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{unit.status}</span>
                </p>
            </Card>

            <Card>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-3">{__('Process history')} ({unit.history.length})</h3>
                {unit.history.length === 0 ? (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{__('No processing steps recorded for this unit yet.')}</p>
                ) : (
                    <div className="space-y-3">
                        {unit.history.map((h, i) => (
                            <div key={i} className={`border-l-2 pl-4 py-1 ${h.result === 'fail' ? 'border-red-400' : 'border-green-400'}`}>
                                <div className="flex items-center gap-2 flex-wrap text-sm">
                                    <span className="font-semibold text-gray-800 dark:text-gray-100">{h.workstation ?? __('Unknown')}</span>
                                    {h.step && <span className="text-xs text-gray-500 dark:text-gray-400">{h.step}</span>}
                                    {h.operator && <span className="text-xs text-gray-500 dark:text-gray-400">{__('by')} {h.operator}</span>}
                                    {h.processed_at && <span className="text-xs text-gray-400 dark:text-gray-500">{h.processed_at}</span>}
                                    {h.result && (
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${RESULT_BADGE[h.result] ?? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'}`}>
                                            {h.result}
                                        </span>
                                    )}
                                </div>
                                {h.parameters && Object.keys(h.parameters).length > 0 && (
                                    <div className="mt-1 ml-1 flex flex-wrap gap-2">
                                        {Object.entries(h.parameters).map(([pk, pv]) => (
                                            <span key={pk} className="text-xs bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded px-2 py-0.5 text-gray-600 dark:text-gray-300">
                                                <span className="text-gray-400 dark:text-gray-500">{pk}:</span> {typeof pv === 'object' ? JSON.stringify(pv) : String(pv)}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
