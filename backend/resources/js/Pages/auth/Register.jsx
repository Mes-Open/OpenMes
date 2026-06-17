import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, TextField } from '@openmes/ui';
import AuthLayout from '../../layouts/AuthLayout';
import { __ } from '../../lib/i18n';

/**
 * Registration page — Inertia render name: auth/Register
 *
 * Only accessible when allow_registration is enabled (controller aborts 404
 * otherwise). Demo accounts are automatically deleted after 3 hours.
 *
 * POST /register → RegisterController::store
 *
 * Validation errors are surfaced from form.errors.
 *
 * Geist White restyle: light-only v1 — former `dark:` classes removed.
 */
export default function Register() {
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        marketing_consent: false,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/register');
    };

    const isDisabled =
        !form.data.name ||
        !form.data.username ||
        !form.data.email ||
        !form.data.password ||
        !form.data.password_confirmation;

    return (
        <div>
            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-4 text-center">{__('Create account')}</h2>

            {/* Demo notice */}
            <div className="mb-5 flex items-start gap-3 rounded-om-sm border border-om-downtime/30 bg-om-downtime-bg px-4 py-3 text-sm text-om-downtime">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="mt-0.5 h-4 w-4 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>
                    {__('This is a')} <strong>{__('demo account')}</strong> — {__('it will be automatically deleted after')} <strong>{__('3 hours')}</strong>.
                </span>
            </div>

            <form onSubmit={submit}>
                {/* Full Name */}
                <TextField
                    className="mb-4"
                    label={__('Full name')}
                    id="name"
                    name="name"
                    value={form.data.name}
                    onChange={(v) => form.setData('name', v)}
                    error={form.errors.name}
                    autoComplete="name"
                    autoFocus
                    required
                />

                {/* Username */}
                <TextField
                    className="mb-4"
                    label={__('Username')}
                    id="username"
                    name="username"
                    value={form.data.username}
                    onChange={(v) => form.setData('username', v)}
                    error={form.errors.username}
                    autoComplete="username"
                    required
                />

                {/* Email */}
                <TextField
                    className="mb-4"
                    label={__('Email')}
                    type="email"
                    id="email"
                    name="email"
                    value={form.data.email}
                    onChange={(v) => form.setData('email', v)}
                    error={form.errors.email}
                    autoComplete="email"
                    required
                />

                {/* Password */}
                <TextField
                    className="mb-4"
                    label={__('Password')}
                    type="password"
                    id="password"
                    name="password"
                    value={form.data.password}
                    onChange={(v) => form.setData('password', v)}
                    error={form.errors.password}
                    autoComplete="new-password"
                    required
                />

                {/* Confirm Password */}
                <TextField
                    className="mb-4"
                    label={__('Confirm password')}
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    value={form.data.password_confirmation}
                    onChange={(v) => form.setData('password_confirmation', v)}
                    error={form.errors.password_confirmation}
                    autoComplete="new-password"
                    required
                />

                {/* Marketing consent */}
                <div className="mb-6">
                    <label className="flex items-start gap-2 cursor-pointer">
                        <Checkbox
                            checked={form.data.marketing_consent}
                            onChange={(next) => form.setData('marketing_consent', next)}
                            className="mt-1"
                        />
                        <span className="text-xs text-om-muted leading-relaxed">
                            {__('I agree to receive product updates and marketing communications via email.')}{' '}
                            <span className="text-om-faint">
                                / {__('I agree to receive product updates and marketing communications via email.')}
                            </span>
                        </span>
                    </label>
                </div>

                {/* Submit */}
                <Button
                    type="submit"
                    variant="accent"
                    className="w-full"
                    loading={form.processing}
                    disabled={isDisabled}
                >
                    {form.processing ? __('Creating account...') : __('Create account')}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-om-muted">
                {__('Already have an account?')}{' '}
                <Link href="/login" className="text-om-accent hover:underline font-medium">
                    {__('Sign in')}
                </Link>
            </p>
        </div>
    );
}

Register.layout = (page) => <AuthLayout>{page}</AuthLayout>;
