import { Head, Link } from '@inertiajs/react';
import OnboardingLayout from '../../layouts/OnboardingLayout';

/**
 * Onboarding Complete — shown after storeStep4 redirects here.
 * GET /onboarding/complete → OnboardingController@complete
 */
export default function Complete() {
    return (
        <>
            <Head title="Setup Complete" />
            <div className="text-center py-8">
                <div className="w-16 h-16 bg-om-running-bg rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg className="w-8 h-8 text-om-running" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>
                <h2 className="text-2xl font-bold text-om-ink mb-2">Setup Complete!</h2>
                <p className="text-om-muted mb-6">
                    Your production line, product type, process template, and first work order have been created.
                </p>

                <div className="space-y-3">
                    <Link href="/admin/dashboard" className="btn-touch btn-primary block">
                        Go to Dashboard
                    </Link>
                    <Link href="/operator/select-line" className="btn-touch btn-secondary block">
                        Start as Operator
                    </Link>
                </div>
            </div>
        </>
    );
}

Complete.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
