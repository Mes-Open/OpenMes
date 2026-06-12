// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, router, usePage } from '@inertiajs/react';
import { StatusPill } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { formatNumber } from '../../lib/i18n';

const DISPOSITION_LABELS = {
    pending: 'Pending',
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
        { key: 'pending', label: 'Pending' },
        { key: 'recent', label: 'Recent' },
        { key: 'failed', label: 'Failed' },
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

    return (
        <>
            <Head title="Inbound Inspections" />

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">Inbound Inspections</h1>
                        <p className="text-[13px] text-om-muted mt-1">
                            Receive material lots and verify them against an inspection plan.
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
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Pending</div>
                        <div className={`mt-1 font-mono text-2xl font-semibold ${(stats.pending ?? 0) > 0 ? 'text-om-downtime' : 'text-om-faint'}`}>
                            {stats.pending ?? 0}
                        </div>
                    </div>
                    <div className="bg-om-card border border-om-line rounded-om p-4 text-center">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Failed (30d)</div>
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
                    <label htmlFor="disposition" className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">Disposition:</label>
                    <select
                        id="disposition"
                        value={selectedDisposition}
                        onChange={(e) => router.visit(dispHref(e.target.value), { preserveScroll: true })}
                        className="w-48 bg-om-bg border border-om-line rounded-om-sm px-3 py-2 text-[13px] text-om-ink outline-none focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]"
                    >
                        <option value="">All</option>
                        {DISPOSITION_OPTIONS.map((d) => (
                            <option key={d} value={d}>{DISPOSITION_LABELS[d] ?? d}</option>
                        ))}
                    </select>
                    {selectedDisposition && (
                        <a href={`/inspections?tab=${tab}`} className="text-[11.5px] text-om-muted hover:text-om-ink">
                            Clear
                        </a>
                    )}
                </div>

                {/* Table */}
                {inspections.length === 0 ? (
                    <div className="bg-om-card border border-om-line rounded-om text-center py-8 text-[13px] text-om-muted">No inspections in this tab.</div>
                ) : (
                    <div className="bg-om-card border border-om-line rounded-om overflow-hidden">
                        <table className="min-w-full divide-y divide-om-line text-[13px]">
                            <thead className="bg-om-bg">
                                <tr>
                                    <th className={`${TH_CLASS} text-left`}>Started</th>
                                    <th className={`${TH_CLASS} text-left`}>Material</th>
                                    <th className={`${TH_CLASS} text-left`}>Lot</th>
                                    <th className={`${TH_CLASS} text-right`}>Qty</th>
                                    <th className={`${TH_CLASS} text-left`}>Inspector</th>
                                    <th className={`${TH_CLASS} text-center`}>Status</th>
                                    <th className={`${TH_CLASS} text-center`}>Disposition</th>
                                    <th className={`${TH_CLASS} text-right`}>Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-om-line">
                                {inspections.map((insp) => (
                                    <tr key={insp.id}>
                                        <td className="px-3 py-2 font-mono text-[12px] text-om-muted">{insp.started_at_formatted ?? '—'}</td>
                                        <td className="px-3 py-2 text-om-ink">{insp.material?.name ?? '—'}</td>
                                        <td className="px-3 py-2 font-mono text-om-ink">{insp.lot_number}</td>
                                        <td className="px-3 py-2 text-right font-mono text-om-ink">
                                            {insp.quantity_received != null ? fmtNum(insp.quantity_received) : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-om-muted">{insp.inspector?.name ?? '—'}</td>
                                        <td className="px-3 py-2 text-center">
                                            <StatusPill
                                                status={statusPill(insp.status)}
                                                pulse={false}
                                                label={(insp.status ?? '').replace(/_/g, ' ')}
                                            />
                                            {insp.issue_id && (
                                                <span className="block font-mono text-[11px] text-om-blocked mt-1">
                                                    NC #{insp.issue_id}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <StatusPill
                                                status={dispositionPill(insp.disposition ?? 'pending')}
                                                pulse={false}
                                                label={(insp.disposition ?? 'pending').replace(/_/g, ' ')}
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Link
                                                href={`/inspections/${insp.id}`}
                                                className="text-om-accent hover:underline"
                                            >
                                                {insp.status === 'pending' ? 'Perform' : 'Open'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

InspectionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
