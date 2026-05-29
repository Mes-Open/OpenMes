import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

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

function StatusBadge({ item }) {
    if (item.done) {
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                Spakowane
            </span>
        );
    }
    if (item.status === 'DONE') {
        return (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                W trakcie
            </span>
        );
    }
    return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
            {item.status}
        </span>
    );
}

export default function Admin() {
    const { items = [], stats = {} } = usePage().props;

    const totalPacked = stats.total_packed ?? 0;
    const plan = stats.plan ?? 0;
    const realizacja = plan > 0 ? Math.min(100, Math.round((totalPacked / plan) * 100)) : 0;

    const now = new Date();
    const hour = now.getHours();
    const shiftLabel = hour >= 6 && hour < 18 ? '06:00 – 18:00' : '18:00 – 06:00';

    const realizacjaColor =
        realizacja >= 100 ? 'text-green-600' : realizacja >= 50 ? 'text-yellow-600' : 'text-red-600';
    const realizacjaBar =
        realizacja >= 100 ? 'bg-green-500' : realizacja >= 50 ? 'bg-yellow-500' : 'bg-red-500';

    return (
        <>
            <Head title="Pakowanie — Przegląd" />
            <div className="max-w-7xl mx-auto">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-1 text-sm text-gray-500 mb-4">
                    <a href="/admin/dashboard" className="hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
                    <span className="mx-1">/</span>
                    <span className="text-gray-700 dark:text-gray-300">Pakowanie</span>
                </nav>

                {/* Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-800 dark:text-white">Pakowanie &mdash; Przegląd</h1>
                        <p className="text-sm text-gray-500 mt-1">Bieżąca zmiana: {shiftLabel}</p>
                    </div>
                    <div className="flex gap-2">
                        <a href="/packaging/station" className="btn-touch btn-primary">Otwórz stanowisko</a>
                        <a href="/packaging/eans" className="btn-touch btn-secondary">Zarządzaj EAN</a>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div className="card text-center">
                        <p className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                            {(stats.today_packed ?? 0).toLocaleString()}
                        </p>
                        <p className="text-xs text-gray-500 mt-1">Spakowano (zmiana)</p>
                    </div>
                    <div className="card text-center">
                        <p className="text-3xl font-extrabold text-gray-700 dark:text-gray-200">
                            {plan.toLocaleString()}
                        </p>
                        <p className="text-xs text-gray-500 mt-1">Plan łącznie</p>
                    </div>
                    <div className="card text-center">
                        <p className={`text-3xl font-extrabold ${(stats.backlog ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                            {(stats.backlog ?? 0).toLocaleString()}
                        </p>
                        <p className="text-xs text-gray-500 mt-1">Backlog</p>
                    </div>
                    <div className="card text-center">
                        <p className={`text-3xl font-extrabold ${realizacjaColor}`}>{realizacja}%</p>
                        <p className="text-xs text-gray-500 mt-1">Realizacja</p>
                        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                            <div className={`h-1.5 rounded-full ${realizacjaBar}`} style={{ width: `${realizacja}%` }} />
                        </div>
                    </div>
                </div>

                {/* Items table */}
                <div className="card overflow-hidden p-0">
                    <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h2 className="font-semibold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">
                            Zlecenia do spakowania ({items.length})
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Zlecenie</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Produkt</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Linia</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">EAN</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Spakowano</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Plan</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-32">Postęp</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/50">
                                {items.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-10 text-center text-gray-400 text-sm">
                                            Brak zleceń z przypisanymi kodami EAN
                                        </td>
                                    </tr>
                                ) : items.map((item) => (
                                    <tr key={item.id} className={item.done ? 'bg-green-50 dark:bg-green-900/10' : ''}>
                                        <td className="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{item.order_no}</td>
                                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{item.product}</td>
                                        <td className="px-4 py-3 text-gray-500">{item.line ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            {(item.eans ?? []).map((ean) => (
                                                <span key={ean} className="inline-block font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded mr-1">
                                                    {ean}
                                                </span>
                                            ))}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold text-gray-800 dark:text-white">{item.packed_qty}</td>
                                        <td className="px-4 py-3 text-right text-gray-500">{item.planned_qty}</td>
                                        <td className="px-4 py-3">
                                            <ProgressBar pct={item.progress} done={item.done} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge item={item} />
                                        </td>
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

Admin.layout = (page) => <AppLayout>{page}</AppLayout>;
