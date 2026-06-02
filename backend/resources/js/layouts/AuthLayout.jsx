import { usePage } from '@inertiajs/react';

/**
 * Centered card chrome for unauthenticated pages — the React port of
 * resources/views/layouts/auth.blade.php.
 *
 * Renders the OpenMES logo + tagline above a white rounded card, on a
 * blue-to-indigo gradient background.  No sidebar, no nav.
 *
 * Pages opt in via:
 *   PageName.layout = (page) => <AuthLayout>{page}</AuthLayout>;
 */
export default function AuthLayout({ children }) {
    const { flash, locale, locales } = usePage().props;

    return (
        <div className="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
            <div className="w-full max-w-md">
                {/* Logo / Header */}
                <div className="text-center mb-8">
                    <img src="/logo_open_mes.png" alt="OpenMES" className="h-16 md:h-20 mx-auto mb-2" />
                    <p className="text-gray-600 mt-2">Manufacturing Execution System</p>
                </div>

                {/* Auth Card */}
                <div className="bg-white rounded-lg shadow-xl p-8">
                    {/* Flash messages */}
                    {flash?.success && (
                        <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg" role="alert">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
                            {flash.error}
                        </div>
                    )}

                    {/* Page content */}
                    {children}
                </div>

                {/* Footer */}
                <div className="text-center mt-6 text-sm text-gray-600 space-y-3">
                    {locales && Object.keys(locales).length > 1 && (
                        <select
                            value={locale}
                            onChange={(e) => { window.location.href = `/locale/${e.target.value}`; }}
                            aria-label="Language"
                            className="mx-auto block bg-white text-gray-700 text-xs rounded-md border border-gray-300 px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            {Object.entries(locales).map(([code, label]) => (
                                <option key={code} value={code}>{label}</option>
                            ))}
                        </select>
                    )}
                    <p>&copy; {new Date().getFullYear()} All rights reserved.</p>
                </div>
            </div>
        </div>
    );
}
