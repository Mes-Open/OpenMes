// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button, InlineAlert, TextField } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const PIN_INPUT_CLASS =
    'w-full text-center text-2xl font-mono tracking-[0.5em] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

export default function Pin() {
    const { hasPin, csrf_token } = usePage().props;

    // Set/Change PIN form
    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        pin: '',
        pin_confirmation: '',
    });

    // Remove PIN form — minimal, uses a bare fetch/form
    const [showRemove, setShowRemove] = useState(false);
    const {
        data: removeData,
        setData: setRemoveData,
        delete: destroyPin,
        processing: removeProcessing,
        errors: removeErrors,
    } = useForm({ current_password: '' });

    const pinValid = data.pin.length >= 4 && data.pin === data.pin_confirmation;

    function handleSetPin(e) {
        e.preventDefault();
        post('/settings/pin');
    }

    function handleRemovePin(e) {
        e.preventDefault();
        destroyPin('/settings/pin');
    }

    return (
        <div className="max-w-lg mx-auto">
            <Head title={__('PIN Setup')} />

            <div className="flex items-center gap-3 mb-6">
                <Link href="/settings" className="text-om-muted hover:text-om-ink transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.02em] text-om-ink">{__('Quick PIN Login')}</h1>
                    <p className="text-om-muted text-[12.5px] mt-0.5">{__('Set a 4–6 digit PIN for fast sign-in')}</p>
                </div>
            </div>

            {/* PIN active status */}
            {hasPin && (
                <InlineAlert severity="success" title={__('PIN is active')} className="mb-6">
                    {__('You can log in using your username and PIN.')}
                </InlineAlert>
            )}

            {/* Remove PIN section */}
            {hasPin && (
                <div className="bg-om-card border border-om-line rounded-om p-6 mb-6">
                    <button
                        type="button"
                        onClick={() => setShowRemove((v) => !v)}
                        className="text-[12.5px] text-om-blocked hover:underline font-medium"
                    >
                        {__('Remove PIN')}
                    </button>
                    {showRemove && (
                        <form onSubmit={handleRemovePin} className="mt-4 space-y-4">
                            <TextField
                                label={__('Confirm your password')}
                                type="password"
                                id="rm_password"
                                value={removeData.current_password}
                                onChange={(v) => setRemoveData('current_password', v)}
                                error={removeErrors.current_password}
                                required
                            />
                            <Button type="submit" variant="danger" loading={removeProcessing}>
                                Remove PIN
                            </Button>
                        </form>
                    )}
                </div>
            )}

            {/* Set / Change PIN form */}
            <div className="bg-om-card border border-om-line rounded-om p-6">
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink mb-2 pb-3 border-b border-om-line">
                    {hasPin ? __('Change PIN') : __('Set PIN')}
                </h2>
                <p className="text-om-muted text-[12.5px] mb-4">
                    {__('Enter your current account password and choose a 4–6 digit numeric PIN.')}
                </p>

                <form onSubmit={handleSetPin} className="space-y-4">
                    <TextField
                        label={__('Current Password')}
                        type="password"
                        id="current_password"
                        value={data.current_password}
                        onChange={(v) => setData('current_password', v)}
                        error={errors.current_password}
                        required
                        autoComplete="current-password"
                    />

                    <div>
                        <label htmlFor="pin" className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">{__('PIN (4–6 digits)')}</label>
                        <input
                            type="password"
                            id="pin"
                            value={data.pin}
                            onChange={(e) => setData('pin', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            className={`${PIN_INPUT_CLASS} ${errors.pin ? 'border-om-blocked' : 'border-om-line'}`}
                            required
                            placeholder="----"
                        />
                        {errors.pin && <p className="mt-1 text-[11.5px] text-om-blocked">{errors.pin}</p>}
                    </div>

                    <div>
                        <label htmlFor="pin_confirmation" className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">{__('Confirm PIN')}</label>
                        <input
                            type="password"
                            id="pin_confirmation"
                            value={data.pin_confirmation}
                            onChange={(e) => setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            className={`${PIN_INPUT_CLASS} border-om-line`}
                            required
                            placeholder="----"
                        />
                    </div>

                    <div className="pt-2">
                        <Button
                            type="submit"
                            variant="accent"
                            className="w-full"
                            disabled={!pinValid}
                            loading={processing}
                        >
                            {hasPin ? __('Change PIN') : __('Set PIN')}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

Pin.layout = (page) => <AppLayout>{page}</AppLayout>;
