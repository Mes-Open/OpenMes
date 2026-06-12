import { Link, useForm, usePage } from '@inertiajs/react';

export default function PalletForm({ action, method, initial, submitLabel }) {
    const { workOrders = [], statuses = [] } = usePage().props;
    const form = useForm(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <form onSubmit={submit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">
                    Work order <span className="text-om-blocked">*</span>
                </label>
                <select
                    value={data.work_order_id ?? ''}
                    onChange={(e) => setData('work_order_id', e.target.value)}
                    className="form-input w-full"
                >
                    <option value="">— Select work order —</option>
                    {workOrders.map((wo) => (
                        <option key={wo.id} value={String(wo.id)}>
                            {wo.order_no}
                        </option>
                    ))}
                </select>
                {errors.work_order_id && <p className="mt-1 text-xs text-om-blocked">{errors.work_order_id}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">Quantity</label>
                    <input
                        type="number"
                        min={0}
                        value={data.qty ?? 0}
                        onChange={(e) => setData('qty', e.target.value)}
                        className="form-input w-full"
                    />
                    {errors.qty && <p className="mt-1 text-xs text-om-blocked">{errors.qty}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-om-muted mb-1">
                        Status <span className="text-om-blocked">*</span>
                    </label>
                    <select
                        value={data.status ?? 'open'}
                        onChange={(e) => setData('status', e.target.value)}
                        className="form-input w-full"
                    >
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    {errors.status && <p className="mt-1 text-xs text-om-blocked">{errors.status}</p>}
                </div>
            </div>

            <TextField
                label="Location"
                value={data.location}
                error={errors.location}
                onChange={(v) => setData('location', v)}
            />
            <TextField
                label="ERP reference"
                value={data.erp_reference}
                error={errors.erp_reference}
                onChange={(v) => setData('erp_reference', v)}
            />

            <div className="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50"
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/pallets" className="text-om-muted hover:text-om-ink text-sm">
                    Cancel
                </Link>
            </div>
        </form>
    );
}

function TextField({ label, value, error, onChange }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">{label}</label>
            <input
                type="text"
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="form-input w-full"
            />
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
