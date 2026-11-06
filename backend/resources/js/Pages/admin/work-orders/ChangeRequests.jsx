import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime, __ } from '../../../lib/i18n';

/**
 * The reviewer's queue of production-change requests (#182, ERP P0-B).
 *
 * Requests arrive from two directions — raised on an order's page, or sent by the
 * ERP for an order already in production. The ERP's have no page visit behind them,
 * so this list is where a manager finds them. Open requests come first. Each row
 * opens the review page, where approval, rejection and application happen.
 */

const STATUS_STYLES = {
    DRAFT: 'bg-om-chip text-om-muted',
    SUBMITTED: 'bg-om-chip text-om-accent',
    APPROVED: 'bg-om-running-bg text-om-running',
    APPLIED: 'bg-om-running-bg text-om-running',
    REJECTED: 'bg-om-blocked-bg text-om-blocked',
    CANCELLED: 'bg-om-chip text-om-faint',
};

function stamp(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : formatDateTime(d, {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

export default function ChangeRequestsIndex() {
    const { changeRequests, filters = {}, statuses = [], basePath } = usePage().props;
    const rows = changeRequests?.data ?? [];

    function filterBy(status) {
        router.get(basePath, status ? { status } : {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title={__('Change requests')} />

            <div className="max-w-7xl mx-auto">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-om-ink">{__('Change requests')}</h1>
                        <p className="text-sm text-om-muted mt-1">
                            {__('Changes and cancellations of work orders in production, waiting for a decision or already decided.')}
                        </p>
                    </div>
                    <div>
                        <select
                            aria-label={__('Status')}
                            value={filters.status ?? ''}
                            onChange={(e) => filterBy(e.target.value)}
                            className="border border-om-line rounded-md px-3 py-2 text-sm bg-om-card text-om-ink focus:outline-none focus:ring-2 focus:ring-om-accent"
                        >
                            <option value="">{__('All statuses')}</option>
                            <option value="OPEN">{__('Open')}</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 overflow-x-auto">
                    {rows.length === 0 ? (
                        <p className="text-sm text-om-faint p-6 text-center">{__('No change requests.')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-om-faint border-b border-om-line2">
                                    <th className="px-4 py-2 font-medium">{__('Code')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Title')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Type')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Work order')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Status')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Requested by')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Submitted')}</th>
                                    <th className="px-4 py-2 font-medium">{__('Decided')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((cr) => (
                                    <tr
                                        key={cr.id}
                                        onClick={() => router.visit(`/admin/work-order-change-requests/${cr.id}`)}
                                        className="border-b border-om-line2 last:border-0 cursor-pointer hover:bg-om-bg"
                                    >
                                        <td className="px-4 py-2 font-mono text-om-muted whitespace-nowrap">
                                            <Link
                                                href={`/admin/work-order-change-requests/${cr.id}`}
                                                className="hover:underline"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                {cr.code}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2 text-om-ink font-medium">{cr.title}</td>
                                        <td className="px-4 py-2">
                                            <span className={`px-2 py-0.5 rounded text-xs font-semibold ${cr.type === 'CANCEL' ? 'bg-om-blocked-bg text-om-blocked' : 'bg-om-chip text-om-muted'}`}>
                                                {cr.type_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 font-mono text-om-muted whitespace-nowrap">{cr.work_order?.order_no ?? '—'}</td>
                                        <td className="px-4 py-2">
                                            <span className={`px-2 py-0.5 rounded text-xs font-semibold ${STATUS_STYLES[cr.status] ?? 'bg-om-chip text-om-muted'}`}>
                                                {cr.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-om-muted">{cr.requested_by ?? '—'}</td>
                                        <td className="px-4 py-2 text-om-faint whitespace-nowrap">{stamp(cr.submitted_at)}</td>
                                        <td className="px-4 py-2 text-om-faint whitespace-nowrap">{stamp(cr.decided_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {changeRequests?.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm text-om-muted">
                        <span>
                            {__('Page :page of :pages', { page: changeRequests.current_page, pages: changeRequests.last_page })}
                        </span>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                disabled={!changeRequests.prev_page_url}
                                onClick={() => router.visit(changeRequests.prev_page_url, { preserveScroll: true })}
                                className="px-3 py-1 rounded border border-om-line disabled:text-om-faint disabled:cursor-default hover:bg-om-bg"
                            >
                                {__('Previous')}
                            </button>
                            <button
                                type="button"
                                disabled={!changeRequests.next_page_url}
                                onClick={() => router.visit(changeRequests.next_page_url, { preserveScroll: true })}
                                className="px-3 py-1 rounded border border-om-line disabled:text-om-faint disabled:cursor-default hover:bg-om-bg"
                            >
                                {__('Next')}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ChangeRequestsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
