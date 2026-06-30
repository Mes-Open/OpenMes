import { useState, useEffect } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

/**
 * Two-Factor Authentication (TOTP) settings.
 * Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
 *
 * Props (TwoFactorController::enable):
 *   enabled        (bool)
 *   secret         (string)  — setup mode only
 *   qrCodeDataUri  (string)  — setup mode only
 *   recoveryCodes  (string[] | null) — shown once after confirm/regenerate
 */

const INPUT_CLASS =
    'w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

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
                    <Link href="/settings" className="text-[13px] text-om-muted hover:text-om-ink flex items-center gap-1 transition-colors">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        {__('Back to Settings')}
                    </Link>
                    <h1 className="mt-3 text-[22px] font-semibold tracking-[-0.02em] text-om-ink">{__('Two-Factor Authentication')}</h1>
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
        <div className="bg-om-downtime-bg border border-om-line rounded-om p-5">
            <h2 className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-ink font-semibold">{__('Recovery codes')}</h2>
            <p className="text-[12.5px] text-om-muted mt-1">
                {__("Store these somewhere safe. Each code can be used once if you lose access to your authenticator. They won't be shown again.")}
            </p>
            <div className="mt-3 grid grid-cols-2 gap-2">
                {codes.map((c, i) => (
                    <span key={i} className="bg-om-card border border-om-line rounded-om-sm px-3 py-1.5 font-mono text-[13px] text-om-ink select-all">
                        {c}
                    </span>
                ))}
            </div>
            <button type="button" onClick={copy} className="mt-3 text-[12.5px] text-om-accent hover:underline">
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
        <div className="bg-om-card border border-om-line rounded-om p-6 space-y-4">
            <div>
                <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">{__('Enable Two-Factor Authentication')}</h2>
                <p className="text-[12.5px] text-om-muted mt-1">
                    {__('Scan the QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator).')}
                </p>
            </div>

            {qrCodeDataUri && (
                <div className="text-center">
                    <img src={qrCodeDataUri} alt={__('2FA QR code')} className="mx-auto rounded-om-sm border border-om-line" />
                </div>
            )}

            <div>
                <p className="text-[12.5px] text-om-muted mb-1">{__("Can't scan? Enter this key manually:")}</p>
                <div className="flex gap-2">
                    <code className="flex-1 bg-om-chip border border-om-line rounded-om-sm px-3 py-2 font-mono text-[13px] tracking-widest text-om-ink select-all break-all">
                        {secret}
                    </code>
                    <Button type="button" variant="secondary" onClick={copySecret} className="whitespace-nowrap">
                        {copied ? __('Copied!') : __('Copy')}
                    </Button>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-3 pt-2 border-t border-om-line">
                <div>
                    <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">
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
                        className={`${INPUT_CLASS} text-center text-2xl font-mono tracking-[0.5em]`}
                    />
                    {form.errors.code && <p className="mt-1 text-[11.5px] text-om-blocked">{form.errors.code}</p>}
                </div>
                <Button type="submit" variant="accent" className="w-full" disabled={form.data.code.length !== 6} loading={form.processing}>
                    {form.processing ? __('Verifying…') : __('Enable Two-Factor Authentication')}
                </Button>
            </form>
        </div>
    );
}

/* ── Manage (already enabled) ────────────────────────────────────────── */

function ManagePanel() {
    return (
        <div className="space-y-4">
            <div className="bg-om-card border border-om-line rounded-om p-5 flex items-center gap-3">
                <span className="w-3 h-3 rounded-full bg-om-running" />
                <div>
                    <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">{__('Two-factor authentication is on')}</h2>
                    <p className="text-[12.5px] text-om-muted">{__('Your account requires an authentication code at login.')}</p>
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

    return (
        <div className="bg-om-card border border-om-line rounded-om p-5">
            <h3 className="text-[15px] font-semibold tracking-[-0.01em] text-om-ink">{title}</h3>
            <p className="text-[12.5px] text-om-muted mt-1">{description}</p>
            <form onSubmit={submit} className="mt-3 flex gap-2 items-start">
                <div className="flex-1">
                    <input
                        type="password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        placeholder={__('Current password')}
                        autoComplete="current-password"
                        className={INPUT_CLASS}
                    />
                    {form.errors.password && <p className="mt-1 text-[11.5px] text-om-blocked">{form.errors.password}</p>}
                </div>
                <Button
                    type="submit"
                    variant={tone === 'red' ? 'danger' : 'primary'}
                    disabled={!form.data.password}
                    loading={form.processing}
                    className="whitespace-nowrap"
                >
                    {submitLabel}
                </Button>
            </form>
        </div>
    );
}
