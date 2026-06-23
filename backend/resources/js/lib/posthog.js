import posthog from 'posthog-js';

const POSTHOG_KEY = import.meta.env.VITE_POSTHOG_KEY;
const POSTHOG_HOST = import.meta.env.VITE_POSTHOG_HOST || 'https://us.i.posthog.com';

const MASKED_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token'];
const AUTH_PATHS = ['/login', '/register', '/forgot-password', '/reset-password'];

function isAuthPath() {
    return AUTH_PATHS.some((p) => window.location.pathname.startsWith(p));
}

export function initPostHog() {
    if (!POSTHOG_KEY) return;

    posthog.init(POSTHOG_KEY, {
        api_host: POSTHOG_HOST,
        capture_exceptions: true,
        autocapture: true,
        session_recording: {
            networkPayloadCapture: {
                recordHeaders: true,
                recordBody: !isAuthPath(),
            },
            maskNetworkRequestFn: (data) => {
                if (data.requestHeaders) {
                    for (const h of MASKED_HEADERS) {
                        if (data.requestHeaders[h]) data.requestHeaders[h] = '***';
                    }
                }
                if (data.responseHeaders) {
                    for (const h of MASKED_HEADERS) {
                        if (data.responseHeaders[h]) data.responseHeaders[h] = '***';
                    }
                }
                return data;
            },
        },
    });
}

export { posthog };
