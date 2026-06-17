import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatDateTime, formatNumber } from '../../../lib/i18n';

const STATUS_BADGE = {
    DONE: 'bg-om-running-bg text-om-running',
    CANCELLED: 'bg-om-chip text-om-muted',
    REJECTED: 'bg-om-blocked-bg text-om-blocked',
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

    const columns = useMemo(
        () => [
            {
                id: 'order_no',
                accessorKey: 'order_no',
                header: __('Order'),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span className="font-medium text-om-accent">
                            <Link href={`/admin/reports/${r.id}`} onClick={(e) => e.stopPropagation()}>
                                {r.order_no}
                            </Link>
                        </span>
                    );
                },
            },
            {
                id: 'product',
                accessorFn: (r) => r.product_name ?? '',
                header: __('Product'),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span className="text-om-muted">
                            {r.product_name ?? '—'}
                            {r.product_code && (
                                <span className="text-xs text-om-faint font-mono ml-1">{r.product_code}</span>
                            )}
                        </span>
                    );
                },
            },
            {
                id: 'line',
                accessorFn: (r) => r.line_name ?? '',
                header: __('Line'),
                cell: ({ row }) => <span className="text-om-muted">{row.original.line_name ?? '—'}</span>,
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: __('Status'),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span
                            className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                STATUS_BADGE[r.status] ?? 'bg-om-chip text-om-muted'
                            }`}
                        >
                            {__(r.status)}
                        </span>
                    );
                },
            },
            {
                id: 'completed',
                accessorKey: 'completed_at',
                header: __('Completed'),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span className="text-om-muted whitespace-nowrap">
                            {r.completed_at ? formatDateTime(r.completed_at) : '—'}
                        </span>
                    );
                },
            },
            {
                id: 'produced_planned',
                accessorFn: (r) => r.produced_qty,
                header: `${__('Produced')} / ${__('Planned')}`,
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span className="font-mono">
                            {formatNumber(r.produced_qty)} / {formatNumber(r.planned_qty)}
                        </span>
                    );
                },
            },
            {
                id: 'execution',
                accessorKey: 'execution_minutes',
                header: __('Execution'),
                cell: ({ row }) => (
                    <span className="text-om-muted whitespace-nowrap">
                        {fmtDuration(row.original.execution_minutes)}
                    </span>
                ),
            },
            {
                id: 'lots',
                accessorFn: (r) => (r.lots ? r.lots.join(', ') : ''),
                header: __('LOTs'),
                cell: ({ row }) => {
                    const r = row.original;
                    return (
                        <span className="font-mono text-xs text-om-muted">
                            {r.lots.length ? r.lots.slice(0, 2).join(', ') : '—'}
                            {r.lots.length > 2 && <span className="text-om-faint"> +{r.lots.length - 2}</span>}
                        </span>
                    );
                },
            },
            {
                id: 'issues',
                accessorKey: 'issues_count',
                header: __('Issues'),
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const r = row.original;
                    return r.issues_count > 0 ? (
                        <span className="px-2 py-0.5 rounded-full text-xs bg-om-downtime-bg text-om-downtime">
                            {r.issues_count}
                        </span>
                    ) : (
                        <span className="text-om-faintest">0</span>
                    );
                },
            },
        ],
        []
    );

    return (
        <>
            <Head title={__('Work Order History')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-om-ink">{__('Work Order History')}</h1>
                        <p className="text-om-muted mt-1">
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
                    <Field label={__('Status')}>
                        <Dropdown
                            value={form.status == null ? '' : String(form.status)}
                            onChange={(v) => setForm((f) => ({ ...f, status: v }))}
                            options={[
                                { value: '', label: __('All') },
                                ...statusOptions.map((s) => ({ value: String(s), label: __(s) })),
                            ]}
                            className="w-44"
                        />
                    </Field>
                    <Field label={__('Line')}>
                        <Dropdown
                            value={form.line_id == null ? '' : String(form.line_id)}
                            onChange={(v) => setForm((f) => ({ ...f, line_id: v }))}
                            options={[
                                { value: '', label: __('All') },
                                ...lines.map((l) => ({ value: String(l.id), label: l.name })),
                            ]}
                            className="w-44"
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
                            className="w-44"
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
                    <button type="button" onClick={() => apply()} className="btn-touch btn-primary">
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
                    paginated={false}
                    searchPlaceholder={__('Order no. or LOT')}
                    emptyLabel={__('No orders match the current filters.')}
                    onRowClick={(r) => router.visit(`/admin/reports/${r.id}`)}
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

ReportsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
