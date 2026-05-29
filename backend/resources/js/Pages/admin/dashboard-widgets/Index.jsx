import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

export default function DashboardWidgetsIndex() {
    const { widgets: initialWidgets = [] } = usePage().props;
    const { csrf_token } = usePage().props;

    const [widgets, setWidgets] = useState(initialWidgets);
    const [dirty, setDirty] = useState(false);
    const [saved, setSaved] = useState(false);

    function moveUp(index) {
        if (index === 0) return;
        setWidgets((prev) => {
            const next = [...prev];
            [next[index - 1], next[index]] = [next[index], next[index - 1]];
            return next;
        });
        setDirty(true);
    }

    function moveDown(index) {
        setWidgets((prev) => {
            if (index >= prev.length - 1) return prev;
            const next = [...prev];
            [next[index], next[index + 1]] = [next[index + 1], next[index]];
            return next;
        });
        setDirty(true);
    }

    function toggleEnabled(index) {
        setWidgets((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], enabled: !next[index].enabled };
            return next;
        });
        setDirty(true);
    }

    async function saveAll() {
        await fetch('/admin/dashboard-widgets/save-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf_token,
            },
            body: JSON.stringify({
                widgets: widgets.map((w) => ({ id: w.id, enabled: w.enabled })),
            }),
        });
        setDirty(false);
        setSaved(true);
        setTimeout(() => {
            window.location.href = '/admin/dashboard';
        }, 1200);
    }

    return (
        <>
            <Head title="Dashboard Setup" />
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <a href="/settings" className="text-gray-500 hover:text-gray-700">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 className="text-3xl font-bold text-gray-800">Dashboard Setup</h1>
                        <p className="text-gray-500 text-sm mt-0.5">Enable, disable, and reorder dashboard widgets</p>
                    </div>
                </div>

                <div className="space-y-2">
                    {widgets.map((widget, index) => (
                        <div key={widget.id} className="card flex items-center gap-3">
                            {/* Move buttons */}
                            <div className="flex flex-col shrink-0">
                                <button
                                    onClick={() => moveUp(index)}
                                    disabled={index === 0}
                                    className="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-20 disabled:cursor-not-allowed"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <button
                                    onClick={() => moveDown(index)}
                                    disabled={index === widgets.length - 1}
                                    className="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-20 disabled:cursor-not-allowed"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            {/* Position number */}
                            <span className="text-sm font-mono text-gray-400 w-6 text-center shrink-0">
                                {index + 1}
                            </span>

                            {/* Info */}
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h3 className="text-sm font-bold text-gray-800 dark:text-gray-200">{widget.name}</h3>
                                    <span
                                        className={`px-2 py-0.5 rounded-full text-xs ${widget.source === 'builtin'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                            : 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'}`}
                                    >
                                        {widget.source === 'builtin' ? 'Built-in' : widget.module_name}
                                    </span>
                                    <span className="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        {widget.zone}
                                    </span>
                                </div>
                                {widget.description && (
                                    <p className="text-xs text-gray-500 mt-0.5">{widget.description}</p>
                                )}
                            </div>

                            {/* Toggle */}
                            <button
                                onClick={() => toggleEnabled(index)}
                                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shrink-0 ${widget.enabled
                                    ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900 dark:text-red-300'}`}
                            >
                                {widget.enabled ? 'Enabled' : 'Disabled'}
                            </button>
                        </div>
                    ))}

                    <div className="flex justify-between items-center mt-6">
                        <div>
                            <p className="text-xs text-gray-400">Use arrows to reorder. Modules can register additional widgets.</p>
                            {dirty && (
                                <p className="text-xs text-orange-600 font-medium mt-1">You have unsaved changes!</p>
                            )}
                        </div>
                        <button
                            onClick={saveAll}
                            className={`btn-touch btn-primary${dirty ? ' animate-pulse ring-2 ring-blue-400' : ''}`}
                        >
                            Save
                        </button>
                    </div>
                </div>

                {/* Toast */}
                {saved && (
                    <div className="fixed bottom-6 right-6 z-50 bg-green-600 text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Saved! Redirecting...</span>
                    </div>
                )}
            </div>
        </>
    );
}

DashboardWidgetsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
