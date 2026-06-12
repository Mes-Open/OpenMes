import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
                                    ? 'bg-om-ink text-white border-om-accent'
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
                                <input
                                    type="date"
                                    value={form.from}
                                    onChange={(e) => setForm((f) => ({ ...f, from: e.target.value }))}
                                    className="form-input py-1.5 text-sm"
                                />
                            </Field>
                            <Field label={__('To')}>
                                <input
                                    type="date"
                                    value={form.to}
                                    onChange={(e) => setForm((f) => ({ ...f, to: e.target.value }))}
                                    className="form-input py-1.5 text-sm"
                                />
                            </Field>
                        </>
                    )}
                    <Field label={__('Line')}>
                        <select
                            value={form.line_id}
                            onChange={(e) => setForm((f) => ({ ...f, line_id: e.target.value }))}
                            className="form-input py-1.5 text-sm"
                        >
                            <option value="">{__('All')}</option>
                            {lines.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label={__('Product Type')}>
                        <select
                            value={form.product_type_id}
                            onChange={(e) => setForm((f) => ({ ...f, product_type_id: e.target.value }))}
                            className="form-input py-1.5 text-sm"
                        >
                            <option value="">{__('All')}</option>
                            {productTypes.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
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
                    <button type="button" onClick={() => apply()} className="btn-touch btn-primary">
                        {__('Apply')}
                    </button>
                    <button type="button" onClick={clear} className="btn-touch btn-secondary">
                        {__('Clear')}
                    </button>
                </div>

                {/* Table */}
                <div className="bg-om-card rounded-om-sm shadow-sm overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-om-muted uppercase border-b border-om-line2">
                                <th className="px-4 py-3">{__('Order')}</th>
                                <th className="px-4 py-3">{__('Product')}</th>
                                <th className="px-4 py-3">{__('Line')}</th>
                                <th className="px-4 py-3 text-right">{__('Produced')}</th>
                                <th className="px-4 py-3 text-right">{__('Material cost')}</th>
                                <th className="px-4 py-3 text-right">{__('Labor cost')}</th>
                                <th className="px-4 py-3 text-right">{__('Additional costs')}</th>
                                <th className="px-4 py-3 text-right">{__('Total cost')}</th>
                                <th className="px-4 py-3 text-right">{__('Cost per unit')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-om-line2">
                            {rows.map((r) => (
                                <tr
                                    key={r.id}
                                    className="hover:bg-om-bg cursor-pointer"
                                    onClick={() => router.visit(`/admin/cost-reports/${r.id}`)}
                                >
                                    <td className="px-4 py-3 font-medium text-om-accent">
                                        <Link href={`/admin/cost-reports/${r.id}`} onClick={(e) => e.stopPropagation()}>
                                            {r.order_no}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-om-muted">{r.product_name ?? '—'}</td>
                                    <td className="px-4 py-3 text-om-muted">{r.line_name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-mono">{formatNumber(r.produced_qty)}</td>
                                    <td className="px-4 py-3 text-right font-mono">{money(r.material_cost, r.currency)}</td>
                                    <td className="px-4 py-3 text-right font-mono">{money(r.labor_cost, r.currency)}</td>
                                    <td className="px-4 py-3 text-right font-mono">{money(r.additional_cost, r.currency)}</td>
                                    <td className="px-4 py-3 text-right font-mono font-semibold text-om-ink">
                                        {money(r.total_cost, r.currency)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono">
                                        {r.cost_per_unit == null ? '—' : money(r.cost_per_unit, r.currency)}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-om-muted">
                                        {__('No orders match the current filters.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

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
                                        ? 'bg-om-ink text-white border-om-accent'
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
