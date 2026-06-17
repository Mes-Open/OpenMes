// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, TextField } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';

const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

export default function InspectionsCreate() {
    const { materials = [], plans = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        material_id: '',
        lot_number: '',
        supplier_lot_ref: '',
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
                <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-4">Start Inspection</h1>

                <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 space-y-5">
                    {/* Material */}
                    <div>
                        <label className={LABEL_CLASS}>
                            Material <span className="text-om-accent">*</span>
                        </label>
                        <Dropdown
                            value={data.material_id == null ? '' : String(data.material_id)}
                            onChange={(v) => setData('material_id', v)}
                            placeholder="-- choose --"
                            options={materials.map((m) => ({
                                value: String(m.id),
                                label: `${m.code} — ${m.name}`,
                            }))}
                            className="w-full"
                        />
                        {errors.material_id && (
                            <p className="text-[11.5px] text-om-blocked mt-1">{errors.material_id}</p>
                        )}
                    </div>

                    {/* Lot + Supplier ref */}
                    <div className="grid grid-cols-2 gap-3">
                        <TextField
                            label={<>Lot number <span className="text-om-accent">*</span></>}
                            mono
                            required
                            maxLength={100}
                            value={data.lot_number}
                            onChange={(v) => setData('lot_number', v)}
                            error={errors.lot_number}
                        />
                        <TextField
                            label="Supplier lot ref"
                            mono
                            maxLength={100}
                            value={data.supplier_lot_ref}
                            onChange={(v) => setData('supplier_lot_ref', v)}
                            error={errors.supplier_lot_ref}
                        />
                    </div>

                    {/* Quantity received */}
                    <TextField
                        label="Quantity received"
                        mono
                        type="number"
                        step="0.001"
                        min="0"
                        value={data.quantity_received}
                        onChange={(v) => setData('quantity_received', v)}
                        error={errors.quantity_received}
                    />

                    {/* Inspection plan */}
                    <div>
                        <label className={LABEL_CLASS}>Inspection plan</label>
                        <Dropdown
                            value={data.inspection_plan_id == null ? '' : String(data.inspection_plan_id)}
                            onChange={(v) => setData('inspection_plan_id', v)}
                            placeholder="— no plan (ad-hoc) —"
                            options={plans.map((plan) => ({
                                value: String(plan.id),
                                label: `${plan.name}${plan.material ? ` (${plan.material.name})` : ''}${
                                    plan.material_type ? ` (${plan.material_type.name})` : ''
                                }`,
                            }))}
                            className="w-full"
                        />
                        <p className="text-[12.5px] text-om-muted mt-1">
                            If no plan is selected, you can still record results but no criteria will be pre-filled.
                        </p>
                        {errors.inspection_plan_id && (
                            <p className="text-[11.5px] text-om-blocked mt-1">{errors.inspection_plan_id}</p>
                        )}
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Link
                            href="/inspections"
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            Cancel
                        </Link>
                        <Button type="submit" variant="accent" loading={processing}>
                            Start
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

InspectionsCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
