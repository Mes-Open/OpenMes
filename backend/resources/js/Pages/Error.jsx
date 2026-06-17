import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import OperatorLayout from '../layouts/OperatorLayout';
import { __ } from '../lib/i18n';

/**
 * Friendly error page rendered for production error statuses (see
 * bootstrap/app.php). It wraps itself in the same chrome the viewer already
 * has — admins/supervisors get the admin sidebar, operators their touch
 * layout, guests a standalone card — so the user can navigate away instead of
 * landing on a bare error screen. The layout is chosen here (not via the
 * static `.layout`) so it reliably reads the shared auth props.
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
    const { auth } = usePage().props;
    const roles = auth?.user?.roles ?? [];

    const title = TITLES[status] ?? 'Error';
    const message = MESSAGES[status] ?? 'An unexpected error occurred.';

    const content = (
        <>
            <Head title={`${status} — ${__(title)}`} />
            <div className="flex items-center justify-center py-20 px-4">
                <div className="bg-om-card rounded-om shadow-sm border border-om-line2 p-10 max-w-md w-full text-center">
                    <p className="text-6xl font-extrabold text-gray-200 leading-none">{status}</p>
                    <h1 className="mt-4 text-xl font-bold text-om-ink">{__(title)}</h1>
                    <p className="mt-2 text-sm text-om-muted">{__(message)}</p>
                    <div className="mt-6 flex items-center justify-center gap-3">
                        <Link
                            href="/"
                            className="bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover"
                        >
                            {__('Back to dashboard')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="text-om-muted hover:text-om-ink text-sm"
                        >
                            {__('Try again')}
                        </button>
                    </div>
                </div>
            </div>
        </>
    );

    if (roles.length === 0) {
        return content;
    }

    const Layout = roles.includes('Admin') || roles.includes('Supervisor') ? AppLayout : OperatorLayout;

    return <Layout>{content}</Layout>;
}
