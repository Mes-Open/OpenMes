import { useState, useEffect, useRef } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { __, formatNumber } from '../../../lib/i18n';

/**
 * Live machine monitor — a tile grid of workstation states, polled from the
 * `check` endpoint. Mirrors the MQTT Show live-log polling pattern.
 * Tile shape (MachineMonitorController::tiles): id, name, line, state, color,
 * since, availability, quality, good, reject, metadata.
 */

const BORDER = {
    green: 'border-green-500',
    amber: 'border-amber-400',
    blue:  'border-blue-500',
    gray:  'border-gray-400',
    red:   'border-red-500',
    slate: 'border-slate-300',
};

const BADGE = {
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    blue:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    gray:  'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    red:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
};

const POLL_MS = 3000;

function timeInState(sinceIso, now) {
    if (!sinceIso) return null;
    const start = new Date(sinceIso).getTime();
    if (Number.isNaN(start)) return null;
    const sec = Math.max(0, Math.floor((now - start) / 1000));
    if (sec < 60) return `${sec}s`;
    if (sec < 3600) return `${Math.floor(sec / 60)}m`;
    return `${Math.floor(sec / 3600)}h ${Math.floor((sec % 3600) / 60)}m`;
}

export default function MachineMonitorIndex() {
    const { tiles: initialTiles = [], checkUrl } = usePage().props;

    const [tiles, setTiles] = useState(initialTiles);
    const [live, setLive] = useState(true);
    const [now, setNow] = useState(() => Date.now());
    const liveRef = useRef(live);
    liveRef.current = live;

    // Poll the fleet status.
    useEffect(() => {
        if (!checkUrl) return undefined;
        const interval = setInterval(async () => {
            if (!liveRef.current) return;
            try {
                const res = await fetch(checkUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const json = await res.json();
                if (Array.isArray(json.data)) setTiles(json.data);
            } catch (_) { /* keep last good snapshot */ }
        }, POLL_MS);
        return () => clearInterval(interval);
    }, [checkUrl]);

    // Tick once a second so the "time in state" labels stay fresh.
    useEffect(() => {
        const t = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(t);
    }, []);

    return (
        <>
            <Head title={__('Machine Monitor')} />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{__('Machine Monitor')}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {__('Live workstation states from connected machines.')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setLive((l) => !l)}
                        className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        title={live ? __('Pause live updates') : __('Resume live updates')}
                    >
                        <span className={`w-2 h-2 rounded-full ${live ? 'bg-green-500 animate-pulse' : 'bg-slate-400'}`} />
                        {live ? __('Live') : __('Paused')}
                    </button>
                </div>

                {tiles.length === 0 ? (
                    <div className="text-center py-16 text-gray-400 dark:text-gray-500">
                        <p className="text-sm">{__('No workstations are wired to a machine connection yet.')}</p>
                        <Link href="/admin/connectivity/modbus" className="mt-2 inline-block text-blue-500 hover:underline text-sm">
                            {__('Configure a Modbus connection →')}
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {tiles.map((t) => (
                            <Tile key={t.id} t={t} now={now} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

MachineMonitorIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

function Tile({ t, now }) {
    const border = BORDER[t.color] ?? BORDER.slate;
    const badge = BADGE[t.color] ?? BADGE.slate;
    const elapsed = timeInState(t.since, now);
    const metadata = t.metadata && typeof t.metadata === 'object' ? Object.entries(t.metadata) : [];

    return (
        <div className={`bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 border-l-4 ${border} shadow-sm p-5`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="font-bold text-gray-900 dark:text-white truncate">{t.name}</h3>
                    {t.line && <p className="text-xs text-gray-500 dark:text-gray-400">{t.line}</p>}
                </div>
                <span className={`shrink-0 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide ${badge}`}>
                    {t.state}
                </span>
            </div>

            {elapsed && (
                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{__('in state for')} {elapsed}</p>
            )}

            <div className="grid grid-cols-3 gap-2 text-center mt-4">
                <Metric label={__('Availability')} value={t.availability != null ? `${t.availability}%` : '—'} />
                <Metric label={__('Good')} value={formatNumber(Number(t.good ?? 0))} tone="text-green-600 dark:text-green-400" />
                <Metric label={__('Reject')} value={formatNumber(Number(t.reject ?? 0))} tone="text-red-500 dark:text-red-400" />
            </div>

            {t.quality != null && (
                <p className="mt-3 text-xs text-gray-400 dark:text-gray-500 text-center">{__('Quality')} {t.quality}%</p>
            )}

            {metadata.length > 0 && (
                <div className="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-1.5">
                    {metadata.map(([k, v]) => (
                        <span key={k} className="text-[11px] bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded px-1.5 py-0.5 text-gray-600 dark:text-gray-300">
                            <span className="text-gray-400 dark:text-gray-500">{k}:</span> {String(v)}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

function Metric({ label, value, tone = 'text-gray-800 dark:text-gray-100' }) {
    return (
        <div>
            <p className="text-[10px] uppercase text-gray-400 dark:text-gray-500">{label}</p>
            <p className={`text-lg font-bold ${tone}`}>{value}</p>
        </div>
    );
}
