import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AuthLayout from '../../layouts/AuthLayout';

/**
 * Forced password change page — Inertia render name: auth/ChangePassword
 *
 * Shown when auth()->user()->force_password_change is true. The user cannot
 * access any other page until they change their password.
 *
 * Props (from AuthController::showChangePasswordForm):
 *   forceChange (bool) — show the "action required" banner
 *
 * POST /settings/change-password → SettingsController::updatePassword
 *   fields: current_password, password, password_confirmation
 *   (matches SettingsController::updatePassword validation)
 *
 * Validation errors are surfaced from form.errors.
 */
export default function ChangePassword({ forceChange = false }) {
    const [showCurrent, setShowCurrent] = useState(false);
    const [showNew, setShowNew] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/settings/change-password');
    };

    const passwordsMatch =
        form.data.password &&
        form.data.password_confirmation &&
        form.data.password === form.data.password_confirmation;

    const isDisabled =
        form.processing ||
        !form.data.current_password ||
        !form.data.password ||
        !form.data.password_confirmation ||
        !passwordsMatch;

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-800 text-center">Zmień hasło</h1>
                <p className="text-gray-600 mt-2 text-center text-sm">
                    Zaktualizuj hasło, aby zabezpieczyć swoje konto.
                </p>

                {forceChange && (
                    <div className="mt-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded-lg text-sm">
                        <strong>Wymagana akcja:</strong> Musisz zmienić hasło przed kontynuowaniem.
                    </div>
                )}
            </div>

            <form onSubmit={submit}>
                {/* Current Password */}
                <div className="mb-4">
                    <label htmlFor="current_password" className="form-label">
                        Aktualne hasło
                    </label>
                    <div className="relative">
                        <input
                            type={showCurrent ? 'text' : 'password'}
                            id="current_password"
                            name="current_password"
                            value={form.data.current_password}
                            onChange={(e) => form.setData('current_password', e.target.value)}
                            className={`form-input w-full pr-12${form.errors.current_password ? ' border-red-500' : ''}`}
                            autoComplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowCurrent((v) => !v)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            tabIndex={-1}
                        >
                            <EyeIcon open={showCurrent} />
                        </button>
                    </div>
                    {form.errors.current_password && (
                        <p className="mt-1 text-sm text-red-600">{form.errors.current_password}</p>
                    )}
                </div>

                {/* New Password */}
                <div className="mb-4">
                    <label htmlFor="new_password" className="form-label">
                        Nowe hasło
                    </label>
                    <div className="relative">
                        <input
                            type={showNew ? 'text' : 'password'}
                            id="new_password"
                            name="new_password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            className={`form-input w-full pr-12${form.errors.password ? ' border-red-500' : ''}`}
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowNew((v) => !v)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            tabIndex={-1}
                        >
                            <EyeIcon open={showNew} />
                        </button>
                    </div>
                    <p className="mt-1 text-sm text-gray-500">Minimum 8 znaków</p>
                    {form.errors.password && (
                        <p className="mt-1 text-sm text-red-600">{form.errors.password}</p>
                    )}
                </div>

                {/* Confirm New Password */}
                <div className="mb-6">
                    <label htmlFor="new_password_confirmation" className="form-label">
                        Potwierdź nowe hasło
                    </label>
                    <div className="relative">
                        <input
                            type={showConfirm ? 'text' : 'password'}
                            id="new_password_confirmation"
                            name="new_password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            className="form-input w-full pr-12"
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowConfirm((v) => !v)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            tabIndex={-1}
                        >
                            <EyeIcon open={showConfirm} />
                        </button>
                    </div>
                    <p className={`mt-1 text-sm ${passwordsMatch ? 'text-green-600' : 'text-gray-500'}`}>
                        {!form.data.password_confirmation && 'Wpisz ponownie nowe hasło'}
                        {form.data.password_confirmation && !passwordsMatch && (
                            <span className="text-red-600">Hasła nie są zgodne</span>
                        )}
                        {passwordsMatch && '✓ Hasła są zgodne'}
                    </p>
                </div>

                {/* Actions */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <button
                        type="submit"
                        disabled={isDisabled}
                        className={`btn-touch btn-primary flex-1${isDisabled ? ' opacity-50 cursor-not-allowed' : ''}`}
                    >
                        {form.processing ? 'Zmienianie...' : 'Zmień hasło'}
                    </button>

                    {!forceChange && (
                        <a
                            href="/operator/select-line"
                            className="btn-touch btn-secondary flex-1 text-center"
                        >
                            Anuluj
                        </a>
                    )}
                </div>
            </form>
        </div>
    );
}

ChangePassword.layout = (page) => <AuthLayout>{page}</AuthLayout>;

function EyeIcon({ open }) {
    if (open) {
        return (
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                />
            </svg>
        );
    }
    return (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
            />
        </svg>
    );
}
