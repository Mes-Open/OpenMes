// Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const LABEL_CLASS = 'block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]';
const INPUT_CLASS =
    'w-full bg-om-bg border rounded-om-sm px-3 py-2.5 pr-12 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]';

function EyeIcon({ visible }) {
    if (visible) {
        return (
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
        );
    }
    return (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    );
}

function PasswordField({ id, label, value, onChange, error, hint, autoComplete, minLength }) {
    const [show, setShow] = useState(false);
    return (
        <div className="mb-6">
            <label htmlFor={id} className={LABEL_CLASS}>{label}</label>
            <div className="relative">
                <input
                    type={show ? 'text' : 'password'}
                    id={id}
                    value={value}
                    onChange={onChange}
                    className={`${INPUT_CLASS} ${error ? 'border-om-blocked' : 'border-om-line'}`}
                    required
                    autoComplete={autoComplete}
                    minLength={minLength}
                />
                <button
                    type="button"
                    onClick={() => setShow((s) => !s)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-om-faint hover:text-om-ink transition-colors"
                >
                    <EyeIcon visible={show} />
                </button>
            </div>
            {hint && <p className="text-[12.5px] text-om-muted mt-1">{hint}</p>}
            {error && <p className="text-[11.5px] text-om-blocked mt-1">{error}</p>}
        </div>
    );
}

export default function ChangePassword() {
    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const canSubmit =
        data.current_password &&
        data.password &&
        data.password_confirmation &&
        data.password === data.password_confirmation;

    function handleSubmit(e) {
        e.preventDefault();
        post('/settings/change-password');
    }

    let confirmHint = __('Re-enter your password');
    if (data.password_confirmation && data.password !== data.password_confirmation) {
        confirmHint = null; // shown as error-style below
    } else if (data.password && data.password_confirmation && data.password === data.password_confirmation) {
        confirmHint = null; // shown as success below
    }

    return (
        <div className="max-w-2xl mx-auto">
            <Head title={__('Change Password')} />

            <div className="mb-6">
                <Link href="/settings" className="text-[13px] text-om-muted hover:text-om-ink flex items-center gap-2 mb-4 transition-colors">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {__('Back')}
                </Link>
                <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__('Change Password')}</h1>
            </div>

            <div className="bg-om-card border border-om-line rounded-om p-6">
                <form onSubmit={handleSubmit}>
                    <PasswordField
                        id="current_password"
                        label={__('Current Password')}
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        error={errors.current_password}
                        autoComplete="current-password"
                    />

                    <PasswordField
                        id="password"
                        label={__('New Password')}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        hint={__('Minimum 8 characters')}
                        autoComplete="new-password"
                        minLength={8}
                    />

                    <div className="mb-6">
                        <label htmlFor="password_confirmation" className={LABEL_CLASS}>{__('Confirm New Password')}</label>
                        <div className="relative">
                            {/* PasswordField not reused here so we can place the dynamic hint outside */}
                            <ConfirmField
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                        </div>
                        {!data.password_confirmation && (
                            <p className="text-[12.5px] text-om-muted mt-1">{__('Re-enter your password')}</p>
                        )}
                        {data.password_confirmation && data.password !== data.password_confirmation && (
                            <p className="text-[11.5px] text-om-blocked mt-1">{__('Passwords do not match')}</p>
                        )}
                        {data.password && data.password_confirmation && data.password === data.password_confirmation && (
                            <p className="text-[12.5px] text-om-running mt-1">{__('Passwords match')}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <Link
                            href="/settings"
                            className="inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
                        >
                            {__('Cancel')}
                        </Link>
                        <Button
                            type="submit"
                            variant="accent"
                            disabled={!canSubmit}
                            loading={processing}
                        >
                            {__('Change Password')}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ConfirmField({ value, onChange }) {
    const [show, setShow] = useState(false);
    return (
        <div className="relative">
            <input
                type={show ? 'text' : 'password'}
                id="password_confirmation"
                value={value}
                onChange={onChange}
                className={`${INPUT_CLASS} border-om-line`}
                required
                autoComplete="new-password"
                minLength={8}
            />
            <button
                type="button"
                onClick={() => setShow((s) => !s)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-om-faint hover:text-om-ink transition-colors"
            >
                <EyeIcon visible={show} />
            </button>
        </div>
    );
}

ChangePassword.layout = (page) => <AppLayout>{page}</AppLayout>;
