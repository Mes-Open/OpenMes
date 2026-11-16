import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { __ } from '../../../lib/i18n';

/**
 * Shows the runtime (poller / gateway) status for a connection and the command
 * to start it. `runtime` matches RuntimeMonitor::connectionRuntime():
 *   { required, alive, seconds_ago, label, command, docker, error, token_url, endpoint_warning }
 * The error message itself is shown by the page's status banner; here we only
 * tell "not running" apart from "running but cannot reach the machine".
 */
export default function RuntimePanel({ runtime }) {
    if (!runtime) return null;

    const { required, alive, seconds_ago, label, command, docker, error, token_url, endpoint_warning } = runtime;

    const state = !required
        ? { dot: 'bg-slate-400', text: __('Not required (connection inactive)'), tone: 'text-om-muted' }
        : alive && error
            ? { dot: 'bg-om-downtime', text: __('Running, but cannot connect to the machine'), tone: 'text-om-downtime' }
            : alive
                ? { dot: 'bg-om-running animate-pulse', text: __('Running - last heartbeat :seconds ago', { seconds: `${seconds_ago ?? '?'}s` }), tone: 'text-om-running' }
                : { dot: 'bg-om-blocked', text: __('Not running - start the runtime below'), tone: 'text-om-blocked' };

    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-5 space-y-4">
            <div className="flex items-center gap-3">
                <span className={`w-3 h-3 rounded-full shrink-0 ${state.dot}`} />
                <div>
                    <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{label}</h2>
                    <p className={`text-sm ${state.tone}`}>{state.text}</p>
                </div>
            </div>

            {endpoint_warning && (
                <p className="rounded-om-sm border border-om-line bg-om-downtime-bg px-3 py-2 text-xs text-om-ink">
                    {endpoint_warning}
                </p>
            )}

            {(command || docker) && (
                // Start instructions are a setup step: open while the runtime is
                // down (or inactive), tucked away once it runs cleanly.
                <details open={!(alive && !error)} className="group">
                    <summary className="cursor-pointer select-none text-xs font-medium text-om-muted hover:text-om-ink list-none flex items-center gap-1">
                        <svg className="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                        {__('How to start it')}
                    </summary>
                    <div className="mt-3 space-y-4">
                        {command && <CommandBlock title={__('Artisan (foreground)')} value={command} />}
                        {docker && <CommandBlock title={__('Docker (background)')} value={docker} />}

                        {token_url && (
                            <p className="text-xs text-om-muted">
                                {__('Replace <token> with a machine gateway token.')}{' '}
                                <Link href={token_url} className="text-om-accent hover:underline font-medium">
                                    {__('Create one in Settings → API Tokens')}
                                </Link>
                            </p>
                        )}
                    </div>
                </details>
            )}
        </div>
    );
}

export function CommandBlock({ title, value }) {
    const [copied, setCopied] = useState(false);

    const copy = () => {
        navigator.clipboard?.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-1">
                <span className="text-xs text-om-muted">{title}</span>
                <button
                    type="button"
                    onClick={copy}
                    className="text-xs text-om-accent hover:text-om-accent"
                >
                    {copied ? __('Copied!') : __('Copy')}
                </button>
            </div>
            <pre className="bg-om-ink text-gray-100 text-xs font-mono rounded-om-sm p-3 overflow-x-auto">{value}</pre>
        </div>
    );
}
