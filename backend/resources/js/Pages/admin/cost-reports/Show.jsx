import { Head, Link, usePage } from '@inertiajs/react';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatNumber, formatDateTime } from '../../../lib/i18n';
import CostMethodology from './CostMethodology';

const PAY_TYPE_LABELS = {
    hourly: 'Hourly',
    weekly: 'Weekly',
    piece_rate: 'Piece rate',
};

function money(value, currency) {
    if (value == null) return '—';
    return `${formatNumber(value)} ${currency}`;
}

const MATERIAL_COLUMNS = [
    {
        id: 'material',
        accessorFn: (r) => r.material_name,
        header: __('Material'),
        cell: ({ row }) => {
            const it = row.original;
            return (
                <>
                    {it.material_name ?? '—'}
                    {it.material_code && <span className="text-xs text-om-faint font-mono ml-1">{it.material_code}</span>}
                </>
            );
        },
    },
    {
        id: 'source',
        accessorFn: (r) => r.source,
        header: __('Source'),
        cell: ({ row }) => {
            const it = row.original;
            return (
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                    it.source === 'actual' ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-accent'
                }`}>
                    {it.source === 'actual' ? __('Actual consumption') : __('BOM estimate')}
                </span>
            );
        },
    },
    {
        id: 'qty',
        accessorFn: (r) => r.qty,
        header: __('Qty'),
        cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.qty)}</span>,
        meta: { align: 'right' },
    },
    {
        id: 'unit_price',
        accessorFn: (r) => r.unit_price,
        header: __('Unit price'),
        cell: ({ row }) => <span className="font-mono">{money(row.original.unit_price, row.original.currency)}</span>,
        meta: { align: 'right' },
    },
    {
        id: 'line_total',
        accessorFn: (r) => r.line_total,
        header: __('Line total'),
        cell: ({ row }) => <span className="font-mono">{money(row.original.line_total, row.original.currency)}</span>,
        meta: { align: 'right' },
    },
];

const LABOR_COLUMNS = [
    {
        id: 'worker',
        accessorFn: (r) => r.worker_name,
        header: __('Worker'),
        cell: ({ row }) => {
            const it = row.original;
            return (
                <>
                    {it.worker_name ?? '—'}
                    {it.worker_code && <span className="text-xs text-om-faint font-mono ml-1">{it.worker_code}</span>}
                </>
            );
        },
    },
    {
        id: 'pay_type',
        accessorFn: (r) => __(PAY_TYPE_LABELS[r.pay_type] ?? r.pay_type),
        header: __('Pay type'),
        cell: ({ row }) => __(PAY_TYPE_LABELS[row.original.pay_type] ?? row.original.pay_type),
    },
    {
        id: 'basis',
        accessorFn: (r) => r.basis,
        header: __('Basis'),
        cell: ({ row }) => {
            const it = row.original;
            return (
                <span className="font-mono">
                    {formatNumber(it.basis)} {it.basis_unit === 'pcs' ? __('Pieces') : __('Hours')}
                </span>
            );
        },
        meta: { align: 'right' },
    },
    {
        id: 'rate',
        accessorFn: (r) => r.rate,
        header: __('Rate'),
        cell: ({ row }) => <span className="font-mono">{money(row.original.rate, row.original.currency)}</span>,
        meta: { align: 'right' },
    },
    {
        id: 'line_total',
        accessorFn: (r) => r.line_total,
        header: __('Line total'),
        cell: ({ row }) => <span className="font-mono">{money(row.original.line_total, row.original.currency)}</span>,
        meta: { align: 'right' },
    },
];

const ADDITIONAL_COLUMNS = [
    {
        id: 'description',
        accessorFn: (r) => r.description,
        header: __('Description'),
        cell: ({ row }) => row.original.description ?? '—',
    },
    {
        id: 'line_total',
        accessorFn: (r) => r.line_total,
        header: __('Line total'),
        cell: ({ row }) => <span className="font-mono">{money(row.original.line_total, row.original.currency)}</span>,
        meta: { align: 'right' },
    },
];

export default function CostReportShow() {
    const { breakdown, meta = {} } = usePage().props;
    const b = breakdown;
    const currency = b.currency;

    return (
        <>
            <Head title={`${__('Production Cost')} · ${b.order_no}`} />
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <Link href="/admin/cost-reports" className="text-sm text-om-accent hover:underline">
                        ← {__('Production Cost Report')}
                    </Link>
                    <h1 className="text-3xl font-bold text-om-ink mt-1">{b.order_no}</h1>
                    <p className="text-om-muted">
                        {meta.product_name ?? '—'} · {meta.line_name ?? '—'}
                        {meta.completed_at && <> · {formatDateTime(meta.completed_at)}</>}
                    </p>
                </div>

                {b.mixed_currency && (
                    <div className="rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2">
                        {__('Mixed currencies - totals are summed without conversion.')}
                    </div>
                )}

                {/* Headline */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <Card label={__('Total cost')} value={money(b.total_cost, currency)} strong />
                    <Card label={__('Cost per unit')} value={b.cost_per_unit == null ? '—' : money(b.cost_per_unit, currency)} />
                    <Card label={__('Produced')} value={formatNumber(b.produced_qty)} />
                    <Card
                        label={`${__('Materials')} / ${__('Labor')}`}
                        value={`${money(b.materials.total, currency)} / ${money(b.labor.total, currency)}`}
                    />
                </div>

                <CostMethodology />

                {/* Materials */}
                <Section title={__('Materials')} total={money(b.materials.total, currency)}>
                    <DataTable
                        data={b.materials.items}
                        columns={MATERIAL_COLUMNS}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={__('No cost data for this work order.')}
                    />
                </Section>

                {/* Labor */}
                <Section title={__('Labor')} total={money(b.labor.total, currency)}>
                    <DataTable
                        data={b.labor.items}
                        columns={LABOR_COLUMNS}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={__('No cost data for this work order.')}
                    />
                </Section>

                {/* Additional costs */}
                <Section title={__('Additional costs')} total={money(b.additional.total, currency)}>
                    <DataTable
                        data={b.additional.items}
                        columns={ADDITIONAL_COLUMNS}
                        searchable={false}
                        columnToggle={false}
                        paginated={false}
                        emptyLabel={__('No cost data for this work order.')}
                    />
                </Section>

                {/* Grand total */}
                <div className="bg-om-ink text-white rounded-om-sm p-4 flex items-center justify-between">
                    <span className="text-lg font-medium">{__('Total cost')}</span>
                    <span className="text-2xl font-bold font-mono">{money(b.total_cost, currency)}</span>
                </div>
            </div>
        </>
    );
}

function Section({ title, total, children }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm overflow-x-auto">
            <div className="flex items-center justify-between px-4 py-3 border-b border-om-line2">
                <h2 className="font-semibold text-om-ink">{title}</h2>
                <span className="font-mono text-om-muted">{total}</span>
            </div>
            {children}
        </div>
    );
}

function Card({ label, value, strong }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-4">
            <div className="text-xs text-om-muted uppercase">{label}</div>
            <div className={`mt-1 ${strong ? 'text-2xl font-bold' : 'text-lg font-semibold'} text-om-ink`}>
                {value}
            </div>
        </div>
    );
}

CostReportShow.layout = (page) => <AppLayout>{page}</AppLayout>;
