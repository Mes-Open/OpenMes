import { usePage } from '@inertiajs/react';

/**
 * Centered card chrome for unauthenticated pages — the React port of
 * resources/views/layouts/auth.blade.php.
 *
 * Geist White: split brand mark + lowercase wordmark above a hairline-bordered
 * white card on the warm om-bg canvas. No sidebar, no nav.
 *
 * Pages opt in via:
 *   PageName.layout = (page) => <AuthLayout>{page}</AuthLayout>;
 */
export default function AuthLayout({ children }) {
    const { flash, locale, locales } = usePage().props;

    return (
        <div className="bg-om-bg min-h-screen flex items-center justify-center p-4 font-sans">
            <div className="w-full max-w-md">
                {/* Logo / Header */}
                <div className="text-center mb-8">
                    <div className="flex items-center justify-center gap-2.5 mb-3">
                        <div aria-hidden className="size-8 rounded-md bg-[linear-gradient(135deg,#EA5A2B_0_50%,#1A1917_50%_100%)]" />
                        <span className="text-xl font-semibold tracking-[-0.01em] text-om-ink">openmes</span>
                    </div>
                    <p className="font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint">Manufacturing Execution System</p>
                </div>

                {/* Auth Card */}
                <div className="bg-om-card border border-om-line rounded-om shadow-[0_20px_50px_-20px_rgba(0,0,0,.35)] p-8">
                    {/* Flash messages */}
                    {flash?.success && (
                        <div className="mb-4 p-4 bg-om-running-bg border border-om-running/30 text-om-running text-sm rounded-om-sm" role="alert">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-4 p-4 bg-om-blocked-bg border border-om-blocked/30 text-om-blocked text-sm rounded-om-sm" role="alert">
                            {flash.error}
                        </div>
                    )}

                    {/* Page content */}
                    {children}
                </div>

                {/* Footer */}
                <div className="text-center mt-6 text-sm text-om-muted space-y-3">
                    {locales && Object.keys(locales).length > 1 && (
                        <select
                            value={locale}
                            onChange={(e) => { window.location.href = `/locale/${e.target.value}`; }}
                            aria-label="Language"
                            className="mx-auto block bg-om-card text-om-muted text-xs rounded-om-sm border border-om-line px-2 py-1.5 focus:outline-none focus:border-om-accent focus:shadow-[0_0_0_3px_rgba(234,90,43,.12)]"
                        >
                            {Object.entries(locales).map(([code, label]) => (
                                <option key={code} value={code}>{label}</option>
                            ))}
                        </select>
                    )}
                    <p className="font-mono text-[11px] text-om-faint">&copy; {new Date().getFullYear()} All rights reserved.</p>
                </div>
            </div>
        </div>
    );
}
