import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../layouts/AuthLayout';

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
            <h2 className="text-2xl font-bold text-gray-800 mb-4 text-center">Utwórz konto</h2>

            {/* Demo notice */}
            <div className="mb-5 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
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
                    To jest <strong>konto demo</strong> — zostanie automatycznie usunięte po <strong>3 godzinach</strong>.
                </span>
            </div>

            <form onSubmit={submit}>
                {/* Full Name */}
                <div className="mb-4">
                    <label htmlFor="name" className="form-label">
                        Imię i nazwisko
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
                        <p className="mt-1 text-sm text-red-600">{form.errors.name}</p>
                    )}
                </div>

                {/* Username */}
                <div className="mb-4">
                    <label htmlFor="username" className="form-label">
                        Nazwa użytkownika
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
                        <p className="mt-1 text-sm text-red-600">{form.errors.username}</p>
                    )}
                </div>

                {/* Email */}
                <div className="mb-4">
                    <label htmlFor="email" className="form-label">
                        Email
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
                        <p className="mt-1 text-sm text-red-600">{form.errors.email}</p>
                    )}
                </div>

                {/* Password */}
                <div className="mb-4">
                    <label htmlFor="password" className="form-label">
                        Hasło
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
                        <p className="mt-1 text-sm text-red-600">{form.errors.password}</p>
                    )}
                </div>

                {/* Confirm Password */}
                <div className="mb-4">
                    <label htmlFor="password_confirmation" className="form-label">
                        Potwierdź hasło
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
                        <p className="mt-1 text-sm text-red-600">{form.errors.password_confirmation}</p>
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
                            className="mt-1 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        />
                        <span className="text-xs text-gray-600 leading-relaxed">
                            Wyrażam zgodę na otrzymywanie aktualizacji produktu i komunikacji marketingowej drogą mailową.{' '}
                            <span className="text-gray-400">
                                / I agree to receive product updates and marketing communications via email.
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
                            Tworzenie konta...
                        </span>
                    ) : (
                        'Utwórz konto'
                    )}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-600">
                Masz już konto?{' '}
                <Link href="/login" className="text-blue-600 hover:underline font-medium">
                    Zaloguj się
                </Link>
            </p>
        </div>
    );
}

Register.layout = (page) => <AuthLayout>{page}</AuthLayout>;
