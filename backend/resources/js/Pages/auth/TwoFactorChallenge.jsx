import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '../../layouts/AuthLayout';

/**
 * Mid-login 2FA challenge — Inertia render name: auth/TwoFactorChallenge.
 * POST /2fa/challenge (two-factor.verify) with either `code` (TOTP) or
 * `recovery_code`. On success the controller logs the user in and redirects.
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

    return (
        <>
            <Head title="Two-Factor Authentication" />

            <div className="w-full">
                <h2 className="text-2xl font-bold text-gray-800 dark:text-white mb-2 text-center">
                    Two-Factor Authentication
                </h2>
                <p className="text-gray-500 dark:text-gray-400 text-sm text-center mb-6">
                    {mode === 'code'
                        ? 'Enter the 6-digit code from your authenticator app.'
                        : 'Enter one of your recovery codes.'}
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
                                className="form-input w-full text-center text-3xl font-mono tracking-[0.5em] py-4"
                            />
                            {form.errors.code && <p className="text-red-600 text-sm mt-2 text-center">{form.errors.code}</p>}
                        </div>
                    ) : (
                        <div>
                            <input
                                type="text"
                                value={form.data.recovery_code}
                                onChange={(e) => form.setData('recovery_code', e.target.value)}
                                placeholder="Recovery code"
                                autoFocus
                                className="form-input w-full text-center text-lg font-mono py-3"
                            />
                            {form.errors.recovery_code && <p className="text-red-600 text-sm mt-2 text-center">{form.errors.recovery_code}</p>}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={form.processing || (mode === 'code' ? form.data.code.length !== 6 : !form.data.recovery_code)}
                        className="w-full px-5 py-3 bg-blue-600 text-white text-base font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                    >
                        {form.processing ? 'Verifying…' : 'Verify'}
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setMode((m) => (m === 'code' ? 'recovery' : 'code'));
                            form.clearErrors();
                        }}
                        className="w-full text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    >
                        {mode === 'code' ? 'Use a recovery code instead' : 'Use an authenticator code instead'}
                    </button>
                </form>
            </div>
        </>
    );
}

TwoFactorChallenge.layout = (page) => <AuthLayout>{page}</AuthLayout>;
