// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Dropdown, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import AppLayout from '../../layouts/AppLayout';
import { formatNumber, __ } from '../../lib/i18n';

const DISPOSITION_LABELS = {
    pending: __('Pending'),
    accept: 'Accept',
    accept_with_deviation: 'Accept with deviation',
    rework: 'Rework',
    quarantine: 'Quarantine',
    scrap: 'Scrap',
    reject: 'Reject',
    return_to_supplier: 'Return to supplier',
};

const DISPOSITION_OPTIONS = [
    'pending',
    'accept',
    'accept_with_deviation',
    'rework',
    'quarantine',
    'scrap',
    'reject',
    'return_to_supplier',
];

// Inspection status → StatusPill status token.
function statusPill(status) {
    const map = {
        pass: 'running',
        conditional_pass: 'downtime',
        fail: 'blocked',
        pending: 'pending',
    };
    return map[status] ?? 'pending';
}

// Disposition → StatusPill status token.
function dispositionPill(disposition) {
    const map = {
        accept: 'running',
        accept_with_deviation: 'running',
        rework: 'downtime',
        quarantine: 'pending',
        scrap: 'blocked',
        reject: 'blocked',
        return_to_supplier: 'downtime',
        pending: 'pending',
    };
    return map[disposition] ?? 'pending';
}

const TH_CLASS = 'px-3 py-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint';

function fmtNum(n) {
    if (n == null) return '—';
    return formatNumber(Number(n), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function InspectionsIndex() {
    const { inspections = [], tab = 'pending', stats = {}, selectedDisposition = '' } = usePage().props;

    const tabs = [
        { key: 'pending', label: __('Pending') },
        { key: 'recent', label: __('Recent') },
        { key: 'failed', label: __('Failed') },
    ];

    const tabHref = (key) => {
        const params = new URLSearchParams({ tab: key });
        if (selectedDisposition) params.set('disposition', selectedDisposition);
        return `/inspections?${params.toString()}`;
    };

    const dispHref = (d) => {
        const params = new URLSearchParams({ tab });
        if (d) params.set('disposition', d);
        return `/inspections?${params.toString()}`;
    };

    const columns = useMemo(() => [
        {
            id: 'started',
            accessorFn: (r) => r.started_at_formatted ?? '',
            header: __('Started'),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-om-muted">{row.original.started_at_formatted ?? '—'}</span>
            ),
        },
        {
            id: 'material',
            accessorFn: (r) => r.material?.name ?? '',
            header: __('Material'),
            cell: ({ row }) => <span className="text-om-ink">{row.original.material?.name ?? '—'}</span>,
        },
        {
            id: 'lot',
            accessorKey: 'lot_number',
            header: __('Lot'),
            cell: ({ row }) => <span className="font-mono text-om-ink">{row.original.lot_number}</span>,
        },
        {
            id: 'qty',
            accessorKey: 'quantity_received',
            header: __('Qty'),
            meta: { align: 'right' },
            cell: ({ row }) => (
                <span className="font-mono text-om-ink">
                    {row.original.quantity_received != null ? fmtNum(row.original.quantity_received) : '—'}
                </span>
            ),
        },
        {
            id: 'inspector',
            accessorFn: (r) => r.inspector?.name ?? '',
            header: __('Inspector'),
            cell: ({ row }) => <span className="text-om-muted">{row.original.inspector?.name ?? '—'}</span>,
        },
        {
            id: 'status',
            accessorKey: 'status',
            header: __('Status'),
            cell: ({ row }) => (
                <>
                    <StatusPill
                        status={statusPill(row.original.status)}
                        pulse={false}
                        label={(row.original.status ?? '').replace(/_/g, ' ')}
                    />
                    {row.original.issue_id && (
                        <span className="block font-mono text-[11px] text-om-blocked mt-1">
                            NC #{row.original.issue_id}
                        </span>
                    )}
                </>
            ),
        },
        {
            id: 'disposition',
            accessorKey: 'disposition',
            header: __('Disposition'),
            cell: ({ row }) => (
                <StatusPill
                    status={dispositionPill(row.original.disposition ?? 'pending')}
                    pulse={false}
                    label={(row.original.disposition ?? 'pending').replace(/_/g, ' ')}
                />
            ),
        },
        {
            id: 'actions',
            header: __('Actions'),
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => (
                <Link
                    href={`/inspections/${row.original.id}`}
                    className="text-om-accent hover:underline"
                >
                    {row.original.status === 'pending' ? __('Perform') : __('Open')}
                </Link>
            ),
        },
    ], []);

    return (
        <>
            <Head title={__('Inbound Inspections')} />

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Inbound Inspections')}</h1>
                        <p className="text-[13px] text-om-muted mt-1">
                            {__('Receive material lots and verify them against an inspection plan.')}
                        </p>
                    </div>
                    <Link
                        href="/inspections/create"
                        className="inline-flex items-center justify-center gap-2 rounded-om-sm bg-om-accent px-4 py-2.5 text-[13px] font-semibold text-white transition-colors hover:brightness-95"
                    >
                        + Start inspection
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Pending')}</div>
                        <div className={`mt-1 font-mono text-2xl font-semibold ${(stats.pending ?? 0) > 0 ? 'text-om-downtime' : 'text-om-faint'}`}>
                            {stats.pending ?? 0}
                        </div>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Failed (30d)')}</div>
                        <div className={`mt-1 font-mono text-2xl font-semibold ${(stats.recent_fail ?? 0) > 0 ? 'text-om-blocked' : 'text-om-running'}`}>
                            {stats.recent_fail ?? 0}
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 mb-3 border-b border-om-line">
                    {tabs.map(({ key, label }) => (
                        <a
                            key={key}
                            href={tabHref(key)}
                            className={`px-4 py-2 text-[13px] font-medium border-b-2 -mb-px transition-colors ${
                                tab === key
                                    ? 'border-om-accent text-om-ink'
                                    : 'border-transparent text-om-muted hover:text-om-ink'
                            }`}
                        >
                            {label}
                        </a>
                    ))}
                </div>

                {/* Disposition filter */}
                <div className="flex items-center gap-2 mb-3">
                    <label htmlFor="disposition" className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{__('Disposition:')}</label>
                    <Dropdown
                        value={selectedDisposition == null ? '' : String(selectedDisposition)}
                        onChange={(v) => router.visit(dispHref(v), { preserveScroll: true })}
                        options={[
                            { value: '', label: __('All') },
                            ...DISPOSITION_OPTIONS.map((d) => ({ value: String(d), label: DISPOSITION_LABELS[d] ?? d })),
                        ]}
                        className="w-48"
                    />
                    {selectedDisposition && (
                        <a href={`/inspections?tab=${tab}`} className="text-[11.5px] text-om-muted hover:text-om-ink">
                            {__('Clear')}
                        </a>
                    )}
                </div>

                {/* Table */}
                <DataTable
                    data={inspections}
                    columns={columns}
                    searchable
                    columnToggle
                    paginated
                    searchPlaceholder="Search inspections…"
                    emptyLabel={__('No inspections in this tab.')}
                />
            </div>
        </>
    );
}

InspectionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
