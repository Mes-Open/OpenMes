import { Head, router, usePage } from '@inertiajs/react';
import { __ } from '../lib/i18n';

/**
 * Wizard chrome for the onboarding flow.
 * Reproduces onboarding/layout.blade.php: centred card, logo, 4-step stepper
 * (Line → Product → Process → Work Order), skip link at bottom.
 *
 * Reads `step` (1–5) from page props; 5 = Complete (all steps shown as done).
 */
export default function OnboardingLayout({ children }) {
    const { step = 1, csrf_token } = usePage().props;

    const steps = ['Line', 'Product', 'Process', 'Work Order'];

    const skipWizard = (e) => {
        e.preventDefault();
        router.post('/onboarding/skip', {}, { headers: { 'X-CSRF-TOKEN': csrf_token } });
    };

    return (
        <div className="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">
            <Head title={`OpenMES — ${__('Setup Wizard')}`} />
            <div className="w-full max-w-2xl">
                {/* Logo */}
                <div className="text-center mb-8">
                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-10 mx-auto mb-2" />
                    <p className="text-sm text-gray-500">{__('Setup Wizard')}</p>
                </div>

                {/* Stepper */}
                <div className="flex items-center justify-center mb-8">
                    {steps.map((label, i) => {
                        const stepNum = i + 1;
                        const done = stepNum < step;
                        const current = stepNum === step;
                        return (
                            <div key={label} className="flex items-center">
                                <div className="flex flex-col items-center">
                                    <div
                                        className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                            ${done
                                                ? 'bg-green-500 text-white'
                                                : current
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-300 text-gray-600'}`}
                                    >
                                        {done ? (
                                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    fillRule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        ) : (
                                            stepNum
                                        )}
                                    </div>
                                    <span
                                        className={`text-xs mt-1 ${current ? 'text-blue-600 font-medium' : 'text-gray-500'}`}
                                    >
                                        {__(label)}
                                    </span>
                                </div>
                                {i < steps.length - 1 && (
                                    <div
                                        className={`w-12 h-0.5 mx-1 ${done ? 'bg-green-500' : 'bg-gray-300'}`}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Content card */}
                <div className="bg-white rounded-xl shadow-lg p-8">{children}</div>

                {/* Skip */}
                <div className="text-center mt-4">
                    <button
                        type="button"
                        onClick={skipWizard}
                        className="text-sm text-gray-400 hover:text-gray-600"
                    >
                        {__('Skip wizard →')}
                    </button>
                </div>
            </div>
        </div>
    );
}
