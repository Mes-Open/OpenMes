import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Dropdown } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

/**
 * Shop-floor "Move Pallet" terminal (#103). A logistics operator identifies
 * themselves (badge-style tap), picks a pallet, enters its new location, and
 * confirms — recording an attributable physical movement. Optimised for a
 * tablet: large tap targets, minimal typing.
 */
export default function MovePallet() {
    const { operators = [], pallets = [] } = usePage().props;

    const form = useForm({
        worker_id: '',
        pallet_id: '',
        to_location: '',
        notes: '',
    });
    const { data, setData, errors, processing } = form;

    const selectedPallet = pallets.find((p) => String(p.id) === String(data.pallet_id));

    const submit = (e) => {
        e.preventDefault();
        form.post('/logistics/movements', {
            preserveScroll: true,
            onSuccess: () => form.reset('pallet_id', 'to_location', 'notes'),
        });
    };

    return (
        <div className="max-w-3xl mx-auto">
            <Head title={__('Move Pallet')} />

            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-1">{__('Move Pallet')}</h1>
            <p className="text-om-muted text-[13px] mb-6">{__('Record a physical pallet movement.')}</p>

            <form onSubmit={submit} className="bg-om-card border border-om-line rounded-om p-6 space-y-7">
                {/* Operator — badge-style picker */}
                <div>
                    <label className="block text-sm font-semibold text-om-ink mb-2">
                        {__('Operator')} <span className="text-om-blocked">*</span>
                    </label>
                    {operators.length === 0 ? (
                        <p className="text-[13px] text-om-muted">
                            {__('No logistics operators configured. Mark a worker as a logistics operator first.')}
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            {operators.map((op) => {
                                const active = String(data.worker_id) === String(op.id);
                                return (
                                    <button
                                        type="button"
                                        key={op.id}
                                        onClick={() => setData('worker_id', op.id)}
                                        className={`rounded-om-sm border px-3 py-3 text-left transition-colors ${
                                            active
                                                ? 'bg-om-ink text-om-on-ink border-om-ink'
                                                : 'bg-om-card text-om-ink border-om-line hover:bg-om-chip'
                                        }`}
                                    >
                                        <span className="block font-mono text-[11px] opacity-70">{op.code}</span>
                                        <span className="block text-[14px] font-medium leading-tight">{op.name}</span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                    {errors.worker_id && <p className="mt-1 text-xs text-om-blocked">{errors.worker_id}</p>}
                </div>

                {/* Pallet */}
                <div>
                    <label className="block text-sm font-semibold text-om-ink mb-2">
                        {__('Pallet')} <span className="text-om-blocked">*</span>
                    </label>
                    <Dropdown
                        className="w-full"
                        value={data.pallet_id == null ? '' : String(data.pallet_id)}
                        onChange={(v) => setData('pallet_id', v)}
                        placeholder={__('— Select pallet —')}
                        options={pallets.map((p) => ({
                            value: String(p.id),
                            label: p.location ? `${p.pallet_no} · ${p.location}` : p.pallet_no,
                        }))}
                    />
                    {selectedPallet && (
                        <p className="mt-1 text-[12px] text-om-muted">
                            {__('Current location')}: <span className="font-mono">{selectedPallet.location || '—'}</span>
                        </p>
                    )}
                    {errors.pallet_id && <p className="mt-1 text-xs text-om-blocked">{errors.pallet_id}</p>}
                </div>

                {/* New location */}
                <div>
                    <label className="block text-sm font-semibold text-om-ink mb-2">
                        {__('New location')} <span className="text-om-blocked">*</span>
                    </label>
                    <input
                        type="text"
                        name="to_location"
                        value={data.to_location}
                        onChange={(e) => setData('to_location', e.target.value)}
                        placeholder={__('e.g. B-07-02')}
                        className="w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-3 text-[16px] text-om-ink outline-hidden focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]"
                    />
                    {errors.to_location && <p className="mt-1 text-xs text-om-blocked">{errors.to_location}</p>}
                </div>

                {/* Notes (optional) */}
                <div>
                    <label className="block text-sm font-semibold text-om-ink mb-2">{__('Notes')}</label>
                    <textarea
                        name="notes"
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className="w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[14px] text-om-ink outline-hidden focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]"
                    />
                    {errors.notes && <p className="mt-1 text-xs text-om-blocked">{errors.notes}</p>}
                </div>

                <Button type="submit" variant="primary" loading={processing} disabled={processing} className="w-full py-3 text-[15px]">
                    {processing ? __('Saving…') : __('Confirm move')}
                </Button>
            </form>
        </div>
    );
}

MovePallet.layout = (page) => <AppLayout>{page}</AppLayout>;
