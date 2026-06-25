import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { formatNumber, __ } from '../../lib/i18n';

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

function statusBadge(status) {
    const map = {
        pass: 'bg-green-100 text-green-700',
        conditional_pass: 'bg-yellow-100 text-yellow-700',
        fail: 'bg-red-100 text-red-700',
        pending: 'bg-gray-100 text-gray-600',
    };
    return map[status] ?? 'bg-gray-100 text-gray-600';
}

function dispositionBadge(disposition) {
    const map = {
        accept: 'bg-green-100 text-green-700',
        accept_with_deviation: 'bg-green-100 text-green-800',
        rework: 'bg-yellow-100 text-yellow-700',
        quarantine: 'bg-blue-100 text-blue-700',
        scrap: 'bg-red-100 text-red-700',
        reject: 'bg-red-100 text-red-800',
        return_to_supplier: 'bg-purple-100 text-purple-700',
        pending: 'bg-gray-100 text-gray-500',
    };
    return map[disposition] ?? 'bg-gray-100 text-gray-500';
}

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

    return (
        <>
            <Head title={__('Inbound Inspections')} />

            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('Inbound Inspections')}</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-1">
                            {__('Receive material lots and verify them against an inspection plan.')}
                        </p>
                    </div>
                    <Link href="/inspections/create" className="btn-touch btn-primary">
                        + {__('Start inspection')}
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                    <div className="card text-center">
                        <div className="text-xs text-gray-500 uppercase">{__('Pending')}</div>
                        <div className={`text-2xl font-bold ${(stats.pending ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400'}`}>
                            {stats.pending ?? 0}
                        </div>
                    </div>
                    <div className="card text-center">
                        <div className="text-xs text-gray-500 uppercase">{__('Failed (30d)')}</div>
                        <div className={`text-2xl font-bold ${(stats.recent_fail ?? 0) > 0 ? 'text-red-600' : 'text-green-600'}`}>
                            {stats.recent_fail ?? 0}
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 mb-3 border-b border-gray-200 dark:border-gray-700">
                    {tabs.map(({ key, label }) => (
                        <a
                            key={key}
                            href={tabHref(key)}
                            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${
                                tab === key
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-800'
                            }`}
                        >
                            {label}
                        </a>
                    ))}
                </div>

                {/* Disposition filter */}
                <div className="flex items-center gap-2 mb-3 text-sm">
                    <label htmlFor="disposition" className="text-gray-600 dark:text-gray-400">{__('Disposition:')}</label>
                    <select
                        id="disposition"
                        value={selectedDisposition}
                        onChange={(e) => router.visit(dispHref(e.target.value), { preserveScroll: true })}
                        className="form-input w-48"
                    >
                        <option value="">{__('All')}</option>
                        {DISPOSITION_OPTIONS.map((d) => (
                            <option key={d} value={d}>{__(DISPOSITION_LABELS[d] ?? d)}</option>
                        ))}
                    </select>
                    {selectedDisposition && (
                        <a href={`/inspections?tab=${tab}`} className="text-xs text-gray-500 hover:underline">
                            {__('Clear')}
                        </a>
                    )}
                </div>

                {/* Table */}
                {inspections.length === 0 ? (
                    <div className="card text-center py-8 text-gray-500">{__('No inspections in this tab.')}</div>
                ) : (
                    <div className="card overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-slate-700">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{__('Started')}</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{__('Material')}</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{__('Lot')}</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{__('Qty')}</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{__('Inspector')}</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{__('Status')}</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{__('Disposition')}</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{__('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {inspections.map((insp) => (
                                    <tr key={insp.id}>
                                        <td className="px-3 py-2 font-mono text-xs">{insp.started_at_formatted ?? '—'}</td>
                                        <td className="px-3 py-2">{insp.material?.name ?? '—'}</td>
                                        <td className="px-3 py-2 font-mono">{insp.lot_number}</td>
                                        <td className="px-3 py-2 text-right font-mono">
                                            {insp.quantity_received != null ? fmtNum(insp.quantity_received) : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-gray-500">{insp.inspector?.name ?? '—'}</td>
                                        <td className="px-3 py-2 text-center">
                                            <span className={`inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize ${statusBadge(insp.status)}`}>
                                                {__(insp.status)}
                                            </span>
                                            {insp.issue_id && (
                                                <span className="block text-xs text-red-600 mt-1">
                                                    {__('NC #:id', { id: insp.issue_id })}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <span className={`inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize ${dispositionBadge(insp.disposition ?? 'pending')}`}>
                                                {__(DISPOSITION_LABELS[insp.disposition ?? 'pending'] ?? (insp.disposition ?? 'pending'))}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Link
                                                href={`/inspections/${insp.id}`}
                                                className="text-blue-600 hover:underline"
                                            >
                                                {insp.status === 'pending' ? __('Perform') : __('Open')}
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
