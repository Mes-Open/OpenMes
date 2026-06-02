import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

function PassBadge({ pass }) {
    return pass
        ? <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">PASS</span>
        : <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">FAIL</span>;
}

function StatusBadge({ status }) {
    const styles = {
        DONE: 'bg-green-100 text-green-700',
        IN_PROGRESS: 'bg-blue-100 text-blue-700',
    };
    return (
        <span className={`inline-block px-2 py-0.5 rounded text-xs font-bold ${styles[status] ?? 'bg-gray-100 text-gray-600'}`}>
            {status}
        </span>
    );
}

export default function BatchReport() {
    const { batch, workOrder, bom = [], steps = [], confirmations = [], qualityChecks = [], checklist } = usePage().props;

    const title = batch.lot_number ?? `Batch #${batch.batch_number}`;

    return (
        <>
            <Head title={`Series Report — ${title}`} />
            <div className="max-w-4xl mx-auto space-y-6">
                {/* Toolbar */}
                <div className="flex justify-between items-center flex-wrap gap-3 print:hidden">
                    <button
                        type="button"
                        onClick={() => window.history.back()}
                        className="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-sm font-medium"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="btn-touch btn-secondary text-sm"
                        >
                            Print
                        </button>
                        <a href={`/admin/batches/${batch.id}/report/pdf`} className="btn-touch btn-primary text-sm">
                            Download PDF
                        </a>
                    </div>
                </div>

                {/* General Information */}
                <Section title="General Information">
                    <InfoTable rows={[
                        ['Work Order', workOrder?.order_no],
                        ['Product', workOrder?.product_type?.name ?? '—'],
                        ['Line', workOrder?.line?.name ?? '—'],
                        ['Workstation', batch.workstation?.name ?? '—'],
                        ['LOT Number', <strong key="lot">{batch.lot_number ?? 'Not assigned'}</strong>],
                        ['Planned Quantity', `${Number(batch.target_qty).toFixed(2)} pcs`],
                        ['Produced Quantity', `${Number(batch.produced_qty).toFixed(2)} pcs`],
                        ...(batch.scrap_qty ? [['Scrap', `${Number(batch.scrap_qty).toFixed(2)} pcs`]] : []),
                        ['Started', batch.started_at ?? '—'],
                        ['Completed', batch.completed_at ?? '—'],
                        ...(batch.released_at ? [
                            ['Released', `${batch.released_at} (${batch.release_type === 'for_sale' ? 'For Sale' : 'For Production'})`],
                            ['Released By', batch.released_by?.name ?? '—'],
                        ] : []),
                        ...(batch.expiry_date ? [['Expiry Date', batch.expiry_date]] : []),
                    ]} />
                </Section>

                {/* BOM */}
                {bom.length > 0 && (
                    <Section title="Materials (BOM)">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-slate-700">
                                    <tr>
                                        {['Material', 'Code', 'Type', 'Qty/Unit', 'Total', 'Unit', 'Supplier LOT'].map((h) => (
                                            <th key={h} className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {bom.map((item, i) => (
                                        <tr key={i}>
                                            <td className="px-3 py-2 font-medium">{item.material_name}</td>
                                            <td className="px-3 py-2 font-mono text-gray-600">{item.material_code}</td>
                                            <td className="px-3 py-2 text-gray-600">{item.material_type?.replace(/_/g, ' ')}</td>
                                            <td className="px-3 py-2 text-right font-mono">{item.quantity_per_unit}</td>
                                            <td className="px-3 py-2 text-right font-mono font-bold">
                                                {item.total_qty}
                                                {item.scrap_percentage > 0 && (
                                                    <span className="text-xs text-gray-400 ml-1">(+{item.scrap_percentage}%)</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-gray-600">{item.unit_of_measure}</td>
                                            <td className="px-3 py-2 text-gray-400 font-mono">{item.external_code ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                )}

                {/* Production Steps */}
                <Section title="Production Steps">
                    {steps.length === 0 ? (
                        <p className="text-gray-400 text-sm">No steps recorded.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-slate-700">
                                    <tr>
                                        {['#', 'Step', 'Started', 'Started By', 'Completed', 'Completed By', 'Duration', 'Status'].map((h) => (
                                            <th key={h} className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {steps.map((step, i) => (
                                        <tr key={step.id ?? i}>
                                            <td className="px-3 py-2 font-mono text-gray-500">{step.step_number}</td>
                                            <td className="px-3 py-2 font-medium">{step.name}</td>
                                            <td className="px-3 py-2 text-gray-500 font-mono text-xs">{step.started_at ?? '—'}</td>
                                            <td className="px-3 py-2 text-gray-600">{step.started_by?.name ?? '—'}</td>
                                            <td className="px-3 py-2 text-gray-500 font-mono text-xs">{step.completed_at ?? '—'}</td>
                                            <td className="px-3 py-2 text-gray-600">{step.completed_by?.name ?? '—'}</td>
                                            <td className="px-3 py-2 text-right font-mono">{step.duration_minutes ? `${step.duration_minutes} min` : '—'}</td>
                                            <td className="px-3 py-2"><StatusBadge status={step.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Section>

                {/* Process Confirmations */}
                {confirmations.length > 0 && (
                    <Section title="Process Confirmations">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-slate-700">
                                    <tr>
                                        {['Date & Time', 'Type', 'Value', 'Confirmed By', 'Notes'].map((h) => (
                                            <th key={h} className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {confirmations.map((c, i) => (
                                        <tr key={c.id ?? i}>
                                            <td className="px-3 py-2 font-mono text-xs">{c.confirmed_at}</td>
                                            <td className="px-3 py-2 capitalize">{c.confirmation_type}</td>
                                            <td className="px-3 py-2 font-mono">{c.value ?? '—'}</td>
                                            <td className="px-3 py-2">{c.confirmed_by?.name ?? '—'}</td>
                                            <td className="px-3 py-2 text-gray-500">{c.notes ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                )}

                {/* Quality Checks */}
                {qualityChecks.length > 0 && (
                    <Section title={`Quality Checks (${qualityChecks.length})`}>
                        <div className="space-y-4">
                            {qualityChecks.map((qc, qi) => (
                                <div key={qc.id ?? qi} className="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <div className="bg-gray-50 dark:bg-slate-700 px-4 py-2 flex flex-wrap gap-3 items-center text-sm">
                                        <span className="font-bold">Check #{qi + 1}</span>
                                        <span className="text-gray-500 font-mono text-xs">{qc.checked_at}</span>
                                        <span className="text-gray-600">By: {qc.checked_by?.name ?? '—'}</span>
                                        {qc.production_quantity != null && (
                                            <span className="text-gray-600">Production: {Number(qc.production_quantity).toFixed(0)} pcs</span>
                                        )}
                                        <PassBadge pass={qc.all_passed} />
                                    </div>
                                    <table className="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead className="bg-gray-50 dark:bg-slate-700">
                                            <tr>
                                                {['Sample #', 'Parameter', 'Value', 'Result'].map((h) => (
                                                    <th key={h} className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {(qc.samples ?? []).map((s, si) => (
                                                <tr key={s.id ?? si}>
                                                    <td className="px-3 py-2 font-mono">{s.sample_number}</td>
                                                    <td className="px-3 py-2">{s.parameter_name}</td>
                                                    <td className="px-3 py-2 font-mono">
                                                        {s.parameter_type === 'measurement' ? s.value_numeric : (s.value_boolean ? 'Yes' : 'No')}
                                                    </td>
                                                    <td className="px-3 py-2"><PassBadge pass={s.is_passed} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ))}
                        </div>
                    </Section>
                )}

                {/* Packaging Checklist */}
                {checklist && (
                    <Section title="Packaging Checklist">
                        <InfoTable rows={[
                            ['UDI code readable', <PassBadge key="udi" pass={checklist.udi_readable} />],
                            ['Packaging in good condition', <PassBadge key="pkg" pass={checklist.packaging_condition} />],
                            ['Labels readable', <PassBadge key="lbl" pass={checklist.labels_readable} />],
                            ['Label matches product', <PassBadge key="match" pass={checklist.label_matches_product} />],
                            ['Overall', checklist.all_passed
                                ? <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">ALL PASS</span>
                                : <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">FAILED</span>
                            ],
                        ]} />
                        <p className="text-xs text-gray-400 mt-2">
                            Checked by: {checklist.checked_by?.name ?? '—'} | {checklist.checked_at ?? '—'}
                        </p>
                    </Section>
                )}
            </div>
        </>
    );
}

BatchReport.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ---- helpers ---- */

function Section({ title, children }) {
    return (
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm overflow-hidden">
            <div className="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                <h2 className="text-base font-bold text-gray-800 dark:text-gray-100">{title}</h2>
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function InfoTable({ rows }) {
    return (
        <table className="w-full text-sm">
            <tbody>
                {rows.map(([label, value], i) => (
                    <tr key={i} className="border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <td className="py-2 pr-4 text-gray-500 font-medium w-2/5">{label}</td>
                        <td className="py-2 text-gray-800 dark:text-gray-200">{value}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
