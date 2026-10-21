import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown, StatusPill } from '@openmes/ui';
import { __ } from '../../../lib/i18n';
import OperatorLayout from '../../../layouts/OperatorLayout';

/**
 * Inbound inspections on the operator tablet — reachable by holders of the
 * "Quality inspector" / "Quality disposition" permissions (the server enforces
 * it; `can` only shapes what is offered). Start an inspection, then pick up
 * pending ones or, with the disposition right, decide on completed ones.
 */
export default function InspectionsIndex() {
    const { pending = [], awaitingDisposition = [], materials = [], can = {} } = usePage().props;

    const form = useForm({
        material_id: '',
        lot_number: '',
        quantity_received: '',
        supplier_lot_ref: '',
        source_container_no: '',
    });
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/inspections');
    };

    return (
        <div className="max-w-5xl mx-auto space-y-6">
            <Head title={__('Inspections')} />
            <h1 className="text-2xl font-bold text-om-ink">{__('Inspections')}</h1>

            {can.perform && (
                <form onSubmit={submit} className="bg-om-card rounded-om-sm shadow-sm p-5 space-y-4">
                    <h2 className="text-lg font-semibold text-om-ink">{__('Start inspection')}</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label={__('Material')} error={errors.material_id} required>
                            <Dropdown
                                aria-label={__('Material')}
                                options={materials.map((m) => ({ value: String(m.id), label: `${m.code} — ${m.name}` }))}
                                value={data.material_id}
                                onChange={(v) => setData('material_id', v)}
                                placeholder={__('— Select material —')}
                                className="w-full"
                            />
                        </Field>
                        <Field label={__('Lot number')} error={errors.lot_number} required>
                            <input
                                aria-label={__('Lot number')}
                                type="text" required maxLength={100}
                                value={data.lot_number}
                                onChange={(e) => setData('lot_number', e.target.value)}
                                className="form-input w-full py-3"
                            />
                        </Field>
                        <Field label={__('Quantity received')} error={errors.quantity_received}>
                            <input
                                aria-label={__('Quantity received')}
                                type="number" min="0" step="any" inputMode="decimal"
                                value={data.quantity_received}
                                onChange={(e) => setData('quantity_received', e.target.value)}
                                className="form-input w-full py-3 tabular-nums"
                            />
                        </Field>
                        <Field label={__('Supplier lot ref')} error={errors.supplier_lot_ref}>
                            <input
                                aria-label={__('Supplier lot ref')}
                                type="text" maxLength={100}
                                value={data.supplier_lot_ref}
                                onChange={(e) => setData('supplier_lot_ref', e.target.value)}
                                className="form-input w-full py-3"
                            />
                        </Field>
                        <Field label={__('Container no.')} error={errors.source_container_no}>
                            <input
                                aria-label={__('Container no.')}
                                type="text" maxLength={100}
                                value={data.source_container_no}
                                onChange={(e) => setData('source_container_no', e.target.value)}
                                className="form-input w-full py-3"
                            />
                        </Field>
                    </div>
                    <Button type="submit" variant="primary" loading={processing} disabled={processing || !data.material_id || !data.lot_number.trim()}>
                        {__('Start inspection')}
                    </Button>
                </form>
            )}

            <InspectionList title={__('Pending inspections')} rows={pending} />
            <InspectionList title={__('Awaiting disposition')} rows={awaitingDisposition} />
        </div>
    );
}

const STATUS_PILL = { pending: 'pending', pass: 'done', conditional_pass: 'downtime', fail: 'blocked' };
const STATUS_LABEL = { pending: 'Pending', pass: 'Pass', conditional_pass: 'Conditional pass', fail: 'Fail' };

export function InspectionStatus({ status }) {
    return <StatusPill status={STATUS_PILL[status] ?? 'pending'} label={__(STATUS_LABEL[status] ?? status)} pulse={false} />;
}

function InspectionList({ title, rows }) {
    return (
        <section className="bg-om-card rounded-om-sm shadow-sm">
            <h2 className="px-5 pt-4 pb-2 text-lg font-semibold text-om-ink">{title}</h2>
            {rows.length === 0 ? (
                <p className="px-5 pb-4 text-sm text-om-faint">{__('No inspections.')}</p>
            ) : (
                <ul className="divide-y divide-om-line2">
                    {rows.map((row) => (
                        <li key={row.id}>
                            <Link href={`/operator/inspections/${row.id}`} className="flex items-center gap-4 px-5 py-4 hover:bg-om-chip">
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-om-ink truncate">{row.material?.name ?? '—'}</p>
                                    <p className="text-sm text-om-muted font-mono truncate">{row.lot_number}</p>
                                </div>
                                <span className="text-sm text-om-muted hidden md:block">{row.inspector?.name}</span>
                                <InspectionStatus status={row.status} />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Field({ label, error, required, children }) {
    return (
        <div>
            <div className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </div>
            {children}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}

InspectionsIndex.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
