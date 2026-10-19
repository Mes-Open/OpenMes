import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, TextField } from '@openmes/ui';
import AuthLayout from '../../layouts/AuthLayout';
import { Hook } from '../../lib/hooks';
import { __ } from '../../lib/i18n';

/**
 * Login page — Inertia render name: auth/Login
 *
 * Props (from AuthController::showLoginForm):
 *   pinEnabled  (bool) — show the Password / Quick PIN tab switcher
 *   regEnabled  (bool) — show "Create account" link
 *   sso         ({name}|null) — show "Sign in with {name}" (single sign-on, OpenID Connect)
 *   hooks       (object) — what modules contributed to `display.auth.login.before`
 *                          (above the form) and `display.auth.login.after` (below it)
 *
 * POST /login   → AuthController::login  (password auth)
 * POST /login/pin → AuthController::loginWithPin  (PIN auth)
 * GET  /auth/sso/redirect → SsoController::redirect (full page load: it leaves for the identity provider)
 *
 * Geist White restyle: light-only v1 — @openmes/ui fields/buttons, om-* tokens.
 */
export default function Login({ pinEnabled = false, regEnabled = false, sso = null, hooks = {} }) {
    const [tab, setTab] = useState('password');

    const passwordForm = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const pinForm = useForm({
        username: '',
        pin: '',
    });

    const submitPassword = (e) => {
        e.preventDefault();
        passwordForm.post('/login');
    };

    const submitPin = (e) => {
        e.preventDefault();
        pinForm.post('/login/pin');
    };

    const switchTab = (t) => {
        setTab(t);
        passwordForm.clearErrors();
        pinForm.clearErrors();
    };

    return (
        <div>
            {/* Whatever an installed module has to say before anybody signs
                in — which installation this is, a notice. Nothing when no
                module listens. */}
            <Hook name="display.auth.login.before" hooks={hooks} />

            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-6 text-center">{__('Sign in')}</h2>

            {/* Single sign-on — a plain link, not an Inertia visit: the browser
                has to leave for the identity provider's own login page. */}
            {sso && (
                <>
                    <a
                        href="/auth/sso/redirect"
                        data-testid="sso-login"
                        className="flex w-full items-center justify-center gap-2 rounded-om-sm border border-om-line bg-om-card px-4 py-2.5 text-sm font-medium text-om-ink transition-colors hover:border-om-faint hover:bg-om-chip"
                    >
                        <svg className="w-4 h-4 text-om-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        {__('Sign in with :name', { name: sso.name })}
                    </a>
                    <div className="my-6 flex items-center gap-3 text-[11px] uppercase tracking-[0.08em] text-om-faint">
                        <span className="h-px flex-1 bg-om-line" />
                        {__('or')}
                        <span className="h-px flex-1 bg-om-line" />
                    </div>
                </>
            )}

            {/* Tab switcher — only when PIN login is enabled */}
            {pinEnabled && (
                <div className="flex rounded-om-sm bg-om-chip p-1 mb-6">
                    <button
                        type="button"
                        data-testid="tab-password"
                        onClick={() => switchTab('password')}
                        className={`flex-1 py-2 text-sm font-medium rounded-[6px] transition-colors ${
                            tab === 'password'
                                ? 'bg-om-card text-om-ink shadow-sm'
                                : 'text-om-muted hover:text-om-ink'
                        }`}
                    >
                        {__('Password')}
                    </button>
                    <button
                        type="button"
                        data-testid="tab-pin"
                        onClick={() => switchTab('pin')}
                        className={`flex-1 py-2 text-sm font-medium rounded-[6px] transition-colors ${
                            tab === 'pin'
                                ? 'bg-om-card text-om-ink shadow-sm'
                                : 'text-om-muted hover:text-om-ink'
                        }`}
                    >
                        {__('Quick PIN')}
                    </button>
                </div>
            )}

            {/* Password login form */}
            {tab === 'password' && (
                <form onSubmit={submitPassword}>
                    <TextField
                        className="mb-4"
                        label={__('Username')}
                        id="username"
                        name="username"
                        value={passwordForm.data.username}
                        onChange={(v) => passwordForm.setData('username', v)}
                        error={passwordForm.errors.username}
                        autoComplete="username"
                        autoFocus
                        required
                    />

                    <TextField
                        className="mb-4"
                        label={__('Password')}
                        type="password"
                        id="password"
                        name="password"
                        value={passwordForm.data.password}
                        onChange={(v) => passwordForm.setData('password', v)}
                        error={passwordForm.errors.password}
                        autoComplete="current-password"
                        required
                    />

                    <div className="mb-6 flex items-center">
                        <Checkbox
                            id="remember"
                            checked={passwordForm.data.remember}
                            onChange={(v) => passwordForm.setData('remember', v)}
                            label={__('Remember me')}
                        />
                    </div>

                    <Button
                        type="submit"
                        variant="accent"
                        className="w-full"
                        loading={passwordForm.processing}
                        disabled={!passwordForm.data.username || !passwordForm.data.password}
                    >
                        {passwordForm.processing ? __('Logging in...') : __('Sign in')}
                    </Button>
                </form>
            )}

            {/* PIN login form */}
            {pinEnabled && tab === 'pin' && (
                <form onSubmit={submitPin}>
                    <TextField
                        className="mb-4"
                        label={__('Username')}
                        id="pin_username"
                        name="username"
                        value={pinForm.data.username}
                        onChange={(v) => pinForm.setData('username', v)}
                        error={pinForm.errors.username}
                        autoComplete="username"
                        required
                    />

                    <div className="mb-6">
                        <label htmlFor="pin_input" className="block mb-[7px] font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                            {__('PIN')}
                        </label>
                        <input
                            type="password"
                            id="pin_input"
                            name="pin"
                            value={pinForm.data.pin}
                            onChange={(e) => pinForm.setData('pin', e.target.value)}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            className={`w-full text-center text-2xl font-mono tracking-[0.5em] text-om-ink placeholder:text-om-faint bg-om-bg rounded-om-sm border px-3 py-2.5 outline-none transition-colors focus:border-om-accent focus:bg-om-card focus:shadow-[0_0_0_3px_rgba(234,90,43,0.12)] ${pinForm.errors.pin ? 'border-om-blocked' : 'border-om-line'}`}
                            autoComplete="off"
                            placeholder="----"
                            required
                        />
                        {pinForm.errors.pin && (
                            <p className="mt-[5px] text-[11.5px] text-om-blocked">{pinForm.errors.pin}</p>
                        )}
                        <p className="mt-[5px] text-[11.5px] text-om-faint">{__('Enter your 4–6 digit PIN')}</p>
                    </div>

                    <Button
                        type="submit"
                        variant="accent"
                        className="w-full"
                        loading={pinForm.processing}
                        disabled={!pinForm.data.username || pinForm.data.pin.length < 4}
                    >
                        {pinForm.processing ? __('Logging in...') : __('Quick PIN Login')}
                    </Button>

                    <p className="mt-4 text-center text-[11.5px] text-om-faint">
                        {__('No PIN yet? Log in with password first, then set your PIN in Settings.')}
                    </p>
                </form>
            )}

            {/* A module's own ways in or back in: single sign-on, a forgotten
                password. Under whichever form is showing. */}
            <Hook name="display.auth.login.after" hooks={hooks} />

            {/* Register link */}
            {regEnabled && (
                <p className="mt-6 text-center text-sm text-om-muted">
                    {__("Don't have an account?")}{' '}
                    <Link href="/register" className="text-om-accent hover:underline font-medium">
                        {__('Create account')}
                    </Link>
                </p>
            )}
        </div>
    );
}

Login.layout = (page) => <AuthLayout>{page}</AuthLayout>;
