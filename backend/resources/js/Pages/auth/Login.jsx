import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../layouts/AuthLayout';
import { __ } from '../../lib/i18n';

/**
 * Login page — Inertia render name: auth/Login
 *
 * Props (from AuthController::showLoginForm):
 *   pinEnabled  (bool) — show the Password / Quick PIN tab switcher
 *   regEnabled  (bool) — show "Create account" link
 *
 * POST /login   → AuthController::login  (password auth)
 * POST /login/pin → AuthController::loginWithPin  (PIN auth)
 */
export default function Login({ pinEnabled = false, regEnabled = false }) {
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
            <h2 className="text-2xl font-bold text-gray-800 mb-6 text-center">{__('Sign in')}</h2>

            {/* Tab switcher — only when PIN login is enabled */}
            {pinEnabled && (
                <div className="flex rounded-lg bg-gray-100 p-1 mb-6">
                    <button
                        type="button"
                        data-testid="tab-password"
                        onClick={() => switchTab('password')}
                        className={`flex-1 py-2 text-sm font-medium rounded-md transition-colors ${
                            tab === 'password'
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {__('Password')}
                    </button>
                    <button
                        type="button"
                        data-testid="tab-pin"
                        onClick={() => switchTab('pin')}
                        className={`flex-1 py-2 text-sm font-medium rounded-md transition-colors ${
                            tab === 'pin'
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {__('Quick PIN')}
                    </button>
                </div>
            )}

            {/* Password login form */}
            {tab === 'password' && (
                <form onSubmit={submitPassword}>
                    <div className="mb-4">
                        <label htmlFor="username" className="form-label">
                            {__('Username')}
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value={passwordForm.data.username}
                            onChange={(e) => passwordForm.setData('username', e.target.value)}
                            className={`form-input w-full${passwordForm.errors.username ? ' border-red-500' : ''}`}
                            autoComplete="username"
                            autoFocus
                            required
                        />
                        {passwordForm.errors.username && (
                            <p className="mt-1 text-sm text-red-600">{passwordForm.errors.username}</p>
                        )}
                    </div>

                    <div className="mb-4">
                        <label htmlFor="password" className="form-label">
                            {__('Password')}
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            value={passwordForm.data.password}
                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                            className={`form-input w-full${passwordForm.errors.password ? ' border-red-500' : ''}`}
                            autoComplete="current-password"
                            required
                        />
                        {passwordForm.errors.password && (
                            <p className="mt-1 text-sm text-red-600">{passwordForm.errors.password}</p>
                        )}
                    </div>

                    <div className="mb-6 flex items-center">
                        <input
                            type="checkbox"
                            id="remember"
                            checked={passwordForm.data.remember}
                            onChange={(e) => passwordForm.setData('remember', e.target.checked)}
                            className="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        />
                        <label htmlFor="remember" className="ml-2 text-sm text-gray-700">
                            {__('Remember me')}
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={passwordForm.processing || !passwordForm.data.username || !passwordForm.data.password}
                        className={`w-full btn-touch btn-primary${
                            passwordForm.processing || !passwordForm.data.username || !passwordForm.data.password
                                ? ' opacity-50 cursor-not-allowed'
                                : ''
                        }`}
                    >
                        {passwordForm.processing ? (
                            <span className="flex items-center justify-center">
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {__('Logging in...')}
                            </span>
                        ) : (
                            __('Sign in')
                        )}
                    </button>
                </form>
            )}

            {/* PIN login form */}
            {pinEnabled && tab === 'pin' && (
                <form onSubmit={submitPin}>
                    <div className="mb-4">
                        <label htmlFor="pin_username" className="form-label">
                            {__('Username')}
                        </label>
                        <input
                            type="text"
                            id="pin_username"
                            name="username"
                            value={pinForm.data.username}
                            onChange={(e) => pinForm.setData('username', e.target.value)}
                            className={`form-input w-full${pinForm.errors.username ? ' border-red-500' : ''}`}
                            autoComplete="username"
                            required
                        />
                        {pinForm.errors.username && (
                            <p className="mt-1 text-sm text-red-600">{pinForm.errors.username}</p>
                        )}
                    </div>

                    <div className="mb-6">
                        <label htmlFor="pin_input" className="form-label">
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
                            className={`form-input w-full text-center text-2xl tracking-[0.5em]${pinForm.errors.pin ? ' border-red-500' : ''}`}
                            autoComplete="off"
                            placeholder="----"
                            required
                        />
                        {pinForm.errors.pin && (
                            <p className="mt-1 text-sm text-red-600">{pinForm.errors.pin}</p>
                        )}
                        <p className="mt-1 text-xs text-gray-500">{__('Enter your 4–6 digit PIN')}</p>
                    </div>

                    <button
                        type="submit"
                        disabled={pinForm.processing || !pinForm.data.username || pinForm.data.pin.length < 4}
                        className={`w-full btn-touch btn-primary${
                            pinForm.processing || !pinForm.data.username || pinForm.data.pin.length < 4
                                ? ' opacity-50 cursor-not-allowed'
                                : ''
                        }`}
                    >
                        {pinForm.processing ? (
                            <span className="flex items-center justify-center">
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {__('Logging in...')}
                            </span>
                        ) : (
                            __('Quick PIN Login')
                        )}
                    </button>

                    <p className="mt-4 text-center text-xs text-gray-500">
                        {__('No PIN yet? Log in with password first, then set your PIN in Settings.')}
                    </p>
                </form>
            )}

            {/* Register link */}
            {regEnabled && (
                <p className="mt-6 text-center text-sm text-gray-600">
                    {__("Don't have an account?")}{' '}
                    <Link href="/register" className="text-blue-600 hover:underline font-medium">
                        {__('Create account')}
                    </Link>
                </p>
            )}
        </div>
    );
}

Login.layout = (page) => <AuthLayout>{page}</AuthLayout>;
