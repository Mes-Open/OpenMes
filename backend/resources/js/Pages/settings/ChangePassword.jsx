import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

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
            <label htmlFor={id} className="form-label">{label}</label>
            <div className="relative">
                <input
                    type={show ? 'text' : 'password'}
                    id={id}
                    value={value}
                    onChange={onChange}
                    className={`form-input w-full pr-12${error ? ' border-red-500' : ''}`}
                    required
                    autoComplete={autoComplete}
                    minLength={minLength}
                />
                <button
                    type="button"
                    onClick={() => setShow((s) => !s)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                >
                    <EyeIcon visible={show} />
                </button>
            </div>
            {hint && <p className="text-sm text-gray-500 mt-1">{hint}</p>}
            {error && <p className="text-red-600 text-sm mt-1">{error}</p>}
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

    let confirmHint = 'Re-enter your password';
    if (data.password_confirmation && data.password !== data.password_confirmation) {
        confirmHint = null; // shown as error-style below
    } else if (data.password && data.password_confirmation && data.password === data.password_confirmation) {
        confirmHint = null; // shown as success below
    }

    return (
        <div className="max-w-2xl mx-auto">
            <Head title="Change Password" />

            <div className="mb-6">
                <Link href="/settings" className="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </Link>
                <h1 className="text-3xl font-bold text-gray-800">Change Password</h1>
            </div>

            <div className="card">
                <form onSubmit={handleSubmit}>
                    <PasswordField
                        id="current_password"
                        label="Current Password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        error={errors.current_password}
                        autoComplete="current-password"
                    />

                    <PasswordField
                        id="password"
                        label="New Password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        hint="Minimum 8 characters"
                        autoComplete="new-password"
                        minLength={8}
                    />

                    <div className="mb-6">
                        <label htmlFor="password_confirmation" className="form-label">Confirm New Password</label>
                        <div className="relative">
                            {/* PasswordField not reused here so we can place the dynamic hint outside */}
                            <ConfirmField
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                        </div>
                        {!data.password_confirmation && (
                            <p className="text-sm text-gray-500 mt-1">Re-enter your password</p>
                        )}
                        {data.password_confirmation && data.password !== data.password_confirmation && (
                            <p className="text-sm text-red-600 mt-1">Passwords do not match</p>
                        )}
                        {data.password && data.password_confirmation && data.password === data.password_confirmation && (
                            <p className="text-sm text-green-600 mt-1">Passwords match</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <Link href="/settings" className="btn-touch btn-secondary">Cancel</Link>
                        <button
                            type="submit"
                            disabled={!canSubmit || processing}
                            className={`btn-touch btn-primary${(!canSubmit || processing) ? ' opacity-50 cursor-not-allowed' : ''}`}
                        >
                            Change Password
                        </button>
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
                className="form-input w-full pr-12"
                required
                autoComplete="new-password"
                minLength={8}
            />
            <button
                type="button"
                onClick={() => setShow((s) => !s)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
            >
                <EyeIcon visible={show} />
            </button>
        </div>
    );
}

ChangePassword.layout = (page) => <AppLayout>{page}</AppLayout>;
