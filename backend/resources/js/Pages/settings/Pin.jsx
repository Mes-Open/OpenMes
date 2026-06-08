import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

export default function Pin() {
    const { hasPin, csrf_token } = usePage().props;

    // Set/Change PIN form
    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        pin: '',
        pin_confirmation: '',
    });

    // Remove PIN form — minimal, uses a bare fetch/form
    const [showRemove, setShowRemove] = useState(false);
    const {
        data: removeData,
        setData: setRemoveData,
        delete: destroyPin,
        processing: removeProcessing,
        errors: removeErrors,
    } = useForm({ current_password: '' });

    const pinValid = data.pin.length >= 4 && data.pin === data.pin_confirmation;

    function handleSetPin(e) {
        e.preventDefault();
        post('/settings/pin');
    }

    function handleRemovePin(e) {
        e.preventDefault();
        destroyPin('/settings/pin');
    }

    return (
        <div className="max-w-lg mx-auto">
            <Head title={__('PIN Setup')} />

            <div className="flex items-center gap-3 mb-6">
                <Link href="/settings" className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h1 className="text-2xl font-bold text-gray-800 dark:text-gray-100">{__('Quick PIN Login')}</h1>
                    <p className="text-gray-500 dark:text-gray-400 text-sm mt-0.5">{__('Set a 4–6 digit PIN for fast sign-in')}</p>
                </div>
            </div>

            {/* PIN active status */}
            {hasPin && (
                <div className="card mb-6 border-l-4 border-green-400">
                    <div className="flex items-center gap-3">
                        <div className="bg-green-100 dark:bg-green-900/30 rounded-full p-2">
                            <svg className="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p className="font-medium text-gray-800 dark:text-gray-100">{__('PIN is active')}</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{__('You can log in using your username and PIN.')}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Remove PIN section */}
            {hasPin && (
                <div className="card mb-6">
                    <button
                        type="button"
                        onClick={() => setShowRemove((v) => !v)}
                        className="text-sm text-red-600 dark:text-red-400 hover:underline font-medium"
                    >
                        {__('Remove PIN')}
                    </button>
                    {showRemove && (
                        <form onSubmit={handleRemovePin} className="mt-4 space-y-4">
                            <div>
                                <label htmlFor="rm_password" className="form-label">{__('Confirm your password')}</label>
                                <input
                                    type="password"
                                    id="rm_password"
                                    value={removeData.current_password}
                                    onChange={(e) => setRemoveData('current_password', e.target.value)}
                                    className={`form-input w-full${removeErrors.current_password ? ' border-red-500' : ''}`}
                                    required
                                />
                                {removeErrors.current_password && (
                                    <p className="mt-1 text-sm text-red-600 dark:text-red-300">{removeErrors.current_password}</p>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={removeProcessing}
                                className="btn-touch px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700"
                            >
                                Remove PIN
                            </button>
                        </form>
                    )}
                </div>
            )}

            {/* Set / Change PIN form */}
            <div className="card">
                <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
                    {hasPin ? __('Change PIN') : __('Set PIN')}
                </h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    {__('Enter your current account password and choose a 4–6 digit numeric PIN.')}
                </p>

                <form onSubmit={handleSetPin} className="space-y-4">
                    <div>
                        <label htmlFor="current_password" className="form-label">{__('Current Password')}</label>
                        <input
                            type="password"
                            id="current_password"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            className={`form-input w-full${errors.current_password ? ' border-red-500' : ''}`}
                            required
                            autoComplete="current-password"
                        />
                        {errors.current_password && (
                            <p className="mt-1 text-sm text-red-600 dark:text-red-300">{errors.current_password}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="pin" className="form-label">{__('PIN (4–6 digits)')}</label>
                        <input
                            type="password"
                            id="pin"
                            value={data.pin}
                            onChange={(e) => setData('pin', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            className={`form-input w-full text-center text-2xl tracking-[0.5em]${errors.pin ? ' border-red-500' : ''}`}
                            required
                            placeholder="----"
                        />
                        {errors.pin && <p className="mt-1 text-sm text-red-600 dark:text-red-300">{errors.pin}</p>}
                    </div>

                    <div>
                        <label htmlFor="pin_confirmation" className="form-label">{__('Confirm PIN')}</label>
                        <input
                            type="password"
                            id="pin_confirmation"
                            value={data.pin_confirmation}
                            onChange={(e) => setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 6))}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            className="form-input w-full text-center text-2xl tracking-[0.5em]"
                            required
                            placeholder="----"
                        />
                    </div>

                    <div className="pt-2">
                        <button
                            type="submit"
                            disabled={!pinValid || processing}
                            className={`w-full btn-touch btn-primary${(!pinValid || processing) ? ' opacity-50 cursor-not-allowed' : ''}`}
                        >
                            {hasPin ? __('Change PIN') : __('Set PIN')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

Pin.layout = (page) => <AppLayout>{page}</AppLayout>;
