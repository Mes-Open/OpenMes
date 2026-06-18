import { Head, router, usePage } from '@inertiajs/react';

/**
 * Wizard chrome for the onboarding flow.
 * Reproduces onboarding/layout.blade.php: centred card, logo, 4-step stepper
 * (Line → Product → Process → Work Order), skip link at bottom.
 *
 * Reads `step` (1–5) from page props; 5 = Complete (all steps shown as done).
 *
 * Geist White restyle: light-only v1 — om-* tokens, hairline card, mono labels.
 */
export default function OnboardingLayout({ children }) {
    const { step = 1, csrf_token } = usePage().props;

    const steps = ['Line', 'Product', 'Process', 'Work Order'];

    const skipWizard = (e) => {
        e.preventDefault();
        router.post('/onboarding/skip', {}, { headers: { 'X-CSRF-TOKEN': csrf_token } });
    };

    return (
        <div className="bg-om-bg min-h-screen flex flex-col items-center justify-center p-4 font-sans">
            <Head title="OpenMES — Setup Wizard" />
            <div className="w-full max-w-2xl">
                {/* Logo */}
                <div className="text-center mb-8">
                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-9 w-auto mx-auto mb-3" />
                    <p className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">Setup Wizard</p>
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
                                        className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                                            ${done
                                                ? 'bg-om-running text-white'
                                                : current
                                                ? 'bg-om-accent text-white'
                                                : 'bg-om-chip text-om-faint border border-om-line'}`}
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
                                        className={`font-mono text-[9.5px] uppercase tracking-[0.08em] mt-1.5 ${current ? 'text-om-accent font-medium' : 'text-om-faint'}`}
                                    >
                                        {label}
                                    </span>
                                </div>
                                {i < steps.length - 1 && (
                                    <div
                                        className={`w-12 h-px mx-1 ${done ? 'bg-om-running' : 'bg-om-line'}`}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Content card */}
                <div className="bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] p-8">{children}</div>

                {/* Skip */}
                <div className="text-center mt-4">
                    <button
                        type="button"
                        onClick={skipWizard}
                        className="text-sm text-om-faint hover:text-om-muted"
                    >
                        Skip wizard →
                    </button>
                </div>
            </div>
        </div>
    );
}
