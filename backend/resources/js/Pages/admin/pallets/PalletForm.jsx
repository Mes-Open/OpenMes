import { Link, useForm, usePage } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

/**
 * Blank/loaded form values — the one builder Create, Edit and the list drawer
 * all share. The drawer call site adds `stay: 1` itself.
 */
export function palletInitial(r) {
    return {
        work_order_id: r?.work_order_id != null ? String(r.work_order_id) : '',
        batch_id: r?.batch_id != null ? String(r.batch_id) : '',
        qty: r?.qty ?? 0,
        status: r?.status ?? 'open',
        location: r?.location ?? '',
        destination: r?.destination ?? '',
        erp_reference: r?.erp_reference ?? '',
    };
}

export default function PalletForm({ action, method, initial, submitLabel, bare = false, onSuccess, onCancel }) {
    const { workOrders = [], statuses = [] } = usePage().props;
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const selectedWo = workOrders.find((wo) => String(wo.id) === String(data.work_order_id));
    const batches = selectedWo?.batches ?? [];

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action, { preserveScroll: true, ...(onSuccess ? { onSuccess } : {}) });
    };

    return (
        <form onSubmit={submit} className={bare ? 'space-y-5' : 'bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5'}>
            <div>
                <div className="block text-sm font-medium text-om-muted mb-1">
                    {__('Work order')} <span className="text-om-blocked">*</span>
                </div>
                <Dropdown
                    aria-label={__('Work order')}
                    value={data.work_order_id == null ? '' : String(data.work_order_id)}
                    onChange={(v) => { setData('work_order_id', v); setData('batch_id', ''); }}
                    placeholder={__('— Select work order —')}
                    options={workOrders.map((wo) => ({ value: String(wo.id), label: wo.order_no }))}
                    className="w-full"
                />
                {errors.work_order_id && <p className="mt-1 text-xs text-om-blocked">{errors.work_order_id}</p>}
            </div>

            {batches.length > 0 && (
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__('Batch')}</div>
                    <Dropdown
                        aria-label={__('Batch')}
                        value={data.batch_id == null ? '' : String(data.batch_id)}
                        onChange={(v) => setData('batch_id', v)}
                        placeholder={__('— None —')}
                        options={batches.map((b) => ({ value: String(b.id), label: b.label }))}
                        className="w-full"
                    />
                    {errors.batch_id && <p className="mt-1 text-xs text-om-blocked">{errors.batch_id}</p>}
                </div>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">{__('Quantity')}</div>
                    <input
                        aria-label={__('Quantity')}
                        type="number"
                        min={0}
                        value={data.qty ?? 0}
                        onChange={(e) => setData('qty', e.target.value)}
                        className="form-input w-full"
                    />
                    {errors.qty && <p className="mt-1 text-xs text-om-blocked">{errors.qty}</p>}
                </div>
                <div>
                    <div className="block text-sm font-medium text-om-muted mb-1">
                        {__('Status')} <span className="text-om-blocked">*</span>
                    </div>
                    <Dropdown
                        aria-label={__('Status')}
                        value={data.status == null ? '' : String(data.status)}
                        onChange={(v) => setData('status', v)}
                        options={statuses.map((s) => ({ value: String(s.value), label: s.label }))}
                        className="w-full"
                    />
                    {errors.status && <p className="mt-1 text-xs text-om-blocked">{errors.status}</p>}
                </div>
            </div>

            <TextField
                label={__('Location')}
                value={data.location}
                error={errors.location}
                onChange={(v) => setData('location', v)}
            />
            <TextField
                label={__('Destination')}
                value={data.destination}
                error={errors.destination}
                onChange={(v) => setData('destination', v)}
            />
            <TextField
                label={__('ERP reference')}
                value={data.erp_reference}
                error={errors.erp_reference}
                onChange={(v) => setData('erp_reference', v)}
            />

            <div className="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50"
                >
                    {processing ? __('Saving…') : submitLabel}
                </button>
                {onCancel ? (
                    <button type="button" onClick={onCancel} className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</button>
                ) : (
                    <Link href="/admin/pallets" className="text-om-muted hover:text-om-ink text-sm">{__('Cancel')}</Link>
                )}
            </div>
        </form>
    );
}

function TextField({ label, value, error, onChange }) {
    return (
        <div>
            <div className="block text-sm font-medium text-om-muted mb-1">{label}</div>
            <input
                aria-label={label}
                type="text"
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="form-input w-full"
            />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
