import { Link, useForm } from '@inertiajs/react';
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
        form.processing ||
        !form.data.name ||
        !form.data.username ||
        !form.data.email ||
        !form.data.password ||
        !form.data.password_confirmation;

    return (
        <div>
            <h2 className="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4 text-center">{__('Create account')}</h2>

            {/* Demo notice */}
            <div className="mb-5 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800">
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
                <div className="mb-4">
                    <label htmlFor="name" className="form-label">
                        {__('Full name')}
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        className={`form-input w-full${form.errors.name ? ' border-red-500' : ''}`}
                        autoComplete="name"
                        autoFocus
                        required
                    />
                    {form.errors.name && (
                        <p className="mt-1 text-sm text-red-600 dark:text-red-300">{form.errors.name}</p>
                    )}
                </div>

                {/* Username */}
                <div className="mb-4">
                    <label htmlFor="username" className="form-label">
                        {__('Username')}
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value={form.data.username}
                        onChange={(e) => form.setData('username', e.target.value)}
                        className={`form-input w-full${form.errors.username ? ' border-red-500' : ''}`}
                        autoComplete="username"
                        required
                    />
                    {form.errors.username && (
                        <p className="mt-1 text-sm text-red-600 dark:text-red-300">{form.errors.username}</p>
                    )}
                </div>

                {/* Email */}
                <div className="mb-4">
                    <label htmlFor="email" className="form-label">
                        {__('Email')}
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        className={`form-input w-full${form.errors.email ? ' border-red-500' : ''}`}
                        autoComplete="email"
                        required
                    />
                    {form.errors.email && (
                        <p className="mt-1 text-sm text-red-600 dark:text-red-300">{form.errors.email}</p>
                    )}
                </div>

                {/* Password */}
                <div className="mb-4">
                    <label htmlFor="password" className="form-label">
                        {__('Password')}
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        className={`form-input w-full${form.errors.password ? ' border-red-500' : ''}`}
                        autoComplete="new-password"
                        required
                    />
                    {form.errors.password && (
                        <p className="mt-1 text-sm text-red-600 dark:text-red-300">{form.errors.password}</p>
                    )}
                </div>

                {/* Confirm Password */}
                <div className="mb-4">
                    <label htmlFor="password_confirmation" className="form-label">
                        {__('Confirm password')}
                    </label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        className={`form-input w-full${form.errors.password_confirmation ? ' border-red-500' : ''}`}
                        autoComplete="new-password"
                        required
                    />
                    {form.errors.password_confirmation && (
                        <p className="mt-1 text-sm text-red-600 dark:text-red-300">{form.errors.password_confirmation}</p>
                    )}
                </div>

                {/* Marketing consent */}
                <div className="mb-6">
                    <label className="flex items-start gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            name="marketing_consent"
                            checked={form.data.marketing_consent}
                            onChange={(e) => form.setData('marketing_consent', e.target.checked)}
                            className="mt-1 h-4 w-4 text-blue-600 dark:text-blue-300 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
                        />
                        <span className="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                            {__('I agree to receive product updates and marketing communications via email.')}{' '}
                            <span className="text-gray-400 dark:text-gray-500">
                                / {__('I agree to receive product updates and marketing communications via email.')}
                            </span>
                        </span>
                    </label>
                </div>

                {/* Submit */}
                <button
                    type="submit"
                    disabled={isDisabled}
                    className={`w-full btn-touch btn-primary${isDisabled ? ' opacity-50 cursor-not-allowed' : ''}`}
                >
                    {form.processing ? (
                        <span className="flex items-center justify-center">
                            <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {__('Creating account...')}
                        </span>
                    ) : (
                        __('Create account')
                    )}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                {__('Already have an account?')}{' '}
                <Link href="/login" className="text-blue-600 dark:text-blue-300 hover:underline font-medium">
                    {__('Sign in')}
                </Link>
            </p>
        </div>
    );
}

Register.layout = (page) => <AuthLayout>{page}</AuthLayout>;
