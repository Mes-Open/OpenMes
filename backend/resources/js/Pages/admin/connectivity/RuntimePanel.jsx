import { useState } from 'react';
import { __ } from '../../../lib/i18n';

/**
 * Shows the runtime (poller / gateway) status for a connection and the command
 * to start it. `runtime` matches RuntimeMonitor::connectionRuntime():
 *   { required, alive, seconds_ago, label, command, docker }
 */
export default function RuntimePanel({ runtime }) {
    if (!runtime) return null;

    const { required, alive, seconds_ago, label, command, docker } = runtime;

    const state = !required
        ? { dot: 'bg-slate-400', text: __('Not required (connection inactive)'), tone: 'text-om-muted' }
        : alive
            ? { dot: 'bg-om-running animate-pulse', text: __('Running — last heartbeat :seconds ago', { seconds: `${seconds_ago ?? '?'}s` }), tone: 'text-om-running' }
            : { dot: 'bg-om-blocked', text: __('Not running — start the runtime below'), tone: 'text-om-blocked' };

    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-5 space-y-4">
            <div className="flex items-center gap-3">
                <span className={`w-3 h-3 rounded-full shrink-0 ${state.dot}`} />
                <div>
                    <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{label}</h2>
                    <p className={`text-sm ${state.tone}`}>{state.text}</p>
                </div>
            </div>

            {command && <CommandBlock title={__('Artisan (foreground)')} value={command} />}
            {docker && <CommandBlock title={__('Docker (background)')} value={docker} />}
        </div>
    );
}

function CommandBlock({ title, value }) {
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
