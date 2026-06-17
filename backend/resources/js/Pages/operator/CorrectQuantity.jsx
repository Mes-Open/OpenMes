import { Head, Link, useForm, usePage } from '@inertiajs/react';
import OperatorLayout from '../../layouts/OperatorLayout';

export default function CorrectQuantity() {
    const { shiftEntry, workOrder } = usePage().props;
    const form = useForm({ quantity: String(Math.round(shiftEntry.quantity)) });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/operator/shift-entry/${shiftEntry.id}/correct`);
    };

    return (
        <>
            <Head title="Correct Quantity" />
            <div className="max-w-md mx-auto mt-8">
                <div className="bg-om-card rounded-om-sm shadow-sm p-6">
                    <div className="flex items-center gap-3 mb-6">
                        <Link href="/operator/workstation" className="text-om-muted hover:text-om-ink">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-om-ink">Correct Quantity</h1>
                            <p className="text-sm text-om-muted">Modify a previously reported production quantity.</p>
                        </div>
                    </div>

                    <div className="space-y-3 mb-6">
                        <Row label="Order No"><span className="font-bold font-mono">{workOrder.order_no}</span></Row>
                        <Row label="Product"><span className="font-medium">{workOrder.product_name ?? '—'}</span></Row>
                        <Row label="Shift"><span className="font-medium">{shiftEntry.shift.name ?? shiftEntry.shift.code}</span></Row>
                        <Row label="Production Date"><span className="font-medium">{shiftEntry.production_date}</span></Row>
                        <Row label="Current Quantity"><span className="font-bold text-om-accent">{Math.round(shiftEntry.quantity)}</span></Row>
                    </div>

                    <form onSubmit={submit}>
                        <div className="mb-5">
                            <label className="block text-sm font-medium text-om-muted mb-1">
                                New Quantity <span className="text-om-blocked">*</span>
                            </label>
                            <input
                                type="number" min="0" max="99999999" step="1" required autoFocus inputMode="numeric"
                                value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', e.target.value)}
                                className="form-input w-full text-2xl font-bold text-center py-3 tabular-nums"
                            />
                            {form.errors.quantity && <p className="text-om-blocked text-sm mt-1">{form.errors.quantity}</p>}
                        </div>

                        <div className="flex gap-3">
                            <Link href="/operator/workstation" className="flex-1 py-3 text-base text-center rounded-om-sm bg-om-line2 hover:bg-om-line text-om-ink font-medium">
                                Cancel
                            </Link>
                            <button type="submit" disabled={form.processing} className="flex-1 bg-om-ink hover:bg-om-ink-hover text-om-on-ink font-bold rounded-om-sm py-3 text-base disabled:opacity-50">
                                Save Correction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

function Row({ label, children }) {
    return (
        <div className="flex justify-between border-b border-om-line2 pb-2 text-sm">
            <span className="text-om-muted">{label}</span>
            {children}
        </div>
    );
}

CorrectQuantity.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
