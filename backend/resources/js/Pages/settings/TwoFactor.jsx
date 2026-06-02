import { useState, useEffect } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

/**
 * Two-Factor Authentication (TOTP) settings.
 *
 * Props (TwoFactorController::enable):
 *   enabled        (bool)
 *   secret         (string)  — setup mode only
 *   qrCodeDataUri  (string)  — setup mode only
 *   recoveryCodes  (string[] | null) — shown once after confirm/regenerate
 */
export default function TwoFactor() {
    const { enabled, secret, qrCodeDataUri, recoveryCodes } = usePage().props;

    // This page always holds sensitive material — the TOTP secret + QR during
    // setup, or recovery codes after confirm. Encrypt its Inertia history state
    // at rest (Inertia v3 history encryption) so the cached page in the browser
    // isn't plaintext. Recovery codes are additionally one-time: clear the
    // history cache so pressing Back refetches the (flash-less) enable route and
    // can't re-show them.
    useEffect(() => {
        if (typeof router.encryptHistory === 'function') {
            router.encryptHistory();
        }
        if (recoveryCodes && recoveryCodes.length > 0 && typeof router.clearHistory === 'function') {
            router.clearHistory();
        }
    }, [recoveryCodes]);

    return (
        <>
            <Head title={__('Two-Factor Authentication')} />
            <div className="p-6 max-w-2xl mx-auto space-y-6">
                <div>
                    <Link href="/settings" className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to Settings')}
                    </Link>
                    <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{__('Two-Factor Authentication')}</h1>
                </div>

                {recoveryCodes && recoveryCodes.length > 0 && (
                    <RecoveryCodes codes={recoveryCodes} />
                )}

                {enabled ? <ManagePanel /> : <SetupPanel secret={secret} qrCodeDataUri={qrCodeDataUri} />}
            </div>
        </>
    );
}

TwoFactor.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ── Recovery codes (one-time display) ───────────────────────────────── */

function RecoveryCodes({ codes }) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard?.writeText(codes.join('\n')).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl p-5">
            <h2 className="text-sm font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wide">{__('Recovery codes')}</h2>
            <p className="text-sm text-amber-700 dark:text-amber-400 mt-1">
                {__("Store these somewhere safe. Each code can be used once if you lose access to your authenticator. They won't be shown again.")}
            </p>
            <div className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                {codes.map((c, i) => (
                    <span key={i} className="bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-800 rounded px-3 py-1.5 text-gray-800 dark:text-gray-100 select-all">
                        {c}
                    </span>
                ))}
            </div>
            <button type="button" onClick={copy} className="mt-3 text-sm text-amber-700 dark:text-amber-400 hover:underline">
                {copied ? __('Copied!') : __('Copy all')}
            </button>
        </div>
    );
}

/* ── Setup (not yet enabled) ─────────────────────────────────────────── */

function SetupPanel({ secret, qrCodeDataUri }) {
    const form = useForm({ code: '' });
    const [copied, setCopied] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        form.post('/settings/two-factor/confirm');
    };

    const copySecret = () => {
        navigator.clipboard?.writeText(secret).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-white">{__('Enable Two-Factor Authentication')}</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {__('Scan the QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator).')}
                </p>
            </div>

            {qrCodeDataUri && (
                <div className="text-center">
                    <img src={qrCodeDataUri} alt={__('2FA QR code')} className="mx-auto rounded-lg border border-gray-200 dark:border-gray-600" />
                </div>
            )}

            <div>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-1">{__("Can't scan? Enter this key manually:")}</p>
                <div className="flex gap-2">
                    <code className="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded px-3 py-2 text-sm font-mono tracking-widest text-gray-800 dark:text-gray-100 select-all break-all">
                        {secret}
                    </code>
                    <button type="button" onClick={copySecret} className="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-200 transition-colors whitespace-nowrap">
                        {copied ? __('Copied!') : __('Copy')}
                    </button>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {__('Enter the 6-digit code to confirm')}
                    </label>
                    <input
                        type="text"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        autoFocus
                        className="form-input w-full text-center text-2xl font-mono tracking-[0.5em]"
                    />
                    {form.errors.code && <p className="mt-1 text-sm text-red-600">{form.errors.code}</p>}
                </div>
                <button type="submit" disabled={form.processing || form.data.code.length !== 6} className="w-full px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                    {form.processing ? __('Verifying…') : __('Enable Two-Factor Authentication')}
                </button>
            </form>
        </div>
    );
}

/* ── Manage (already enabled) ────────────────────────────────────────── */

function ManagePanel() {
    return (
        <div className="space-y-4">
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-3">
                <span className="w-3 h-3 rounded-full bg-green-500" />
                <div>
                    <h2 className="font-bold text-gray-900 dark:text-white">{__('Two-factor authentication is on')}</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">{__('Your account requires an authentication code at login.')}</p>
                </div>
            </div>

            <PasswordActionCard
                title={__('Regenerate recovery codes')}
                description={__('Invalidate your old recovery codes and generate a fresh set.')}
                action="/settings/two-factor/recovery-codes"
                submitLabel={__('Regenerate codes')}
                tone="blue"
            />

            <PasswordActionCard
                title={__('Disable two-factor authentication')}
                description={__("Remove 2FA from your account. You'll only need your password to log in.")}
                action="/settings/two-factor/disable"
                submitLabel={__('Disable 2FA')}
                tone="red"
            />
        </div>
    );
}

function PasswordActionCard({ title, description, action, submitLabel, tone }) {
    const form = useForm({ password: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(action, { onSuccess: () => form.reset('password') });
    };

    const btn = tone === 'red'
        ? 'bg-red-600 hover:bg-red-700'
        : 'bg-blue-600 hover:bg-blue-700';

    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 className="font-semibold text-gray-900 dark:text-white">{title}</h3>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{description}</p>
            <form onSubmit={submit} className="mt-3 flex gap-2 items-start">
                <div className="flex-1">
                    <input
                        type="password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        placeholder={__('Current password')}
                        autoComplete="current-password"
                        className="form-input w-full"
                    />
                    {form.errors.password && <p className="mt-1 text-sm text-red-600">{form.errors.password}</p>}
                </div>
                <button type="submit" disabled={form.processing || !form.data.password} className={`px-5 py-3 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 whitespace-nowrap ${btn}`}>
                    {submitLabel}
                </button>
            </form>
        </div>
    );
}
