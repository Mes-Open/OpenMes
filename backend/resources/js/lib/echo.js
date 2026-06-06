import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Single app-wide WebSocket to Laravel Reverb (Pusher protocol), replacing
 * Electric's per-shape HTTP long-polls. One connection carries every collection
 * channel (multiplexed), so the HTTP/1.1 ~6-connection limit no longer applies.
 *
 * Connects to the SAME host/port the app is served from (works on a factory-LAN
 * IP over plain http → ws:// with no cert), proxied by Caddy to Reverb. Private
 * channels auth via the session cookie at /broadcasting/auth (CSRF-exempt).
 */
window.Pusher = Pusher;

const isHttps = window.location.protocol === 'https:';
const port = window.location.port
    ? Number(window.location.port)
    : (isHttps ? 443 : 80);

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'openmeskey',
    wsHost: window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
});
