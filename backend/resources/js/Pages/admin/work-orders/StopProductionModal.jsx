import { useForm } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';

/**
 * Stop production on a running work order (#182).
 *
 * "Requires a configuration change" is the decision that matters here: it puts the
 * order on CHANGE_HOLD instead of PAUSED, and the backend then refuses to resume it
 * until an approved change request has been applied. The copy says so, because an
 * operator ticking it is committing the order to a review cycle.
 */
export default function StopProductionModal({ workOrder, options, onClose }) {
    const form = useForm({
        type: 'OPERATIONAL',
        reason: '',
        batch_id: '',
        downtime_reason_id: '',
        requires_change: false,
    });

    function submit(e) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            batch_id: data.batch_id || null,
            downtime_reason_id: data.downtime_reason_id || null,
        }));
        form.post(`/admin/work-orders/${workOrder.id}/stop`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    const openBatches = (workOrder.batches ?? []).filter((b) => b.status !== 'DONE');

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-lg max-h-full overflow-y-auto">
                <h3 className="text-lg font-bold text-om-ink mb-1">{__('Stop production')}</h3>
                <p className="text-sm text-om-muted mb-4">
                    {__('Recorded against :order with the state it is in right now.', { order: workOrder.order_no })}
                </p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Stop type')}</label>
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                        >
                            {(options.stop_types ?? []).map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                        {form.errors.type && <p className="text-xs text-om-blocked mt-1">{form.errors.type}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-om-muted mb-1">{__('Reason')}</label>
                        <textarea
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            required
                        />
                        {form.errors.reason && <p className="text-xs text-om-blocked mt-1">{form.errors.reason}</p>}
                    </div>

                    {openBatches.length > 0 && (
                        <div>
                            <label className="block text-sm font-medium text-om-muted mb-1">
                                {__('Affected batch')} <span className="text-om-faint font-normal">({__('optional')})</span>
                            </label>
                            <select
                                value={form.data.batch_id}
                                onChange={(e) => form.setData('batch_id', e.target.value)}
                                className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            >
                                <option value="">{__('None')}</option>
                                {openBatches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {__('Batch #:no', { no: b.batch_number })} — {b.status}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {(options.downtimeReasons ?? []).length > 0 && (
                        <div>
                            <label className="block text-sm font-medium text-om-muted mb-1">
                                {__('Downtime reason')} <span className="text-om-faint font-normal">({__('optional')})</span>
                            </label>
                            <select
                                value={form.data.downtime_reason_id}
                                onChange={(e) => form.setData('downtime_reason_id', e.target.value)}
                                className="w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent"
                            >
                                <option value="">{__('Do not record downtime')}</option>
                                {options.downtimeReasons.map((r) => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                            <p className="text-xs text-om-faint mt-1">
                                {__('Opens a linked downtime record, closed automatically when production resumes.')}
                            </p>
                        </div>
                    )}

                    <label className="flex items-start gap-2 p-3 rounded-om-sm bg-om-panel cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.data.requires_change}
                            onChange={(e) => form.setData('requires_change', e.target.checked)}
                            className="mt-0.5"
                        />
                        <span className="text-sm">
                            <span className="font-medium text-om-ink">{__('A configuration change is required')}</span>
                            <span className="block text-xs text-om-muted mt-0.5">
                                {__('Puts the order on change hold. Production cannot resume until an approved change request has been applied.')}
                            </span>
                        </span>
                    </label>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
                        >
                            {__('Cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="px-4 py-2 text-sm font-medium text-white bg-om-downtime rounded-md hover:brightness-95 disabled:opacity-50"
                        >
                            {__('Stop production')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
