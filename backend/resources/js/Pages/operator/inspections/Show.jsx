import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, ConfirmDialog, InlineAlert } from '@openmes/ui';
import { useState } from 'react';
import { __ } from '../../../lib/i18n';
import OperatorLayout from '../../../layouts/OperatorLayout';
import { InspectionStatus } from './Index';

const DISPOSITION_LABEL = {
    accept: 'Accept',
    accept_with_deviation: 'Accept with deviation',
    rework: 'Rework',
    quarantine: 'Quarantine',
    scrap: 'Scrap',
    return_to_supplier: 'Return to supplier',
    reject: 'Reject',
};

/**
 * One inbound inspection on the operator tablet: record a value per criterion,
 * complete it, and — only for holders of the disposition right — decide on the
 * lot. The server refuses the disposition to everyone else; `can.disposition`
 * just keeps the buttons off their screen.
 */
export default function InspectionShow() {
    const { inspection, dispositions = [], can = {} } = usePage().props;
    const pending = inspection.status === 'pending';
    const decided = inspection.disposition && inspection.disposition !== 'pending';

    const completeForm = useForm({ notes: '' });
    const [confirming, setConfirming] = useState(false);
    const complete = () => {
        setConfirming(false);
        completeForm.post(`/operator/inspections/${inspection.id}/complete`, { preserveScroll: true });
    };

    const dispositionForm = useForm({ disposition: '', notes: '' });
    const decide = (value) => {
        dispositionForm.transform((d) => ({ ...d, disposition: value }));
        dispositionForm.post(`/operator/inspections/${inspection.id}/disposition`, { preserveScroll: true });
    };

    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <Head title={__('Inspection')} />

            <div className="flex items-center gap-3">
                <Link href="/operator/inspections" aria-label={__('Inspections')} className="text-om-muted hover:text-om-ink">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div className="min-w-0 flex-1">
                    <h1 className="text-2xl font-bold text-om-ink truncate">{inspection.material?.name ?? '—'}</h1>
                    <p className="text-sm text-om-muted font-mono">
                        {__('Lot number')}: {inspection.lot_number}
                        {inspection.quantity_received != null && ` · ${__('Quantity received')}: ${Number(inspection.quantity_received)} ${inspection.material?.unit_of_measure ?? ''}`}
                    </p>
                </div>
                <InspectionStatus status={inspection.status} />
            </div>

            <section className="bg-om-card rounded-om-sm shadow-sm">
                <h2 className="px-5 pt-4 pb-2 text-lg font-semibold text-om-ink">{__('Criteria')}</h2>
                {inspection.results.length === 0 ? (
                    <p className="px-5 pb-4 text-sm text-om-faint">{__('Record at least one measurement before completing this inspection.')}</p>
                ) : (
                    <ul className="divide-y divide-om-line2">
                        {inspection.results.map((result) => (
                            <ResultRow key={result.id} inspectionId={inspection.id} result={result} editable={pending && can.perform} />
                        ))}
                    </ul>
                )}
            </section>

            {pending && can.perform && (
                <section className="bg-om-card rounded-om-sm shadow-sm p-5 space-y-3">
                    <textarea
                        aria-label={__('Notes')}
                        placeholder={__('Notes')}
                        maxLength={2000}
                        value={completeForm.data.notes}
                        onChange={(e) => completeForm.setData('notes', e.target.value)}
                        className="form-input w-full"
                        rows={2}
                    />
                    {completeForm.errors.notes && <p className="text-xs text-om-blocked">{completeForm.errors.notes}</p>}
                    <Button variant="primary" size="lg" onClick={() => setConfirming(true)} loading={completeForm.processing} disabled={completeForm.processing}>
                        {__('Complete inspection')}
                    </Button>
                    <ConfirmDialog
                        open={confirming}
                        onClose={() => setConfirming(false)}
                        onConfirm={complete}
                        title={__('Complete inspection')}
                        confirmLabel={__('Complete inspection')}
                        cancelLabel={__('Cancel')}
                        destructive={false}
                    >
                        {__('The overall result is fixed once the inspection is completed. Results can no longer be changed.')}
                    </ConfirmDialog>
                </section>
            )}

            {!pending && !decided && !can.disposition && (
                <InlineAlert severity="info" title={__('Awaiting disposition.')}>
                    {__('A person with the quality disposition permission decides whether this lot is released.')}
                </InlineAlert>
            )}

            {decided && (
                <InlineAlert severity="success" title={`${__('Disposition')}: ${__(DISPOSITION_LABEL[inspection.disposition] ?? inspection.disposition)}`}>
                    {inspection.disposition_notes}
                </InlineAlert>
            )}

            {!pending && can.disposition && (
                <section className="bg-om-card rounded-om-sm shadow-sm p-5 space-y-3">
                    <h2 className="text-lg font-semibold text-om-ink">{__('Disposition')}</h2>
                    <textarea
                        aria-label={__('Disposition notes')}
                        placeholder={__('Disposition notes')}
                        maxLength={2000}
                        value={dispositionForm.data.notes}
                        onChange={(e) => dispositionForm.setData('notes', e.target.value)}
                        className="form-input w-full"
                        rows={2}
                    />
                    {dispositionForm.errors.disposition && <p className="text-xs text-om-blocked">{dispositionForm.errors.disposition}</p>}
                    <div className="flex flex-wrap gap-2">
                        {dispositions.map((value) => (
                            <Button
                                key={value}
                                size="lg"
                                variant={value.startsWith('accept') ? 'success' : ['scrap', 'reject', 'return_to_supplier'].includes(value) ? 'danger' : 'secondary'}
                                disabled={dispositionForm.processing}
                                onClick={() => decide(value)}
                            >
                                {__(DISPOSITION_LABEL[value] ?? value)}
                            </Button>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

function ResultRow({ inspectionId, result, editable }) {
    const measurement = result.criterion_type === 'measurement';
    const form = useForm({
        value_numeric: result.value_numeric ?? '',
        value_boolean: result.value_boolean,
    });

    const save = (payload) => {
        router.patch(`/operator/inspections/${inspectionId}/results/${result.id}`, payload, {
            preserveScroll: true,
            onError: (errors) => form.setError(errors),
        });
    };

    const spec = measurement && (result.spec_min != null || result.spec_max != null)
        ? `${result.spec_min ?? '…'} – ${result.spec_max ?? '…'} ${result.unit ?? ''}`
        : null;

    return (
        <li className="flex flex-wrap items-center gap-3 px-5 py-4">
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-om-ink">
                    {result.criterion_name}
                    {result.required && <span className="text-om-blocked"> *</span>}
                </p>
                {spec && <p className="text-xs text-om-muted font-mono">{__('Spec')}: {spec}</p>}
                {form.errors.value_numeric && <p className="text-xs text-om-blocked">{form.errors.value_numeric}</p>}
            </div>

            {measurement ? (
                <div className="flex items-center gap-2">
                    <input
                        aria-label={result.criterion_name}
                        type="number" step="any" inputMode="decimal"
                        disabled={!editable}
                        value={form.data.value_numeric}
                        onChange={(e) => form.setData('value_numeric', e.target.value)}
                        className="form-input w-32 py-3 text-right tabular-nums"
                    />
                    {editable && (
                        <Button variant="secondary" size="lg" disabled={form.data.value_numeric === ''} onClick={() => save({ value_numeric: form.data.value_numeric })}>
                            {__('Record result')}
                        </Button>
                    )}
                </div>
            ) : editable ? (
                <div className="flex gap-2">
                    <Button variant={result.value_boolean === true ? 'success' : 'outline'} size="lg" onClick={() => save({ value_boolean: true })}>
                        {__('Pass')}
                    </Button>
                    <Button variant={result.value_boolean === false ? 'danger' : 'outline'} size="lg" onClick={() => save({ value_boolean: false })}>
                        {__('Fail')}
                    </Button>
                </div>
            ) : null}

            <ResultBadge passed={result.is_passed} />
        </li>
    );
}

function ResultBadge({ passed }) {
    if (passed === null || passed === undefined) {
        return <span className="w-14 text-center text-xs text-om-faint">—</span>;
    }
    return (
        <span className={`w-14 text-center text-xs font-semibold uppercase ${passed ? 'text-om-running' : 'text-om-blocked'}`}>
            {passed ? __('Pass') : __('Fail')}
        </span>
    );
}

InspectionShow.layout = (page) => <OperatorLayout>{page}</OperatorLayout>;
