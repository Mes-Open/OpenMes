import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import AuthLayout from '../../layouts/AuthLayout';
import { __ } from '../../lib/i18n';

/**
 * Mid-login 2FA challenge — Inertia render name: auth/TwoFactorChallenge.
 * POST /2fa/challenge (two-factor.verify) with either `code` (TOTP) or
 * `recovery_code`. On success the controller logs the user in and redirects.
 *
 * Geist White restyle: light-only v1 — former `dark:` classes removed.
 * Inputs stay hand-rolled (centered oversized code entry).
 */
export default function TwoFactorChallenge() {
    const [mode, setMode] = useState('code'); // 'code' | 'recovery'
    const form = useForm({ code: '', recovery_code: '' });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => (mode === 'code'
            ? { code: data.code }
            : { recovery_code: data.recovery_code }))
            .post('/2fa/challenge');
    };

    // §04 input idiom, kept hand-rolled for the centered code styling.
    const fieldBase =
        'w-full text-center font-mono text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border border-om-line outline-none transition-colors focus:border-om-accent focus:bg-om-card focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)]';

    return (
        <>
            <Head title={__('Two-Factor Authentication')} />

            <div className="w-full">
                <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2 text-center">
                    {__('Two-Factor Authentication')}
                </h2>
                <p className="text-om-muted text-sm text-center mb-6">
                    {mode === 'code'
                        ? __('Enter the 6-digit code from your authenticator app.')
                        : __('Enter one of your recovery codes.')}
                </p>

                <form onSubmit={submit} className="space-y-4">
                    {mode === 'code' ? (
                        <div>
                            <input
                                type="text"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                autoFocus
                                className={`${fieldBase} text-3xl tracking-[0.5em] py-4`}
                            />
                            {form.errors.code && <p className="text-om-blocked text-[11.5px] mt-2 text-center">{form.errors.code}</p>}
                        </div>
                    ) : (
                        <div>
                            <input
                                type="text"
                                value={form.data.recovery_code}
                                onChange={(e) => form.setData('recovery_code', e.target.value)}
                                placeholder={__('Recovery code')}
                                autoFocus
                                className={`${fieldBase} text-lg py-3`}
                            />
                            {form.errors.recovery_code && <p className="text-om-blocked text-[11.5px] mt-2 text-center">{form.errors.recovery_code}</p>}
                        </div>
                    )}

                    <Button
                        type="submit"
                        variant="accent"
                        className="w-full"
                        loading={form.processing}
                        disabled={mode === 'code' ? form.data.code.length !== 6 : !form.data.recovery_code}
                    >
                        {form.processing ? __('Verifying…') : __('Verify')}
                    </Button>

                    <button
                        type="button"
                        onClick={() => {
                            setMode((m) => (m === 'code' ? 'recovery' : 'code'));
                            form.clearErrors();
                        }}
                        className="w-full text-sm text-om-muted hover:text-om-ink"
                    >
                        {mode === 'code' ? __('Use a recovery code instead') : __('Use an authenticator code instead')}
                    </button>
                </form>
            </div>
        </>
    );
}

TwoFactorChallenge.layout = (page) => <AuthLayout>{page}</AuthLayout>;
