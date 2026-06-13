import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

export default function InspectionsCreate() {
    const { materials = [], plans = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        material_id: '',
        lot_number: '',
        supplier_lot_ref: '',
        source_container_no: '',
        quantity_received: '',
        inspection_plan_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/inspections');
    };

    return (
        <>
            <Head title="Start Inspection" />

            <div className="max-w-2xl mx-auto">
                <h1 className="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">Start Inspection</h1>

                <form onSubmit={submit} className="card space-y-4">
                    {/* Material */}
                    <div>
                        <label className="block text-sm font-medium mb-1">
                            Material <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.material_id}
                            onChange={(e) => setData('material_id', e.target.value)}
                            required
                            className="form-input w-full"
                        >
                            <option value="">-- choose --</option>
                            {materials.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.code} — {m.name}
                                </option>
                            ))}
                        </select>
                        {errors.material_id && (
                            <p className="text-red-600 text-xs mt-1">{errors.material_id}</p>
                        )}
                    </div>

                    {/* Lot + Supplier ref */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium mb-1">
                                Lot number <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                maxLength={100}
                                value={data.lot_number}
                                onChange={(e) => setData('lot_number', e.target.value)}
                                className="form-input w-full"
                            />
                            {errors.lot_number && (
                                <p className="text-red-600 text-xs mt-1">{errors.lot_number}</p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Supplier lot ref</label>
                            <input
                                type="text"
                                maxLength={100}
                                value={data.supplier_lot_ref}
                                onChange={(e) => setData('supplier_lot_ref', e.target.value)}
                                className="form-input w-full"
                            />
                            {errors.supplier_lot_ref && (
                                <p className="text-red-600 text-xs mt-1">{errors.supplier_lot_ref}</p>
                            )}
                        </div>
                    </div>

                    {/* Source container (scan) */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Source container no</label>
                        <input
                            type="text"
                            maxLength={100}
                            value={data.source_container_no}
                            onChange={(e) => setData('source_container_no', e.target.value)}
                            placeholder="Scan container barcode…"
                            className="form-input w-full font-mono"
                        />
                        <p className="text-xs text-gray-500 mt-1">
                            Scan the barcode of the container/pallet the delivery arrived in. Carried onto the created material lot for traceability.
                        </p>
                        {errors.source_container_no && (
                            <p className="text-red-600 text-xs mt-1">{errors.source_container_no}</p>
                        )}
                    </div>

                    {/* Quantity received */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Quantity received</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            value={data.quantity_received}
                            onChange={(e) => setData('quantity_received', e.target.value)}
                            className="form-input w-full"
                        />
                        {errors.quantity_received && (
                            <p className="text-red-600 text-xs mt-1">{errors.quantity_received}</p>
                        )}
                    </div>

                    {/* Inspection plan */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Inspection plan</label>
                        <select
                            value={data.inspection_plan_id}
                            onChange={(e) => setData('inspection_plan_id', e.target.value)}
                            className="form-input w-full"
                        >
                            <option value="">— no plan (ad-hoc) —</option>
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name}
                                    {plan.material ? ` (${plan.material.name})` : ''}
                                    {plan.material_type ? ` (${plan.material_type.name})` : ''}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 mt-1">
                            If no plan is selected, you can still record results but no criteria will be pre-filled.
                        </p>
                        {errors.inspection_plan_id && (
                            <p className="text-red-600 text-xs mt-1">{errors.inspection_plan_id}</p>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Link href="/inspections" className="btn-touch btn-secondary">
                            Cancel
                        </Link>
                        <button type="submit" disabled={processing} className="btn-touch btn-primary disabled:opacity-50">
                            Start
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

InspectionsCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
