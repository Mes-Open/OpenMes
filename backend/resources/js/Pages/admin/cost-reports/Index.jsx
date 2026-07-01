import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatNumber } from '../../../lib/i18n';
import CostMethodology from './CostMethodology';

const PRESET_LABELS = {
    today: 'Today',
    yesterday: 'Yesterday',
    last7: 'Last 7 days',
    last30: 'Last 30 days',
    this_month: 'This month',
    last_month: 'Last month',
    custom: 'Custom',
    all: 'All time',
};

function money(value, currency) {
    if (value == null) return '—';
    return `${formatNumber(value)} ${currency}`;
}

export default function CostReportsIndex() {
    const { orders, summary = {}, filters = {}, lines = [], productTypes = [], presets = [], currency = 'PLN' } =
        usePage().props;

    const [form, setForm] = useState({
        line_id: filters.line_id ?? '',
        product_type_id: filters.product_type_id ?? '',
        preset: filters.preset ?? 'last30',
        from: filters.from ?? '',
        to: filters.to ?? '',
        search: filters.search ?? '',
    });

    const apply = (overrides = {}) => {
        const params = { ...form, ...overrides };
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] == null) delete params[k];
        });
        router.get('/admin/cost-reports', params, { preserveState: false, preserveScroll: true });
    };

    const setPreset = (preset) => {
        setForm((f) => ({ ...f, preset }));
        apply({ preset });
    };

    const clear = () => router.get('/admin/cost-reports', {}, { preserveState: false });

    const exportUrl = () => {
        const p = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => {
            if (v) p.set(k, v);
        });
        const qs = p.toString();
        return `/admin/cost-reports/export${qs ? '?' + qs : ''}`;
    };

    const goPage = (page) => apply({ page });

    const rows = orders?.data ?? [];
    const links = orders?.links ?? [];
    const lastPage = orders?.last_page ?? 1;

    const columns = useMemo(
        () => [
            {
                id: 'order',
                accessorKey: 'order_no',
                header: __('Order'),
                cell: ({ row }) => (
                    <Link
                        href={`/admin/cost-reports/${row.original.id}`}
                        onClick={(e) => e.stopPropagation()}
                        className="font-medium text-om-accent"
                    >
                        {row.original.order_no}
                    </Link>
                ),
            },
            {
                id: 'product',
                accessorKey: 'product_name',
                header: __('Product'),
                cell: ({ row }) => <span className="text-om-muted">{row.original.product_name ?? '—'}</span>,
            },
            {
                id: 'line',
                accessorKey: 'line_name',
                header: __('Line'),
                cell: ({ row }) => <span className="text-om-muted">{row.original.line_name ?? '—'}</span>,
            },
            {
                id: 'produced',
                accessorKey: 'produced_qty',
                header: __('Produced'),
                meta: { align: 'right' },
                cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.produced_qty)}</span>,
            },
            {
                id: 'material_cost',
                accessorKey: 'material_cost',
                header: __('Material cost'),
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="font-mono">{money(row.original.material_cost, row.original.currency)}</span>
                ),
            },
            {
                id: 'labor_cost',
                accessorKey: 'labor_cost',
                header: __('Labor cost'),
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="font-mono">{money(row.original.labor_cost, row.original.currency)}</span>
                ),
            },
            {
                id: 'additional_cost',
                accessorKey: 'additional_cost',
                header: __('Additional costs'),
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="font-mono">{money(row.original.additional_cost, row.original.currency)}</span>
                ),
            },
            {
                id: 'total_cost',
                accessorKey: 'total_cost',
                header: __('Total cost'),
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="font-mono font-semibold text-om-ink">
                        {money(row.original.total_cost, row.original.currency)}
                    </span>
                ),
            },
            {
                id: 'cost_per_unit',
                accessorKey: 'cost_per_unit',
                header: __('Cost per unit'),
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="font-mono">
                        {row.original.cost_per_unit == null
                            ? '—'
                            : money(row.original.cost_per_unit, row.original.currency)}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title={__('Production Cost Report')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{__('Production Cost Report')}</h1>
                        <p className="text-om-muted mt-1">
                            {__('Material, labor and additional cost per finished work order.')}
                        </p>
                    </div>
                    <a href={exportUrl()} className="btn-touch btn-secondary whitespace-nowrap">
                        {__('Export CSV')}
                    </a>
                </div>

                {summary.mixed_currency && (
                    <div className="rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2">
                        {__('Mixed currencies - totals are summed without conversion.')}
                    </div>
                )}

                {summary.limited && (
                    <div className="rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2">
                        {__('Large result set: summary totals cover the first 10000 orders. Narrow the filters for an exact total.')}
                    </div>
                )}

                {/* Summary cards */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <SummaryCard label={__('Total cost')} value={money(summary.total_cost ?? 0, currency)} />
                    <SummaryCard label={__('Material cost')} value={money(summary.material_cost ?? 0, currency)} />
                    <SummaryCard label={__('Labor cost')} value={money(summary.labor_cost ?? 0, currency)} />
                    <SummaryCard label={__('Additional costs')} value={money(summary.additional_cost ?? 0, currency)} />
                    <SummaryCard
                        label={__('Avg cost per unit')}
                        value={summary.avg_cost_per_unit == null ? '—' : money(summary.avg_cost_per_unit, currency)}
                    />
                </div>

                <CostMethodology />

                {/* Date presets */}
                <div className="flex flex-wrap gap-1.5">
                    {presets.map((p) => (
                        <button
                            key={p}
                            type="button"
                            onClick={() => setPreset(p)}
                            className={`px-3 py-1.5 rounded-om-sm text-sm font-medium border ${
                                form.preset === p
                                    ? 'bg-om-ink text-om-on-ink border-om-accent'
                                    : 'bg-om-card text-om-muted border-om-line2 hover:bg-om-bg'
                            }`}
                        >
                            {__(PRESET_LABELS[p] ?? p)}
                        </button>
                    ))}
                </div>

                {/* Filters */}
                <div className="bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-3">
                    {form.preset === 'custom' && (
                        <>
                            <Field label={__('From')}>
                                <DatePicker
                                    value={form.from || null}
                                    onChange={(iso) => setForm((f) => ({ ...f, from: iso ?? '' }))}
                                    className="w-44"
                                />
                            </Field>
                            <Field label={__('To')}>
                                <DatePicker
                                    value={form.to || null}
                                    onChange={(iso) => setForm((f) => ({ ...f, to: iso ?? '' }))}
                                    className="w-44"
                                />
                            </Field>
                        </>
                    )}
                    <Field label={__('Line')}>
                        <Dropdown
                            value={form.line_id == null ? '' : String(form.line_id)}
                            onChange={(v) => setForm((f) => ({ ...f, line_id: v }))}
                            options={[
                                { value: '', label: __('All') },
                                ...lines.map((l) => ({ value: String(l.id), label: l.name })),
                            ]}
                            className="w-full"
                        />
                    </Field>
                    <Field label={__('Product Type')}>
                        <Dropdown
                            value={form.product_type_id == null ? '' : String(form.product_type_id)}
                            onChange={(v) => setForm((f) => ({ ...f, product_type_id: v }))}
                            options={[
                                { value: '', label: __('All') },
                                ...productTypes.map((p) => ({ value: String(p.id), label: p.name })),
                            ]}
                            className="w-full"
                        />
                    </Field>
                    <Field label={__('Search')}>
                        <input
                            type="text"
                            value={form.search}
                            onChange={(e) => setForm((f) => ({ ...f, search: e.target.value }))}
                            onKeyDown={(e) => e.key === 'Enter' && apply()}
                            placeholder={__('Order no. or LOT')}
                            className="form-input py-1.5 text-sm"
                        />
                    </Field>
                    <button type="button" onClick={() => apply()} className="btn-touch btn-accent">
                        {__('Apply')}
                    </button>
                    <button type="button" onClick={clear} className="btn-touch btn-secondary">
                        {__('Clear')}
                    </button>
                </div>

                {/* Table */}
                <DataTable
                    data={rows}
                    columns={columns}
                    searchable={false}
                    paginated={false}
                    emptyLabel={__('No orders match the current filters.')}
                    onRowClick={(r) => router.visit(`/admin/cost-reports/${r.id}`)}
                />

                {/* Pagination */}
                {lastPage > 1 && (
                    <div className="flex items-center gap-1 flex-wrap justify-center">
                        {links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url}
                                onClick={() => link.url && goPage(new URL(link.url).searchParams.get('page'))}
                                className={`px-3 py-1 text-sm rounded border ${
                                    link.active
                                        ? 'bg-om-ink text-om-on-ink border-om-accent'
                                        : link.url
                                        ? 'border-om-line text-om-muted hover:bg-om-bg'
                                        : 'border-om-line2 text-om-faint cursor-default'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="bg-om-card rounded-om-sm shadow-sm p-4">
            <div className="text-xs text-om-muted uppercase">{label}</div>
            <div className="text-2xl font-bold text-om-ink mt-1">{value}</div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-om-muted mb-1">{label}</label>
            {children}
        </div>
    );
}

CostReportsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
