import { Head, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';

const bomColumns = [
    { id: 'material_name', accessorKey: 'material_name', header: 'Material', cell: ({ row }) => <span className="font-medium">{row.original.material_name}</span> },
    { id: 'material_code', accessorKey: 'material_code', header: 'Code', cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.material_code}</span> },
    { id: 'material_type', accessorKey: 'material_type', header: 'Type', cell: ({ row }) => <span className="text-om-muted">{row.original.material_type?.replace(/_/g, ' ')}</span> },
    { id: 'quantity_per_unit', accessorKey: 'quantity_per_unit', header: 'Qty/Unit', meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{row.original.quantity_per_unit}</span> },
    {
        id: 'total_qty', accessorKey: 'total_qty', header: 'Total', meta: { align: 'right' },
        cell: ({ row }) => (
            <span className="font-mono font-bold">
                {row.original.total_qty}
                {row.original.scrap_percentage > 0 && (
                    <span className="text-xs text-om-faint ml-1">(+{row.original.scrap_percentage}%)</span>
                )}
            </span>
        ),
    },
    { id: 'unit_of_measure', accessorKey: 'unit_of_measure', header: 'Unit', cell: ({ row }) => <span className="text-om-muted">{row.original.unit_of_measure}</span> },
    { id: 'external_code', accessorKey: 'external_code', header: 'Supplier LOT', cell: ({ row }) => <span className="text-om-faint font-mono">{row.original.external_code ?? '—'}</span> },
];

const stepColumns = [
    { id: 'step_number', accessorKey: 'step_number', header: '#', cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.step_number}</span> },
    { id: 'name', accessorKey: 'name', header: 'Step', cell: ({ row }) => <span className="font-medium">{row.original.name}</span> },
    { id: 'started_at', accessorKey: 'started_at', header: 'Started', cell: ({ row }) => <span className="text-om-muted font-mono text-xs">{row.original.started_at ?? '—'}</span> },
    { id: 'started_by', accessorFn: (r) => r.started_by?.name ?? '—', header: 'Started By', cell: ({ row }) => <span className="text-om-muted">{row.original.started_by?.name ?? '—'}</span> },
    { id: 'completed_at', accessorKey: 'completed_at', header: 'Completed', cell: ({ row }) => <span className="text-om-muted font-mono text-xs">{row.original.completed_at ?? '—'}</span> },
    { id: 'completed_by', accessorFn: (r) => r.completed_by?.name ?? '—', header: 'Completed By', cell: ({ row }) => <span className="text-om-muted">{row.original.completed_by?.name ?? '—'}</span> },
    { id: 'duration_minutes', accessorKey: 'duration_minutes', header: 'Duration', meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{row.original.duration_minutes ? `${row.original.duration_minutes} min` : '—'}</span> },
    { id: 'status', accessorKey: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
];

const confirmationColumns = [
    { id: 'confirmed_at', accessorKey: 'confirmed_at', header: 'Date & Time', cell: ({ row }) => <span className="font-mono text-xs">{row.original.confirmed_at}</span> },
    { id: 'confirmation_type', accessorKey: 'confirmation_type', header: 'Type', cell: ({ row }) => <span className="capitalize">{row.original.confirmation_type}</span> },
    { id: 'value', accessorKey: 'value', header: 'Value', cell: ({ row }) => <span className="font-mono">{row.original.value ?? '—'}</span> },
    { id: 'confirmed_by', accessorFn: (r) => r.confirmed_by?.name ?? '—', header: 'Confirmed By', cell: ({ row }) => row.original.confirmed_by?.name ?? '—' },
    { id: 'notes', accessorKey: 'notes', header: 'Notes', cell: ({ row }) => <span className="text-om-muted">{row.original.notes ?? '—'}</span> },
];

const sampleColumns = [
    { id: 'sample_number', accessorKey: 'sample_number', header: 'Sample #', cell: ({ row }) => <span className="font-mono">{row.original.sample_number}</span> },
    { id: 'parameter_name', accessorKey: 'parameter_name', header: 'Parameter', cell: ({ row }) => row.original.parameter_name },
    {
        id: 'value', header: 'Value',
        accessorFn: (s) => (s.parameter_type === 'measurement' ? s.value_numeric : (s.value_boolean ? 'Yes' : 'No')),
        cell: ({ row }) => (
            <span className="font-mono">
                {row.original.parameter_type === 'measurement' ? row.original.value_numeric : (row.original.value_boolean ? 'Yes' : 'No')}
            </span>
        ),
    },
    { id: 'result', accessorKey: 'is_passed', header: 'Result', cell: ({ row }) => <PassBadge pass={row.original.is_passed} /> },
];

function PassBadge({ pass }) {
    return pass
        ? <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running">PASS</span>
        : <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked">FAIL</span>;
}

function StatusBadge({ status }) {
    const styles = {
        DONE: 'bg-om-running-bg text-om-running',
        IN_PROGRESS: 'bg-om-chip text-om-accent',
    };
    return (
        <span className={`inline-block px-2 py-0.5 rounded text-xs font-bold ${styles[status] ?? 'bg-om-chip text-om-muted'}`}>
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
                        className="inline-flex items-center gap-2 text-om-accent hover:text-om-accent text-sm font-medium"
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
                        <DataTable
                            data={bom}
                            columns={bomColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </Section>
                )}

                {/* Production Steps */}
                <Section title="Production Steps">
                    <DataTable
                        data={steps}
                        columns={stepColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel="No steps recorded."
                    />
                </Section>

                {/* Process Confirmations */}
                {confirmations.length > 0 && (
                    <Section title="Process Confirmations">
                        <DataTable
                            data={confirmations}
                            columns={confirmationColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </Section>
                )}

                {/* Quality Checks */}
                {qualityChecks.length > 0 && (
                    <Section title={`Quality Checks (${qualityChecks.length})`}>
                        <div className="space-y-4">
                            {qualityChecks.map((qc, qi) => (
                                <div key={qc.id ?? qi} className="border border-om-line2 rounded-om-sm overflow-hidden">
                                    <div className="bg-om-panel px-4 py-2 flex flex-wrap gap-3 items-center text-sm">
                                        <span className="font-bold">Check #{qi + 1}</span>
                                        <span className="text-om-muted font-mono text-xs">{qc.checked_at}</span>
                                        <span className="text-om-muted">By: {qc.checked_by?.name ?? '—'}</span>
                                        {qc.production_quantity != null && (
                                            <span className="text-om-muted">Production: {Number(qc.production_quantity).toFixed(0)} pcs</span>
                                        )}
                                        <PassBadge pass={qc.all_passed} />
                                    </div>
                                    <DataTable
                                        data={qc.samples ?? []}
                                        columns={sampleColumns}
                                        searchable={false}
                                        columnToggle={false}
                                        paginated={false}
                                    />
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
                                ? <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running">ALL PASS</span>
                                : <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked">FAILED</span>
                            ],
                        ]} />
                        <p className="text-xs text-om-faint mt-2">
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
        <div className="bg-om-card rounded-om-sm shadow-sm overflow-hidden">
            <div className="px-5 py-3 border-b border-om-line2">
                <h2 className="text-base font-bold text-om-ink">{title}</h2>
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
                    <tr key={i} className="border-b border-om-line2 last:border-0">
                        <td className="py-2 pr-4 text-om-muted font-medium w-2/5">{label}</td>
                        <td className="py-2 text-om-ink">{value}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
