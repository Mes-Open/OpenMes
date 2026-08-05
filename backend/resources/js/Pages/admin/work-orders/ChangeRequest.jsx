import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDate, formatNumber, __ } from '../../../lib/i18n';

/**
 * Review screen for a production-change request (#182).
 *
 * The order of the page is the order of the decision: what is being changed, what it
 * means for work already done, and only then the buttons. The impact block is the
 * live analysis — the stored one is frozen as the approver saw it, which is the right
 * thing for the audit trail and the wrong thing for someone deciding now.
 */

const STATUS_STYLES = {
    DRAFT: 'bg-om-chip text-om-muted',
    SUBMITTED: 'bg-om-chip text-om-accent',
    APPROVED: 'bg-om-running-bg text-om-running',
    APPLIED: 'bg-om-running-bg text-om-running',
    REJECTED: 'bg-om-blocked-bg text-om-blocked',
    CANCELLED: 'bg-om-chip text-om-faint',
};

const FIELD_LABELS = {
    product_revision_id: () => __('Product revision'),
    planned_qty: () => __('Planned quantity'),
    line_id: () => __('Production line'),
    bom_template_ids: () => __('BOM selection'),
    due_date: () => __('Due date'),
    description: () => __('Description'),
    production_notes: () => __('Production notes'),
};

function num(n) {
    return formatNumber(Number(n ?? 0), { maximumFractionDigits: 2 });
}

function stamp(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : formatDate(d, {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function value(v) {
    if (v === null || v === undefined || v === '') return '—';
    if (Array.isArray(v)) return v.length ? v.join(', ') : '—';
    return String(v);
}

function Stat({ label, children, tone }) {
    return (
        <div className="bg-om-panel rounded-om-sm px-3 py-2">
            <p className="text-xs text-om-faint">{label}</p>
            <p className={`text-lg font-semibold ${tone ?? 'text-om-ink'}`}>{children}</p>
        </div>
    );
}

function RejectModal({ changeRequest, onClose }) {
    const [reason, setReason] = useState('');

    function submit(e) {
        e.preventDefault();
        router.post(`/admin/work-order-change-requests/${changeRequest.id}/reject`, { reason }, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-md">
                <h3 className="text-lg font-bold text-om-ink mb-1">{__('Reject change request')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    {__('A rejection is final. The reason stays on the record.')}
                </p>
                <form onSubmit={submit}>
                    <textarea
                        rows={4}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                        required
                    />
                    <div className="flex justify-end gap-2 mt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            {__('Cancel')}
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-medium text-white bg-om-blocked rounded-md hover:brightness-95"
                        >
                            {__('Reject')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ApplyModal({ changeRequest, onClose }) {
    const [effectiveFrom, setEffectiveFrom] = useState(changeRequest.effective_from);
    const [notes, setNotes] = useState('');

    function submit(e) {
        e.preventDefault();
        router.post(
            `/admin/work-order-change-requests/${changeRequest.id}/apply`,
            { effective_from: effectiveFrom, implementation_notes: notes || null },
            { preserveScroll: true, onSuccess: onClose },
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-md">
                <h3 className="text-lg font-bold text-om-ink mb-1">{__('Apply change request')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    {__('This writes a new configuration version. Earlier versions and everything already produced stay exactly as recorded.')}
                </p>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Applies from')}</label>
                        <select
                            value={effectiveFrom}
                            onChange={(e) => setEffectiveFrom(e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                        >
                            <option value="NEXT_BATCH">{__('From the next batch')}</option>
                            <option value="REMAINING_QUANTITY">{__('For the remaining quantity')}</option>
                            <option value="IMMEDIATE">{__('Immediately')}</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">
                            {__('Implementation notes')} <span className="text-om-faint font-normal">({__('optional')})</span>
                        </label>
                        <textarea
                            rows={3}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            {__('Cancel')}
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 text-sm font-medium text-white bg-om-running rounded-md hover:brightness-95"
                        >
                            {__('Apply')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function AdminChangeRequestShow() {
    const { changeRequest: cr, can = {} } = usePage().props;
    const [showReject, setShowReject] = useState(false);
    const [showApply, setShowApply] = useState(false);

    const post = (verb) => router.post(`/admin/work-order-change-requests/${cr.id}/${verb}`, {}, { preserveScroll: true });

    const impact = cr.live_impact ?? {};
    const warnings = impact.warnings ?? [];
    const order = cr.work_order ?? {};

    return (
        <>
            <Head title={__('Change request :code', { code: cr.code })} />

            <nav className="flex items-center gap-2 text-sm text-om-muted mb-4">
                <Link href="/admin/work-orders" className="hover:text-om-ink">{__('Work Orders')}</Link>
                <span>/</span>
                <Link href={`/admin/work-orders/${order.id}`} className="hover:text-om-ink">#{order.order_no}</Link>
                <span>/</span>
                <span className="text-om-muted font-medium">{cr.code}</span>
            </nav>

            <div className="max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-bold text-om-ink">{cr.title}</h1>
                            <span className={`px-2 py-0.5 rounded text-xs font-semibold ${STATUS_STYLES[cr.status] ?? 'bg-om-chip text-om-muted'}`}>
                                {cr.status_label}
                            </span>
                        </div>
                        <p className="text-om-muted mt-1 font-mono text-sm">{cr.code}</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {cr.status === 'DRAFT' && can.submit && (
                            <button
                                onClick={() => post('submit')}
                                className="px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
                            >
                                {__('Submit for review')}
                            </button>
                        )}
                        {cr.status === 'SUBMITTED' && can.approve && (
                            <>
                                <button
                                    onClick={() => post('approve')}
                                    className="px-4 py-2 text-sm font-medium text-white bg-om-running rounded-md hover:brightness-95"
                                >
                                    {__('Approve')}
                                </button>
                                <button
                                    onClick={() => setShowReject(true)}
                                    className="px-4 py-2 text-sm font-medium text-om-blocked bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                                >
                                    {__('Reject')}
                                </button>
                            </>
                        )}
                        {cr.status === 'APPROVED' && can.apply && (
                            <button
                                onClick={() => setShowApply(true)}
                                className="px-4 py-2 text-sm font-medium text-white bg-om-running rounded-md hover:brightness-95"
                            >
                                {__('Apply change')}
                            </button>
                        )}
                        {['DRAFT', 'SUBMITTED', 'APPROVED'].includes(cr.status) && can.cancel && (
                            <button
                                onClick={() => { if (confirm(__('Withdraw this change request?'))) post('cancel'); }}
                                className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                            >
                                {__('Withdraw')}
                            </button>
                        )}
                        <Link
                            href={`/admin/work-orders/${order.id}`}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            ← {__('Work order')}
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">

                        {/* Proposed change */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h2 className="text-lg font-bold text-om-ink mb-1">{__('Proposed change')}</h2>
                            <p className="text-sm text-om-muted mb-4">
                                {__('Applies from')}: <span className="font-medium text-om-ink">{cr.effective_from_label}</span>
                            </p>

                            {(cr.diff ?? []).length === 0 ? (
                                <p className="text-sm text-om-faint py-3">{__('Nothing proposed.')}</p>
                            ) : (
                                <div className="space-y-2">
                                    {cr.diff.map((row) => (
                                        <div key={row.field} className="grid grid-cols-3 gap-3 items-center py-2 border-b border-om-line2 last:border-0">
                                            <span className="text-sm font-medium text-om-muted">
                                                {(FIELD_LABELS[row.field] ?? (() => row.field))()}
                                            </span>
                                            <span className="text-sm text-om-faint line-through">{value(row.from)}</span>
                                            <span className="text-sm font-semibold text-om-ink">{value(row.to)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div className="mt-4 pt-4 border-t border-om-line2">
                                <p className="text-xs text-om-faint mb-1">{__('Reason')}</p>
                                <p className="text-sm text-om-muted whitespace-pre-line">{cr.reason}</p>
                            </div>

                            {(cr.produced_disposition || cr.material_disposition) && (
                                <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {cr.produced_disposition && (
                                        <div>
                                            <p className="text-xs text-om-faint mb-1">{__('Disposition of produced units')}</p>
                                            <p className="text-sm text-om-muted whitespace-pre-line">{cr.produced_disposition}</p>
                                        </div>
                                    )}
                                    {cr.material_disposition && (
                                        <div>
                                            <p className="text-xs text-om-faint mb-1">{__('Disposition of material')}</p>
                                            <p className="text-sm text-om-muted whitespace-pre-line">{cr.material_disposition}</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Impact */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h2 className="text-lg font-bold text-om-ink mb-4">{__('Impact')}</h2>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                <Stat label={__('Produced')}>{num(impact.produced_qty)}</Stat>
                                <Stat label={__('Remaining')}>{num(impact.remaining_qty)}</Stat>
                                <Stat label={__('Completed batches')}>{impact.batches?.completed ?? 0}</Stat>
                                <Stat label={__('Active batches')}>{impact.batches?.active ?? 0}</Stat>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                <Stat label={__('Completed steps')}>{impact.steps?.completed ?? 0}</Stat>
                                <Stat label={__('Steps still ahead')}>{impact.steps?.not_started ?? 0}</Stat>
                                <Stat label={__('Material allocated')}>{num(impact.materials?.allocated_qty)}</Stat>
                                <Stat label={__('Material consumed')}>{num(impact.materials?.consumed_qty)}</Stat>
                            </div>

                            {impact.revision && (
                                <div className="mb-4 p-3 rounded-om-sm bg-om-panel text-sm">
                                    <span className="text-om-faint">{__('Product revision')}: </span>
                                    <span className="text-om-muted">{impact.revision.from ?? '—'}</span>
                                    <span className="text-om-faint"> → </span>
                                    <span className="font-semibold text-om-ink">{impact.revision.to ?? '—'}</span>
                                </div>
                            )}

                            {(impact.documents ?? []).length > 0 && (
                                <div className="mb-4">
                                    <p className="text-sm font-medium text-om-muted mb-2">
                                        {__('Technical documents being replaced')} ({impact.documents.length})
                                    </p>
                                    <div className="space-y-1">
                                        {impact.documents.map((d) => (
                                            <div key={d.document_id} className="text-sm text-om-muted bg-om-panel rounded px-2 py-1">
                                                #{d.document_id}: {value(d.from_revision)} → {value(d.to_revision)}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {warnings.length > 0 && (
                                <div className="rounded-om-sm bg-om-downtime-bg p-3">
                                    <p className="text-sm font-semibold text-om-downtime mb-2">
                                        {__('Conflicts with work already done')}
                                    </p>
                                    <ul className="space-y-1">
                                        {warnings.map((w, i) => (
                                            <li key={i} className="text-sm text-om-downtime">• {w}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {(impact.remaining_requirements ?? cr.impact?.remaining_requirements ?? []).length > 0 && (
                                <div className="mt-4">
                                    <p className="text-sm font-medium text-om-muted mb-2">
                                        {__('Recalculated material requirements for the remaining quantity')}
                                    </p>
                                    <div className="space-y-1">
                                        {(impact.remaining_requirements ?? cr.impact.remaining_requirements).map((r) => (
                                            <div key={r.material_id} className="flex justify-between text-sm bg-om-panel rounded px-2 py-1">
                                                <span className="text-om-muted">{r.material_code ?? `#${r.material_id}`}</span>
                                                <span className="font-medium text-om-ink">{num(r.remaining_qty)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h3 className="text-base font-bold text-om-ink mb-3">{__('Work order')}</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Order')}</span>
                                    <Link href={`/admin/work-orders/${order.id}`} className="font-mono text-om-accent hover:underline">
                                        {order.order_no}
                                    </Link>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Status')}</span>
                                    <span className="font-medium text-om-ink">{order.status}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-om-muted">{__('Configuration version')}</span>
                                    <span className="font-medium text-om-ink">v{order.snapshot_version ?? 1}</span>
                                </div>
                            </div>

                            {cr.stop && (
                                <div className="mt-4 pt-3 border-t border-om-line2">
                                    <p className="text-xs text-om-faint mb-1">{__('Raised from stop')}</p>
                                    <p className="text-sm font-medium text-om-ink">{cr.stop.type_label}</p>
                                    <p className="text-sm text-om-muted">{cr.stop.reason}</p>
                                    <p className="text-xs text-om-faint mt-1">{stamp(cr.stop.stopped_at)}</p>
                                </div>
                            )}
                        </div>

                        {/* Audit trail */}
                        <div className="bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5">
                            <h3 className="text-base font-bold text-om-ink mb-3">{__('History')}</h3>
                            <div className="space-y-3 text-sm">
                                <div>
                                    <p className="text-om-ink font-medium">{__('Requested')}</p>
                                    <p className="text-om-faint text-xs">{cr.requested_by ?? '—'}</p>
                                </div>
                                {cr.submitted_at && (
                                    <div>
                                        <p className="text-om-ink font-medium">{__('Submitted')}</p>
                                        <p className="text-om-faint text-xs">{stamp(cr.submitted_at)}</p>
                                    </div>
                                )}
                                {cr.approved_at && (
                                    <div>
                                        <p className="text-om-ink font-medium">{__('Approved')}</p>
                                        <p className="text-om-faint text-xs">{cr.approved_by} · {stamp(cr.approved_at)}</p>
                                    </div>
                                )}
                                {cr.rejected_at && (
                                    <div>
                                        <p className="text-om-blocked font-medium">{__('Rejected')}</p>
                                        <p className="text-om-faint text-xs">{cr.rejected_by} · {stamp(cr.rejected_at)}</p>
                                        <p className="text-om-muted text-xs mt-1">{cr.rejection_reason}</p>
                                    </div>
                                )}
                                {cr.applied_at && (
                                    <div>
                                        <p className="text-om-running font-medium">
                                            {__('Applied as version :version', { version: cr.resulting_snapshot_version })}
                                        </p>
                                        <p className="text-om-faint text-xs">{cr.applied_by} · {stamp(cr.applied_at)}</p>
                                        {cr.implementation_notes && (
                                            <p className="text-om-muted text-xs mt-1">{cr.implementation_notes}</p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {showReject && <RejectModal changeRequest={cr} onClose={() => setShowReject(false)} />}
            {showApply && <ApplyModal changeRequest={cr} onClose={() => setShowApply(false)} />}
        </>
    );
}

AdminChangeRequestShow.layout = (page) => <AppLayout>{page}</AppLayout>;
