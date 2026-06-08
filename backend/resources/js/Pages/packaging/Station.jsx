import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { formatTime } from '../../lib/i18n';

function ProgressBar({ pct, done }) {
    const color = done ? 'bg-green-500' : pct >= 50 ? 'bg-yellow-500' : 'bg-indigo-500';
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
            <span className="text-xs text-gray-500 w-8 text-right">{pct}%</span>
        </div>
    );
}

function ShiftLabel() {
    const h = new Date().getHours();
    return h >= 6 && h < 18 ? '06:00 – 18:00' : '18:00 – 06:00';
}

export default function Station() {
    const { auth } = usePage().props;

    const [items, setItems] = useState([]);
    const [history, setHistory] = useState([]);
    const [stats, setStats] = useState({ today_packed: 0, plan: 0, backlog: 0 });
    const [lastScan, setLastScan] = useState(null);
    const [flash, setFlash] = useState(null); // 'success' | 'error' | null
    const lastHistoryIdRef = useRef(0);
    const bufferRef = useRef('');
    const bufferTimerRef = useRef(null);

    const realizacja =
        stats.plan > 0 ? Math.min(100, Math.round((stats.today_packed / stats.plan) * 100)) : 0;

    const fetchItems = useCallback(async () => {
        try {
            const res = await fetch('/packaging/items', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setItems(data.items ?? []);
            }
        } catch {}
    }, []);

    const fetchHistory = useCallback(async () => {
        try {
            const res = await fetch('/packaging/history', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            const hist = data.history ?? [];
            setHistory(hist);
            if (hist.length > 0) {
                lastHistoryIdRef.current = Math.max(...hist.map((h) => h.id));
            }
        } catch {}
    }, []);

    const fetchStats = useCallback(async () => {
        try {
            const res = await fetch('/packaging/stats', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const data = await res.json();
                setStats(data);
            }
        } catch {}
    }, []);

    const poll = useCallback(async () => {
        try {
            const res = await fetch(`/packaging/history/poll?after_id=${lastHistoryIdRef.current}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            const newEntries = data.history ?? [];
            if (newEntries.length > 0) {
                setHistory((prev) => {
                    const merged = [...newEntries, ...prev].slice(0, 100);
                    return merged;
                });
                lastHistoryIdRef.current = Math.max(lastHistoryIdRef.current, ...newEntries.map((h) => h.id));
                await Promise.all([fetchItems(), fetchStats()]);
            }
        } catch {}
    }, [fetchItems, fetchStats]);

    const handleScan = useCallback(async (ean) => {
        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const res = await fetch('/packaging/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ean }),
            });
            const data = await res.json();

            if (res.ok) {
                const wo = data.work_order;
                const packedQty = wo.packed_qty;
                const plannedQty = wo.planned_qty;
                const pct = plannedQty > 0 ? Math.min(100, Math.round((packedQty / plannedQty) * 100)) : 0;
                setLastScan({
                    success: true,
                    product: wo.product,
                    ean,
                    packed_qty: packedQty,
                    planned_qty: plannedQty,
                    progress: pct,
                    scanned_at: formatTime(new Date()),
                });
                setFlash('success');
                await Promise.all([fetchItems(), fetchStats()]);
                setHistory((prev) => [
                    { id: Date.now(), ean, product_name: wo.product, scanned_at: formatTime(new Date()) },
                    ...prev,
                ].slice(0, 100));
            } else {
                setLastScan({ success: false, ean, error: data.message, scanned_at: formatTime(new Date()) });
                setFlash('error');
            }
        } catch {
            setLastScan({ success: false, ean, error: 'Błąd połączenia', scanned_at: formatTime(new Date()) });
            setFlash('error');
        }

        setTimeout(() => setFlash(null), 2000);
    }, [fetchItems, fetchStats]);

    const onKey = useCallback((e) => {
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if (e.key === 'Enter') {
            const ean = bufferRef.current.trim();
            bufferRef.current = '';
            if (bufferTimerRef.current) clearTimeout(bufferTimerRef.current);
            if (ean) handleScan(ean);
        } else if (e.key.length === 1) {
            bufferRef.current += e.key;
            if (bufferTimerRef.current) clearTimeout(bufferTimerRef.current);
            bufferTimerRef.current = setTimeout(() => { bufferRef.current = ''; }, 500);
        }
    }, [handleScan]);

    useEffect(() => {
        Promise.all([fetchItems(), fetchHistory(), fetchStats()]);
        const interval = setInterval(poll, 3000);
        document.addEventListener('keydown', onKey);
        return () => {
            clearInterval(interval);
            document.removeEventListener('keydown', onKey);
        };
    }, [fetchItems, fetchHistory, fetchStats, poll, onKey]);

    const flashBg =
        flash === 'success'
            ? 'bg-green-50 dark:bg-green-900/20 border-green-300'
            : flash === 'error'
            ? 'bg-red-50 dark:bg-red-900/20 border-red-300'
            : '';

    return (
        <>
            <Head title="Stanowisko Pakowania" />
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                            </svg>
                            Stanowisko Pakowania
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Zmiana: <span className="font-semibold"><ShiftLabel /></span>
                            &nbsp;&middot;&nbsp; Zalogowany: <span className="font-semibold">{auth?.user?.name}</span>
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-400 px-3 py-1.5 rounded-full">
                            <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                            Skanowanie aktywne
                        </span>
                    </div>
                </div>

                {/* Stats row */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div className="card text-center">
                        <p className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">{stats.today_packed ?? '—'}</p>
                        <p className="text-xs text-gray-500 mt-1">Spakowano (zmiana)</p>
                    </div>
                    <div className="card text-center">
                        <p className="text-3xl font-extrabold text-gray-700 dark:text-gray-200">{stats.plan ?? '—'}</p>
                        <p className="text-xs text-gray-500 mt-1">Plan łącznie</p>
                    </div>
                    <div className="card text-center">
                        <p className={`text-3xl font-extrabold ${(stats.backlog ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                            {stats.backlog ?? '—'}
                        </p>
                        <p className="text-xs text-gray-500 mt-1">Backlog</p>
                    </div>
                    <div className="card text-center">
                        <p className={`text-3xl font-extrabold ${realizacja >= 100 ? 'text-green-600' : realizacja >= 50 ? 'text-yellow-600' : 'text-red-600'}`}>
                            {realizacja}%
                        </p>
                        <p className="text-xs text-gray-500 mt-1">Realizacja</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    {/* Last scan */}
                    <div className="card">
                        <h2 className="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">
                            Ostatnie skanowanie
                        </h2>
                        {!lastScan ? (
                            <div className="py-8 text-center text-gray-400 dark:text-gray-600 text-sm">
                                Przyłóż kod EAN do skanera&hellip;
                            </div>
                        ) : (
                            <div>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xl font-bold text-gray-800 dark:text-white">{lastScan.product}</p>
                                        <p className="text-sm text-gray-500 mt-0.5">
                                            EAN: <span className="font-mono">{lastScan.ean}</span>
                                            &nbsp;&middot;&nbsp; {lastScan.scanned_at}
                                        </p>
                                    </div>
                                    <span className={`shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${lastScan.success ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'}`}>
                                        {lastScan.success ? 'OK' : 'Błąd'}
                                    </span>
                                </div>
                                {lastScan.success && (
                                    <div className="mt-3 flex items-center gap-3">
                                        <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div
                                                className={`h-2 rounded-full transition-all duration-500 ${lastScan.progress >= 100 ? 'bg-green-500' : lastScan.progress >= 50 ? 'bg-yellow-500' : 'bg-indigo-500'}`}
                                                style={{ width: `${lastScan.progress ?? 0}%` }}
                                            />
                                        </div>
                                        <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            {lastScan.packed_qty} / {lastScan.planned_qty} szt.
                                        </span>
                                    </div>
                                )}
                                {!lastScan.success && lastScan.error && (
                                    <div className="mt-3 text-sm text-red-600 dark:text-red-400 font-medium">{lastScan.error}</div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Flash overlay */}
                    <div className={`card flex items-center justify-center min-h-[120px] ${flashBg}`}>
                        {flash === null && (
                            <div className="text-center text-gray-400 dark:text-gray-600 text-sm select-none">
                                <svg className="mx-auto w-10 h-10 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5"
                                        d="M12 4v1m6.364 1.636l-.707.707M20 12h-1M17.657 17.657l-.707-.707M12 20v-1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l.707.707" />
                                </svg>
                                Czekam na skan&hellip;
                            </div>
                        )}
                        {flash === 'success' && (
                            <div className="text-center">
                                <svg className="mx-auto w-14 h-14 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p className="text-green-700 dark:text-green-300 font-bold mt-2">Zeskanowano!</p>
                            </div>
                        )}
                        {flash === 'error' && (
                            <div className="text-center">
                                <svg className="mx-auto w-14 h-14 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <p className="text-red-700 dark:text-red-300 font-bold mt-2">Błąd skanowania</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Items to pack */}
                <div className="card overflow-hidden p-0 mb-6">
                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                            Zlecenia do spakowania
                        </h2>
                        <span className="text-xs text-gray-400">{items.length} pozycji</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Zlecenie</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produkt</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">EAN</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Spakowano</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Postęp</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-gray-400 text-sm">
                                            Brak zleceń z przypisanymi kodami EAN
                                        </td>
                                    </tr>
                                ) : items.map((item) => (
                                    <tr key={item.id} className={item.done ? 'bg-green-50 dark:bg-green-900/10' : ''}>
                                        <td className="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{item.order_no}</td>
                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{item.product}</td>
                                        <td className="px-4 py-3">
                                            {(item.eans ?? []).map((ean) => (
                                                <span key={ean} className="inline-block font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded mr-1 mb-0.5">
                                                    {ean}
                                                </span>
                                            ))}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold">{item.packed_qty}</td>
                                        <td className="px-4 py-3 text-right text-gray-500">{item.planned_qty}</td>
                                        <td className="px-4 py-3">
                                            <ProgressBar pct={item.progress} done={item.done} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Scan log */}
                <div className="card overflow-hidden p-0">
                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                            Historia skanowań (zmiana)
                        </h2>
                    </div>
                    <div className="overflow-x-auto max-h-64 overflow-y-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Czas</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                                    <th className="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">EAN</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {history.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-6 text-center text-gray-400 text-sm">
                                            Brak skanowań w tej zmianie
                                        </td>
                                    </tr>
                                ) : history.map((entry) => (
                                    <tr key={entry.id}>
                                        <td className="px-4 py-2.5 font-mono text-gray-500 text-xs whitespace-nowrap">{entry.scanned_at}</td>
                                        <td className="px-4 py-2.5 font-medium text-gray-700 dark:text-gray-300">{entry.product_name}</td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-gray-500">{entry.ean}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

Station.layout = (page) => <AppLayout>{page}</AppLayout>;
