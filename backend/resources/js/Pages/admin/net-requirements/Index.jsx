import { Head, router, usePage } from '@inertiajs/react';
import { DatePicker, Dropdown } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(undefined, { maximumFractionDigits: 4 });

export default function NetRequirementsIndex() {
    const {
        lines = [], lineId, dateFrom, dateTo,
        requirements = [], shortages = [],
        totals = { work_orders: 0, components: 0, shortage_components: 0, total_shortfall: 0 },
    } = usePage().props;

    const apply = (changes) =>
        router.get('/admin/net-requirements', { line_id: lineId ?? '', date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });

    const reqColumns = [
        { id: 'code', accessorKey: 'code', header: __('Code'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code ?? '—'}</span> },
        { id: 'name', accessorKey: 'name', header: __('Component'), cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.name}</span> },
        { id: 'required_qty', accessorFn: (r) => num(r.required_qty), header: __('Required'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.required_qty)}</span>, meta: { align: 'right' } },
        { id: 'available_qty', accessorFn: (r) => num(r.available_qty), header: __('Available'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.available_qty)}</span>, meta: { align: 'right' } },
        { id: 'net_qty', accessorFn: (r) => num(r.net_qty), header: __('Net'), cell: ({ row }) => <span className={`tabular-nums font-medium ${row.original.is_short ? 'text-om-blocked' : 'text-om-running'}`}>{fmt(row.original.net_qty)}</span>, meta: { align: 'right' } },
        { id: 'uom', accessorKey: 'unit_of_measure', header: __('Unit'), cell: ({ row }) => <span className="text-om-faint">{row.original.unit_of_measure ?? '—'}</span> },
        {
            id: 'status', accessorFn: (r) => (r.is_short ? 'short' : 'ok'), header: __('Status'),
            cell: ({ row }) => (
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${row.original.is_short ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-running-bg text-om-running'}`}>
                    {row.original.is_short ? __('Short') : __('OK')}
                </span>
            ),
        },
    ];

    const shortageColumns = [
        { id: 'code', accessorKey: 'code', header: __('Code'), cell: ({ row }) => <span className="font-mono text-om-muted">{row.original.code ?? '—'}</span> },
        { id: 'name', accessorKey: 'name', header: __('Component'), cell: ({ row }) => <span className="font-medium text-om-ink">{row.original.name}</span> },
        { id: 'required_qty', accessorFn: (r) => num(r.required_qty), header: __('Required'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.required_qty)}</span>, meta: { align: 'right' } },
        { id: 'available_qty', accessorFn: (r) => num(r.available_qty), header: __('Available'), cell: ({ row }) => <span className="tabular-nums">{fmt(row.original.available_qty)}</span>, meta: { align: 'right' } },
        { id: 'net_qty', accessorFn: (r) => num(r.net_qty), header: __('Shortfall'), cell: ({ row }) => <span className="tabular-nums font-medium text-om-blocked">{fmt(row.original.net_qty)}</span>, meta: { align: 'right' } },
        {
            id: 'related', accessorFn: (r) => (r.related_work_orders ?? []).join(' '), header: __('Driving work orders'), enableSorting: false,
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1">
                    {(row.original.related_work_orders ?? []).map((wo) => (
                        <span key={wo} className="font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px]">{wo}</span>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={__('Net requirements')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Net requirements')}</h1>
                    <p className="text-om-muted mt-1 text-sm">{__('Component requirements exploded from planned work orders, netted against on-hand stock, with a shortage list.')}</p>
                </div>

                {/* Filters */}
                <div className="bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label={__('Line')}>
                        <Dropdown
                            className="min-w-[160px]"
                            options={[{ value: '', label: __('All Lines') }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))]}
                            value={lineId == null ? '' : String(lineId)}
                            onChange={(v) => apply({ line_id: v })}
                        />
                    </Filter>
                    <Filter label={__('From')}>
                        <DatePicker value={dateFrom || null} onChange={(iso) => apply({ date_from: iso ?? '' })} className="w-44" />
                    </Filter>
                    <Filter label={__('To')}>
                        <DatePicker value={dateTo || null} onChange={(iso) => apply({ date_to: iso ?? '' })} className="w-44" />
                    </Filter>
                </div>

                {/* KPI cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Kpi label={__('Planned work orders')} value={totals.work_orders} />
                    <Kpi label={__('Components required')} value={totals.components} />
                    <Kpi label={__('Components short')} value={totals.shortage_components} tone={totals.shortage_components > 0 ? 'blocked' : null} />
                    <Kpi label={__('Total shortfall')} value={fmt(totals.total_shortfall)} tone={num(totals.total_shortfall) > 0 ? 'blocked' : null} />
                </div>

                {/* Shortages */}
                <Card title={__('Shortages')}>
                    {shortages.length === 0 ? (
                        <Empty>{__('No shortages — on-hand stock covers the planned work orders.')}</Empty>
                    ) : (
                        <DataTable
                            data={shortages}
                            columns={shortageColumns}
                            searchable
                            paginated
                            searchPlaceholder={__('Search components…')}
                            emptyLabel={__('No shortages.')}
                        />
                    )}
                </Card>

                {/* Full net requirements */}
                <Card title={__('Net requirements by component')}>
                    {requirements.length === 0 ? (
                        <Empty>{__('No planned work orders in this period.')}</Empty>
                    ) : (
                        <DataTable
                            data={requirements}
                            columns={reqColumns}
                            searchable
                            columnToggle
                            paginated
                            searchPlaceholder={__('Search components…')}
                            emptyLabel={__('No requirements.')}
                        />
                    )}
                </Card>
            </div>
        </>
    );
}

NetRequirementsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ─────────────────────────────── helpers ──────────────────────────────────── */

function Filter({ label, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-om-muted mb-1">{label}</label>
            {children}
        </div>
    );
}

function Kpi({ label, value, tone }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om-sm p-5">
            <p className="text-sm text-om-muted">{label}</p>
            <p className={`text-2xl font-bold truncate ${tone === 'blocked' ? 'text-om-blocked' : 'text-om-ink'}`} title={String(value)}>{value}</p>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-om-card border border-om-line rounded-om-sm p-5">
            <h2 className="text-lg font-semibold text-om-ink mb-4">{title}</h2>
            {children}
        </div>
    );
}

function Empty({ children }) {
    return <p className="text-om-muted text-center py-8">{children}</p>;
}
