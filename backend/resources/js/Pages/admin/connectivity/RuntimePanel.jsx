import { useState } from 'react';

/**
 * Shows the runtime (poller / gateway) status for a connection and the command
 * to start it. `runtime` matches RuntimeMonitor::connectionRuntime():
 *   { required, alive, seconds_ago, label, command, docker }
 */
export default function RuntimePanel({ runtime }) {
    if (!runtime) return null;

    const { required, alive, seconds_ago, label, command, docker } = runtime;

    const state = !required
        ? { dot: 'bg-slate-400', text: 'Not required (connection inactive)', tone: 'text-gray-500 dark:text-gray-400' }
        : alive
            ? { dot: 'bg-green-500 animate-pulse', text: `Running — last heartbeat ${seconds_ago ?? '?'}s ago`, tone: 'text-green-700 dark:text-green-400' }
            : { dot: 'bg-red-500', text: 'Not running — start the runtime below', tone: 'text-red-600 dark:text-red-400' };

    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div className="flex items-center gap-3">
                <span className={`w-3 h-3 rounded-full shrink-0 ${state.dot}`} />
                <div>
                    <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{label}</h2>
                    <p className={`text-sm ${state.tone}`}>{state.text}</p>
                </div>
            </div>

            {command && <CommandBlock title="Artisan (foreground)" value={command} />}
            {docker && <CommandBlock title="Docker (background)" value={docker} />}
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
                <span className="text-xs text-gray-500 dark:text-gray-400">{title}</span>
                <button
                    type="button"
                    onClick={copy}
                    className="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700"
                >
                    {copied ? 'Copied!' : 'Copy'}
                </button>
            </div>
            <pre className="bg-gray-900 dark:bg-gray-950 text-gray-100 text-xs font-mono rounded-lg p-3 overflow-x-auto">{value}</pre>
        </div>
    );
}
