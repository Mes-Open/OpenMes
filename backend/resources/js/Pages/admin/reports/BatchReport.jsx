import { Head, Link, usePage } from '@inertiajs/react';
import AppDataTable from '../../../components/AppDataTable';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDate, formatDateTime, formatNumber } from '../../../lib/i18n';

const bomColumns = [
    { id: 'material_name', accessorKey: 'material_name', header: () => __('Material'), cell: ({ row }) => <span className="font-medium">{row.original.material_name}</span> },
    { id: 'material_code', accessorKey: 'material_code', header: () => __('Code'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.material_code}</span> },
    { id: 'material_type', accessorKey: 'material_type', header: () => __('Type'), cell: ({ row }) => <span className="text-om-muted">{row.original.material_type?.replace(/_/g, ' ')}</span> },
    { id: 'quantity_per_unit', accessorKey: 'quantity_per_unit', header: () => __('Qty/Unit'), meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{row.original.quantity_per_unit}</span> },
    {
        id: 'total_qty', accessorKey: 'total_qty', header: () => __('Total'), meta: { align: 'right' },
        cell: ({ row }) => (
            <span className="font-mono font-bold">
                {row.original.total_qty}
                {row.original.scrap_percentage > 0 && (
                    <span className="text-xs text-om-faint ml-1">(+{row.original.scrap_percentage}%)</span>
                )}
            </span>
        ),
    },
    { id: 'unit_of_measure', accessorKey: 'unit_of_measure', header: () => __('Unit'), cell: ({ row }) => <span className="text-om-muted">{row.original.unit_of_measure}</span> },
    { id: 'external_code', accessorKey: 'external_code', header: () => __('Supplier LOT'), cell: ({ row }) => <span className="text-om-faint font-mono">{row.original.external_code ?? '—'}</span> },
];

const stepColumns = [
    { id: 'step_number', accessorKey: 'step_number', header: '#', cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.step_number}</span> },
    { id: 'name', accessorKey: 'name', header: () => __('Step'), cell: ({ row }) => <span className="font-medium">{row.original.name}</span> },
    { id: 'started_at', accessorKey: 'started_at', header: () => __('Started'), cell: ({ row }) => <span className="text-om-muted font-mono text-xs">{row.original.started_at ?? '—'}</span> },
    { id: 'started_by', accessorFn: (r) => r.started_by?.name ?? '—', header: () => __('Started By'), cell: ({ row }) => <span className="text-om-muted">{row.original.started_by?.name ?? '—'}</span> },
    { id: 'completed_at', accessorKey: 'completed_at', header: () => __('Completed'), cell: ({ row }) => <span className="text-om-muted font-mono text-xs">{row.original.completed_at ?? '—'}</span> },
    { id: 'completed_by', accessorFn: (r) => r.completed_by?.name ?? '—', header: () => __('Completed By'), cell: ({ row }) => <span className="text-om-muted">{row.original.completed_by?.name ?? '—'}</span> },
    { id: 'duration_minutes', accessorKey: 'duration_minutes', header: () => __('Duration'), meta: { align: 'right' }, cell: ({ row }) => <span className="font-mono">{row.original.duration_minutes ? `${row.original.duration_minutes} ${__('min')}` : '—'}</span> },
    { id: 'status', accessorKey: 'status', header: () => __('Status'), cell: ({ row }) => <StatusBadge status={row.original.status} /> },
];

const confirmationColumns = [
    { id: 'confirmed_at', accessorKey: 'confirmed_at', header: () => __('Date & Time'), cell: ({ row }) => <span className="font-mono text-xs">{row.original.confirmed_at}</span> },
    { id: 'confirmation_type', accessorKey: 'confirmation_type', header: () => __('Type'), cell: ({ row }) => <span className="capitalize">{row.original.confirmation_type}</span> },
    { id: 'value', accessorKey: 'value', header: () => __('Value'), cell: ({ row }) => <span className="font-mono">{row.original.value ?? '—'}</span> },
    { id: 'confirmed_by', accessorFn: (r) => r.confirmed_by?.name ?? '—', header: () => __('Confirmed By'), cell: ({ row }) => row.original.confirmed_by?.name ?? '—' },
    { id: 'notes', accessorKey: 'notes', header: () => __('Notes'), cell: ({ row }) => <span className="text-om-muted">{row.original.notes ?? '—'}</span> },
];

const sampleColumns = [
    { id: 'sample_number', accessorKey: 'sample_number', header: () => __('Sample #'), cell: ({ row }) => <span className="font-mono">{row.original.sample_number}</span> },
    { id: 'parameter_name', accessorKey: 'parameter_name', header: () => __('Parameter'), cell: ({ row }) => row.original.parameter_name },
    {
        id: 'value', header: () => __('Value'),
        accessorFn: (s) => (s.parameter_type === 'measurement' ? s.value_numeric : (s.value_boolean ? __('Yes') : __('No'))),
        cell: ({ row }) => (
            <span className="font-mono">
                {row.original.parameter_type === 'measurement' ? row.original.value_numeric : (row.original.value_boolean ? __('Yes') : __('No'))}
            </span>
        ),
    },
    { id: 'result', accessorKey: 'is_passed', header: () => __('Result'), cell: ({ row }) => <PassBadge pass={row.original.is_passed} /> },
];

function PassBadge({ pass }) {
    return pass
        ? <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running">{__('PASS')}</span>
        : <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked">{__('FAIL')}</span>;
}

function StatusBadge({ status }) {
    const styles = {
        DONE: 'bg-om-running-bg text-om-running',
        IN_PROGRESS: 'bg-om-chip text-om-accent',
    };
    return (
        <span className={`inline-block px-2 py-0.5 rounded text-xs font-bold ${styles[status] ?? 'bg-om-chip text-om-muted'}`}>
            {({ DONE: __('Done'), IN_PROGRESS: __('In progress') })[status] ?? status}
        </span>
    );
}

export default function BatchReport() {
    const { batch, workOrder, bom = [], steps = [], confirmations = [], qualityChecks = [], checklist } = usePage().props;

    const title = batch.lot_number ?? `${__('Batch #')}${batch.batch_number}`;

    return (
        <>
            <Head title={`${__('Batch Report')} — ${title}`} />
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
                        {__('Back')}
                    </button>
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="btn-touch btn-secondary text-sm"
                        >
                            {__('Print')}
                        </button>
                        <a href={`/admin/batches/${batch.id}/report/pdf`} className="btn-touch btn-secondary text-sm">
                            {__('Download PDF')}
                        </a>
                        <Link href={`/admin/coa/${batch.id}`} className="btn-touch btn-primary text-sm">
                            COA 与放行
                        </Link>
                        <a href={`/admin/batches/${batch.id}/coa/pdf`} className="btn-touch btn-secondary text-sm">
                            {__('Download COA')}
                        </a>
                    </div>
                </div>

                {/* General Information */}
                <Section title={__('General Information')}>
                    <InfoTable rows={[
                        [__('Work Order'), workOrder?.order_no],
                        [__('Product'), workOrder?.product_type?.name ?? '—'],
                        [__('Line'), workOrder?.line?.name ?? '—'],
                        [__('Workstation'), batch.workstation?.name ?? '—'],
                        [__('Lot Number'), <strong key="lot">{batch.lot_number ?? __('Not assigned')}</strong>],
                        [__('Planned Quantity'), `${formatNumber(Number(batch.target_qty), { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${__('pcs')}`],
                        [__('Produced Quantity'), `${formatNumber(Number(batch.produced_qty), { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${__('pcs')}`],
                        ...(batch.scrap_qty ? [[__('Scrap'), `${formatNumber(Number(batch.scrap_qty), { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${__('pcs')}`]] : []),
                        [__('Started'), batch.started_at ? formatDateTime(batch.started_at) : '—'],
                        [__('Completed'), batch.completed_at ? formatDateTime(batch.completed_at) : '—'],
                        ...(batch.released_at ? [
                            [__('Released'), `${formatDateTime(batch.released_at)} (${batch.release_type === 'for_sale' ? __('For Sale') : __('For Production')})`],
                            [__('Released By'), batch.released_by?.name ?? '—'],
                        ] : []),
                        ...(batch.expiry_date ? [[__('Expiry Date'), formatDate(batch.expiry_date)]] : []),
                    ]} />
                </Section>

                {/* BOM */}
                {bom.length > 0 && (
                    <Section title={__('Materials (BOM)')}>
                        <AppDataTable
                            data={bom}
                            columns={bomColumns}
                            searchable={false}
                            columnToggle={false}
                            paginated={false}
                        />
                    </Section>
                )}

                {/* Production Steps */}
                <Section title={__('Production Steps')}>
                    <AppDataTable
                        data={steps}
                        columns={stepColumns}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={__('No steps recorded.')}
                    />
                </Section>

                {/* Process Confirmations */}
                {confirmations.length > 0 && (
                    <Section title={__('Process Confirmations')}>
                        <AppDataTable
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
                    <Section title={`${__('Quality Checks')} (${qualityChecks.length})`}>
                        <div className="space-y-4">
                            {qualityChecks.map((qc, qi) => (
                                <div key={qc.id ?? qi} className="border border-om-line2 rounded-om-sm overflow-hidden">
                                    <div className="bg-om-panel px-4 py-2 flex flex-wrap gap-3 items-center text-sm">
                                        <span className="font-bold">{__('Check')} #{qi + 1}</span>
                                        <span className="text-om-muted font-mono text-xs">{qc.checked_at ? formatDateTime(qc.checked_at) : '—'}</span>
                                        <span className="text-om-muted">{__('By')}: {qc.checked_by?.name ?? '—'}</span>
                                        {qc.production_quantity != null && (
                                            <span className="text-om-muted">{__('Production')}: {formatNumber(Number(qc.production_quantity))} {__('pcs')}</span>
                                        )}
                                        <PassBadge pass={qc.all_passed} />
                                    </div>
                                    <AppDataTable
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
                    <Section title={__('Packaging Checklist')}>
                        <InfoTable rows={[
                            [__('UDI code readable'), <PassBadge key="udi" pass={checklist.udi_readable} />],
                            [__('Packaging in good condition'), <PassBadge key="pkg" pass={checklist.packaging_condition} />],
                            [__('Labels readable'), <PassBadge key="lbl" pass={checklist.labels_readable} />],
                            [__('Label matches product'), <PassBadge key="match" pass={checklist.label_matches_product} />],
                            [__('Overall'), checklist.all_passed
                                ? <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running">{__('ALL PASS')}</span>
                                : <span key="overall" className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked">{__('FAILED')}</span>
                            ],
                        ]} />
                        <p className="text-xs text-om-faint mt-2">
                            {__('Checked by')}: {checklist.checked_by?.name ?? '—'} | {checklist.checked_at ? formatDateTime(checklist.checked_at) : '—'}
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
