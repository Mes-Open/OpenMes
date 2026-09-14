// Shown when the server renders an Inertia page this build does not contain.
//
// That happens when a route outlives its page — a module was disabled or never
// installed, or a page was removed while a link to it stayed behind. Before this
// existed the resolver threw and the user got a white screen with the real cause
// only in the console.
//
// Deliberately standalone: no AppLayout, no shared props, no hooks beyond the
// page name. A last-resort fallback must not have its own way to fail.
import { Head } from '@inertiajs/react';
import { __ } from '../lib/i18n';

export default function MissingPage({ __pageName }) {
    return (
        <div className="min-h-screen bg-om-bg flex items-center justify-center p-6">
            <Head title={__('Page unavailable')} />

            <div className="max-w-md w-full rounded-om border border-om-line bg-om-surface p-6">
                <h1 className="text-[15px] font-semibold text-om-ink">{__('Page unavailable')}</h1>
                <p className="text-om-muted text-[13px] mt-2">
                    {__('This screen is not part of the installed system. It usually means the feature it belongs to is not installed or has been turned off.')}
                </p>
                {__pageName && (
                    <p className="text-om-faint text-[11.5px] font-mono mt-3 break-all">{__pageName}</p>
                )}
                <a
                    href="/admin"
                    className="inline-block mt-5 text-[13px] text-om-accent hover:underline"
                >
                    {__('Back to the dashboard')}
                </a>
            </div>
        </div>
    );
}
