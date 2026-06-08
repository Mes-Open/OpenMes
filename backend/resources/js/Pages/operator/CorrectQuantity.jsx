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
                <div className="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
                    <div className="flex items-center gap-3 mb-6">
                        <Link href="/operator/workstation" className="text-gray-500 hover:text-gray-700">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-gray-800 dark:text-gray-100">Correct Quantity</h1>
                            <p className="text-sm text-gray-500">Modify a previously reported production quantity.</p>
                        </div>
                    </div>

                    <div className="space-y-3 mb-6">
                        <Row label="Order No"><span className="font-bold font-mono">{workOrder.order_no}</span></Row>
                        <Row label="Product"><span className="font-medium">{workOrder.product_name ?? '—'}</span></Row>
                        <Row label="Shift"><span className="font-medium">{shiftEntry.shift.name ?? shiftEntry.shift.code}</span></Row>
                        <Row label="Production Date"><span className="font-medium">{shiftEntry.production_date}</span></Row>
                        <Row label="Current Quantity"><span className="font-bold text-blue-600">{Math.round(shiftEntry.quantity)}</span></Row>
                    </div>

                    <form onSubmit={submit}>
                        <div className="mb-5">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                New Quantity <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number" min="0" max="99999999" step="1" required autoFocus inputMode="numeric"
                                value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', e.target.value)}
                                className="form-input w-full text-2xl font-bold text-center py-3 tabular-nums"
                            />
                            {form.errors.quantity && <p className="text-red-600 text-sm mt-1">{form.errors.quantity}</p>}
                        </div>

                        <div className="flex gap-3">
                            <Link href="/operator/workstation" className="flex-1 py-3 text-base text-center rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium">
                                Cancel
                            </Link>
                            <button type="submit" disabled={form.processing} className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg py-3 text-base disabled:opacity-50">
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
        <div className="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-2 text-sm">
            <span className="text-gray-500">{label}</span>
            {children}
        </div>
    );
}

CorrectQuantity.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
