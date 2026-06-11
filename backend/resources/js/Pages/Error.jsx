import { Head, Link } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import OperatorLayout from '../layouts/OperatorLayout';
import { __ } from '../lib/i18n';

/**
 * Friendly error page rendered for production error statuses (see
 * bootstrap/app.php). It opts into the same persistent layout the user already
 * has, so the sidebar / chrome stays put and they can navigate away instead of
 * landing on a bare error screen.
 */
const TITLES = {
    403: 'Forbidden',
    404: 'Page not found',
    429: 'Too many requests',
    500: 'Server error',
    503: 'Service unavailable',
};

const MESSAGES = {
    403: 'You do not have permission to view this page.',
    404: 'The page you are looking for does not exist or was moved.',
    429: 'Too many requests. Please wait a moment and try again.',
    500: 'Something went wrong on our end. Please try again.',
    503: 'The service is temporarily unavailable. Please try again shortly.',
};

export default function ErrorPage({ status }) {
    const title = TITLES[status] ?? 'Error';
    const message = MESSAGES[status] ?? 'An unexpected error occurred.';

    return (
        <>
            <Head title={`${status} — ${__(title)}`} />
            <div className="flex items-center justify-center py-20 px-4">
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-10 max-w-md w-full text-center">
                    <p className="text-6xl font-extrabold text-gray-200 leading-none">{status}</p>
                    <h1 className="mt-4 text-xl font-bold text-gray-800">{__(title)}</h1>
                    <p className="mt-2 text-sm text-gray-500">{__(message)}</p>
                    <div className="mt-6 flex items-center justify-center gap-3">
                        <Link
                            href="/"
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700"
                        >
                            {__('Back to dashboard')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="text-gray-500 hover:text-gray-800 text-sm"
                        >
                            {__('Try again')}
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}

// Reuse the viewer's existing chrome so the side panel stays visible: admins and
// supervisors get the admin sidebar, operators their touch layout, guests none.
ErrorPage.layout = (page) => {
    const roles = page.props?.auth?.user?.roles ?? [];
    if (roles.length === 0) {
        return page;
    }

    return roles.includes('Admin') || roles.includes('Supervisor')
        ? <AppLayout>{page}</AppLayout>
        : <OperatorLayout>{page}</OperatorLayout>;
};
