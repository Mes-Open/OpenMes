import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDateTime, formatNumber } from '../../../lib/i18n';

const STATUS_BADGE = {
    DONE: 'bg-green-100 text-green-800',
    CANCELLED: 'bg-gray-100 text-gray-600',
    REJECTED: 'bg-red-100 text-red-700',
};

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

function fmtDuration(min) {
    if (min == null) return '—';
    const total = Math.round(min);
    const h = Math.floor(total / 60);
    const m = total % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

export default function ReportsIndex() {
    const { orders, summary = {}, filters = {}, lines = [], productTypes = [], statusOptions = [], presets = [] } =
        usePage().props;

    const [form, setForm] = useState({
        status: filters.status ?? '',
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
        router.get('/admin/reports', params, { preserveState: false, preserveScroll: true });
    };

    const setPreset = (preset) => {
        setForm((f) => ({ ...f, preset }));
        apply({ preset });
    };

    const clear = () => router.get('/admin/reports', {}, { preserveState: false });

    const exportUrl = () => {
        const p = new URLSearchParams();
        Object.entries(form).forEach(([k, v]) => {
            if (v) p.set(k, v);
        });
        const qs = p.toString();
        return `/admin/reports/export${qs ? '?' + qs : ''}`;
    };

    const goPage = (page) => apply({ page });

    const rows = orders?.data ?? [];
    const links = orders?.links ?? [];
    const lastPage = orders?.last_page ?? 1;

    return (
        <>
            <Head title={__('Work Order History')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('Work Order History')}</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-1">
                            {__('Completed, cancelled and rejected orders — full execution record.')}
                        </p>
                    </div>
                    <a href={exportUrl()} className="btn-touch btn-secondary whitespace-nowrap">
                        {__('Export CSV')}
                    </a>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <SummaryCard label={__('Orders')} value={formatNumber(summary.orders ?? 0)} />
                    <SummaryCard label={__('Produced')} value={formatNumber(summary.produced ?? 0)} />
                    <SummaryCard label={__('Planned')} value={formatNumber(summary.planned ?? 0)} />
                    <SummaryCard label={__('Avg execution')} value={fmtDuration(summary.avg_execution_minutes)} />
                    <SummaryCard
                        label={__('On-time')}
                        value={summary.on_time_pct == null ? '—' : `${summary.on_time_pct}%`}
                    />
                </div>

                {/* Date presets */}
                <div className="flex flex-wrap gap-1.5">
                    {presets.map((p) => (
                        <button
                            key={p}
                            type="button"
                            onClick={() => setPreset(p)}
                            className={`px-3 py-1.5 rounded-lg text-sm font-medium border ${
                                form.preset === p
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-slate-600 hover:bg-gray-50'
                            }`}
                        >
                            {__(PRESET_LABELS[p] ?? p)}
                        </button>
                    ))}
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-3">
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
                    <Field label={__('Status')}>
                        <select
                            value={form.status}
                            onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
                            className="form-input py-1.5 text-sm"
                        >
                            <option value="">{__('All')}</option>
                            {statusOptions.map((s) => (
                                <option key={s} value={s}>
                                    {__(s)}
                                </option>
                            ))}
                        </select>
                    </Field>
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
                    <button type="button" onClick={clear} className="text-gray-500 hover:text-gray-800 text-sm">
                        {__('Clear')}
                    </button>
                </div>

                {/* Table */}
                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-gray-500 uppercase border-b border-gray-200 dark:border-slate-700">
                                <th className="px-4 py-3">{__('Order')}</th>
                                <th className="px-4 py-3">{__('Product')}</th>
                                <th className="px-4 py-3">{__('Line')}</th>
                                <th className="px-4 py-3">{__('Status')}</th>
                                <th className="px-4 py-3">{__('Completed')}</th>
                                <th className="px-4 py-3 text-right">
                                    {__('Produced')} / {__('Planned')}
                                </th>
                                <th className="px-4 py-3">{__('Execution')}</th>
                                <th className="px-4 py-3">{__('LOTs')}</th>
                                <th className="px-4 py-3 text-right">{__('Issues')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-700">
                            {rows.map((r) => (
                                <tr
                                    key={r.id}
                                    className="hover:bg-gray-50 dark:hover:bg-slate-700/40 cursor-pointer"
                                    onClick={() => router.visit(`/admin/reports/${r.id}`)}
                                >
                                    <td className="px-4 py-3 font-medium text-blue-600">
                                        <Link href={`/admin/reports/${r.id}`} onClick={(e) => e.stopPropagation()}>
                                            {r.order_no}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {r.product_name ?? '—'}
                                        {r.product_code && (
                                            <span className="text-xs text-gray-400 font-mono ml-1">{r.product_code}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{r.line_name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                                STATUS_BADGE[r.status] ?? 'bg-gray-100 text-gray-600'
                                            }`}
                                        >
                                            {__(r.status)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        {r.completed_at ? formatDateTime(r.completed_at) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono">
                                        {formatNumber(r.produced_qty)} / {formatNumber(r.planned_qty)}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        {fmtDuration(r.execution_minutes)}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        {r.lots.length ? r.lots.slice(0, 2).join(', ') : '—'}
                                        {r.lots.length > 2 && <span className="text-gray-400"> +{r.lots.length - 2}</span>}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {r.issues_count > 0 ? (
                                            <span className="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">
                                                {r.issues_count}
                                            </span>
                                        ) : (
                                            <span className="text-gray-300">0</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-gray-500">
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
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : link.url
                                        ? 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:border-slate-600'
                                        : 'border-gray-200 text-gray-400 cursor-default'
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
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4">
            <div className="text-xs text-gray-500 uppercase">{label}</div>
            <div className="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{value}</div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
            {children}
        </div>
    );
}

ReportsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
